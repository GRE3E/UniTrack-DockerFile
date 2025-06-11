import cv2
import os
from flask import Flask, jsonify, request, url_for
from flask_cors import CORS
from capturar_imagenes.captura import capturar_imagen
from analisis_objetos.analisis import analizar_imagen, extraer_metadatos, generar_hash_para_blockchain
from seguridad_blockchain.blockchain import enviar_a_blockchain as enviar_hash_a_blockchain
from QR.generar import generar_codigo_qr as generar_codigo_qr
from QR.lectura import capturar_codigo_qr as capturar_codigo_qr
from dotenv import load_dotenv
import datetime
from flask_mail import Mail, Message
import json
import sys

import psycopg2
import time
import logging

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

load_dotenv(dotenv_path='../.env')

app = Flask(__name__)

# Configuración del correo
app.config['MAIL_SERVER'] = 'smtp.gmail.com'
app.config['MAIL_PORT'] = 587
app.config['MAIL_USE_TLS'] = True
app.config['MAIL_USE_SSL'] = False
app.config['MAIL_USERNAME'] = os.getenv('MAIL_USERNAME')
app.config['MAIL_PASSWORD'] = os.getenv('MAIL_PASSWORD')
app.config['MAIL_DEFAULT_SENDER'] = os.getenv('MAIL_DEFAULT_SENDER')

mail = Mail(app)
CORS(app, resources={r"/*": {"origins": "*"}})

def get_db_connection():
    retries = 10
    while retries > 0:
        try:
            logger.info(f"Intentando conectar a la base de datos... (intentos restantes: {retries})")
            
            # Usar la URL externa directamente
            conn = psycopg2.connect(
                host=os.getenv('PG_HOST', 'dpg-d14br76uk2gs73and8u0-a.oregon-postgres.render.com'),
                user=os.getenv('PG_USER', 'proyuser_user'),
                password=os.getenv('PG_PASSWORD', '1Pi94v788RMiCObSqGYuPZVVwv8pv6em'),
                database=os.getenv('PG_DATABASE', 'proyuser'),
                port=os.getenv('PG_PORT', '5432'),
                connect_timeout=30,
                sslmode='require'  # Importante para conexiones externas
            )
            logger.info("Conexión a la base de datos exitosa")
            return conn
        except psycopg2.OperationalError as e:
            logger.error(f"Error de conexión a la base de datos: {e}")
            retries -= 1
            if retries > 0:
                logger.info(f"Reintentando conexión en 10 segundos...")
                time.sleep(10)
            else:
                logger.error("No se pudo establecer conexión después de múltiples intentos")
                raise Exception(f"Could not connect to the database after multiple retries: {e}")
        except Exception as e:
            logger.error(f"Error inesperado: {e}")
            raise e

# Inicializar conexión a la base de datos
try:
    mydb = get_db_connection()
    logger.info("Base de datos inicializada correctamente")
except Exception as e:
    logger.error(f"Error crítico al inicializar la base de datos: {e}")
    # No salir inmediatamente, intentar continuar sin DB para debugging
    mydb = None

app.config['UPLOAD_FOLDER'] = os.path.join(app.root_path, 'static')

# Crear directorio static si no existe
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# Declarar la variable hash_blockchain como global
global hash_blockchain
hash_blockchain = None

# Funciones para manejar la tabla temporal
def store_temp_user(user_id, nombre, correo, modo, correoA):
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return False
    
    try:
        with mydb.cursor() as mycursor:
            sql = "INSERT INTO temp_logged_user (user_id, nombre, correo, modo, correoA) VALUES (%s, %s, %s, %s, %s)"
            val = (user_id, nombre, correo, modo, correoA)
            mycursor.execute(sql, val)
        mydb.commit()
        return True
    except Exception as e:
        logger.error(f"Error al almacenar usuario temporal: {e}")
        return False

def get_temp_user():
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return None
    
    try:
        with mydb.cursor() as mycursor:
            sql = "SELECT user_id, nombre, correo, modo, correoA, timestamp FROM temp_logged_user ORDER BY timestamp DESC LIMIT 1"
            mycursor.execute(sql)
            user = mycursor.fetchone()
            return user
    except Exception as e:
        logger.error(f"Error al obtener usuario temporal: {e}")
        return None

def delete_temp_user():
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return False
    
    try:
        with mydb.cursor() as mycursor:
            sql = "DELETE FROM temp_logged_user"
            mycursor.execute(sql)
        mydb.commit()
        return True
    except Exception as e:
        logger.error(f"Error al eliminar usuario temporal: {e}")
        return False

@app.route('/health', methods=['GET'])
def health_check():
    """Endpoint de verificación de salud"""
    status = {
        'status': 'healthy',
        'database': 'connected' if mydb else 'disconnected',
        'timestamp': datetime.datetime.now().isoformat()
    }
    return jsonify(status)

@app.route('/login_user', methods=['POST'])
def login_user():
    try:
        data = request.json
        user_id = data.get('id')
        nombre = data.get('nombre')
        correo = data.get('correo')
        modo = data.get('modo')
        correoA = data.get('correoA')

        if not user_id:
            return jsonify({"error": "ID de usuario no proporcionado"}), 400

        if store_temp_user(user_id, nombre, correo, modo, correoA):
            return jsonify({"logged_in": True})
        else:
            return jsonify({"error": "Error al almacenar usuario"}), 500
    except Exception as e:
        logger.error(f"Error en login_user: {e}")
        return jsonify({"error": "Error interno del servidor"}), 500

@app.route('/verify_qr', methods=['POST'])
def verify_qr():
    try:
        data = request.get_json()
        contenido_qr = data.get('contenido_qr')

        global hash_blockchain

        if not contenido_qr:
            return jsonify({"verified": False, "error": "No se recibió el contenido del QR"}), 400

        # Verificar el contenido del QR con el hash de la blockchain
        if contenido_qr == hash_blockchain:
            # Obtener la información del usuario temporal
            user = get_temp_user()
            if user:
                user_id, nombre, correo, modo, correoA, timestamp = user[:6]

                # Define el periodo de restricción, por ejemplo, 10 minutos
                restriccion = datetime.timedelta(minutes=10)

                # Verificar el último registro del usuario en la tabla de reportes
                if mydb:
                    try:
                        with mydb.cursor() as mycursor:
                            sql = "SELECT timestamp FROM reportes WHERE user_id = %s AND modo = %s ORDER BY timestamp DESC LIMIT 1"
                            val = (user_id, modo)
                            mycursor.execute(sql, val)
                            last_record = mycursor.fetchone()

                        if last_record:
                            last_timestamp = last_record[0]
                            now = datetime.datetime.now()
                            time_difference = now - last_timestamp

                            if time_difference < restriccion:
                                return jsonify({"error": "No puede generar el mismo modo de QR en un corto tiempo."}), 400
                    except Exception as e:
                        logger.error(f"Error al verificar último registro: {e}")

                data = {
                    'id': user_id,
                    'nombre': nombre,
                    'correo': correo,
                    'modo': modo,
                    'correoA': correoA,
                    'tiempo': timestamp
                }
                reporte_response = reporte(data)
                delete_temp_user()
                return jsonify({"verified": True, "reporte": reporte_response.json})

        return jsonify({"verified": False})
    except Exception as e:
        logger.error(f"Error en verify_qr: {e}")
        return jsonify({"error": "Error interno del servidor"}), 500

@app.route('/reporte', methods=['POST'])
def reporte(data=None):
    try:
        if not data:
            data = request.json

        user_id = data.get('id')
        nombres = data.get('nombre')
        correo = data.get('correo')
        modo = data.get('modo')
        correoA = data.get('correoA')

        if not user_id:
            return jsonify({"error": "ID de usuario no proporcionado"}), 400

        if not mydb:
            return jsonify({"error": "No hay conexión a la base de datos"}), 500

        # Crear un cursor para ejecutar consultas SQL
        with mydb.cursor() as mycursor:
            # Verificar el último registro del usuario
            sql = "SELECT timestamp FROM reportes WHERE user_id = %s AND modo = %s ORDER BY timestamp DESC LIMIT 1"
            val = (user_id, modo)
            mycursor.execute(sql, val)
            last_record = mycursor.fetchone()

            if last_record:
                last_timestamp = last_record[0]
                now = datetime.datetime.now()
                time_difference = now - last_timestamp

                # Verificar si la diferencia de tiempo es menor a un umbral (por ejemplo, 10 minutos)
                if time_difference.total_seconds() < 10 * 60:
                    return jsonify({"error": "No puede generar el mismo modo de QR en un corto tiempo."}), 400

            # Insertar la información en la tabla correspondiente
            sql = "INSERT INTO reportes (fecha, hora, user_id, nombre, email, modo, timestamp) VALUES (%s, %s, %s, %s, %s, %s, %s)"
            fecha_actual = datetime.datetime.now().date()
            hora_actual = datetime.datetime.now().time()
            timestamp = datetime.datetime.now()
            val = (fecha_actual, hora_actual, user_id, nombres, correo, modo, timestamp)
            mycursor.execute(sql, val)

        # Confirmar la ejecución de la consulta
        mydb.commit()
        
        # Enviar el correo electrónico
        try:
            if correoA:
                msg = Message("Ingreso a las instalaciones", 
                            sender=app.config['MAIL_DEFAULT_SENDER'],
                            recipients=[correoA])
                msg.body = f"El usuario {nombres} ingresó a las instalaciones a las {hora_actual} del {fecha_actual}."
                mail.send(msg)
                logger.info(f"Correo enviado exitosamente a {correoA}")
        except Exception as e:
            logger.error(f"Error al enviar correo: {e}")
            # No fallar el reporte por error de correo
            pass
            
        return jsonify({"reported": True})
    except Exception as e:
        logger.error(f"Error en reporte: {e}")
        return jsonify({"error": "Error interno del servidor"}), 500

if __name__ == "__main__":
    try:
        if len(sys.argv) > 1:
            # Modo de línea de comandos para procesar imagen
            image_path = sys.argv[1]
            try:
                # Cargar la imagen usando OpenCV
                imagen = cv2.imread(image_path)
                if imagen is None:
                    print(json.dumps({"error": "No se pudo cargar la imagen desde la ruta proporcionada."}), file=sys.stderr)
                    sys.exit(1)

                suma_pixeles = analizar_imagen(imagen)
                metadatos = extraer_metadatos(imagen)
                hash_blockchain = generar_hash_para_blockchain(suma_pixeles, metadatos)

                # Generar QR y obtener URL
                nombre_archivo_qr = "codigo_qr.png"
                qr_image_path = os.path.join(app.config['UPLOAD_FOLDER'], nombre_archivo_qr)
                generar_codigo_qr(hash_blockchain, qr_image_path)
                qr_image_url = url_for('static', filename=nombre_archivo_qr, _external=True)

                # Enviar a blockchain (simulado o real)
                if enviar_hash_a_blockchain(hash_blockchain):
                    print(json.dumps({"qr_image_url": qr_image_url, "hash": hash_blockchain}))
                else:
                    print(json.dumps({"error": "Error al enviar el hash a la blockchain."}), file=sys.stderr)
                    sys.exit(1)

            except Exception as e:
                print(json.dumps({"error": f"Error en el script Python: {str(e)}"}), file=sys.stderr)
                sys.exit(1)
        else:
            # Modo servidor Flask
            port = int(os.environ.get('PORT', 5000))
            logger.info(f"Iniciando aplicación Flask en http://0.0.0.0:{port}")
            
            # Verificar que la aplicación esté configurada correctamente
            logger.info("Verificando configuración...")
            logger.info(f"Mail configured: {bool(app.config.get('MAIL_USERNAME'))}")
            logger.info(f"Database connected: {bool(mydb)}")
            logger.info(f"Upload folder: {app.config['UPLOAD_FOLDER']}")
            
            app.run(host='0.0.0.0', port=port, debug=False)
    except Exception as e:
        logger.error(f"Error crítico en la aplicación: {e}")
        sys.exit(1)
    finally:
        if 'mydb' in locals() and mydb:
            mydb.close()
            logger.info("Conexión a la base de datos cerrada")