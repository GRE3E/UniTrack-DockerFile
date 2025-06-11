import cv2
import os
from flask import Flask, jsonify, request, url_for
from flask_cors import CORS
import datetime
from flask_mail import Mail, Message
import json
import sys
import psycopg2
import time
import logging
from dotenv import load_dotenv

# Configurar logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler('/var/log/python_app.log')
    ]
)
logger = logging.getLogger(__name__)

# Cargar variables de entorno
load_dotenv(dotenv_path='../.env')

app = Flask(__name__)

# Configuración del correo con validación
try:
    app.config['MAIL_SERVER'] = 'smtp.gmail.com'
    app.config['MAIL_PORT'] = 587
    app.config['MAIL_USE_TLS'] = True
    app.config['MAIL_USE_SSL'] = False
    app.config['MAIL_USERNAME'] = os.getenv('MAIL_USERNAME')
    app.config['MAIL_PASSWORD'] = os.getenv('MAIL_PASSWORD')
    app.config['MAIL_DEFAULT_SENDER'] = os.getenv('MAIL_DEFAULT_SENDER')
    
    if not app.config['MAIL_USERNAME']:
        logger.warning("MAIL_USERNAME no configurado")
    
    mail = Mail(app)
    logger.info("Configuración de correo inicializada")
except Exception as e:
    logger.error(f"Error configurando correo: {e}")
    mail = None

CORS(app, resources={r"/*": {"origins": "*"}})

# Variable global para conexión de BD
mydb = None

def init_database_tables():
    """Inicializar tablas necesarias si no existen"""
    if not mydb:
        return False
    
    try:
        with mydb.cursor() as cursor:
            # Crear tabla temp_logged_user si no existe
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS temp_logged_user (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    nombre VARCHAR(255),
                    correo VARCHAR(255),
                    modo VARCHAR(100),
                    correoA VARCHAR(255),
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            """)
            
            # Crear tabla reportes si no existe
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS reportes (
                    id SERIAL PRIMARY KEY,
                    fecha DATE NOT NULL,
                    hora TIME NOT NULL,
                    user_id INTEGER NOT NULL,
                    nombre VARCHAR(255),
                    email VARCHAR(255),
                    modo VARCHAR(100),
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            """)
            
        mydb.commit()
        logger.info("Tablas de base de datos verificadas/creadas exitosamente")
        return True
    except Exception as e:
        logger.error(f"Error inicializando tablas: {e}")
        return False

def get_db_connection():
    """Establecer conexión a la base de datos con reintentos"""
    max_retries = 5
    retry_delay = 5
    
    for attempt in range(max_retries):
        try:
            logger.info(f"Intento de conexión a BD {attempt + 1}/{max_retries}")
            
            conn = psycopg2.connect(
                host=os.getenv('PG_HOST', 'dpg-d14br76uk2gs73and8u0-a.oregon-postgres.render.com'),
                user=os.getenv('PG_USER', 'proyuser_user'),
                password=os.getenv('PG_PASSWORD', '1Pi94v788RMiCObSqGYuPZVVwv8pv6em'),
                database=os.getenv('PG_DATABASE', 'proyuser'),
                port=os.getenv('PG_PORT', '5432'),
                connect_timeout=30,
                sslmode='require'
            )
            
            # Verificar que la conexión funciona
            with conn.cursor() as cursor:
                cursor.execute("SELECT 1")
                cursor.fetchone()
            
            logger.info("Conexión a la base de datos exitosa")
            return conn
            
        except psycopg2.OperationalError as e:
            logger.warning(f"Error de conexión BD (intento {attempt + 1}): {e}")
            if attempt < max_retries - 1:
                time.sleep(retry_delay)
            else:
                logger.error("No se pudo conectar a la base de datos después de múltiples intentos")
                
        except Exception as e:
            logger.error(f"Error inesperado conectando a BD: {e}")
            break
    
    return None

# Inicializar conexión a la base de datos
def initialize_database():
    global mydb
    try:
        mydb = get_db_connection()
        if mydb:
            init_database_tables()
            return True
        else:
            logger.warning("Aplicación iniciará sin conexión a BD")
            return False
    except Exception as e:
        logger.error(f"Error crítico inicializando BD: {e}")
        return False

# Configurar directorio de uploads
app.config['UPLOAD_FOLDER'] = os.path.join(app.root_path, 'static')
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# Variable global para hash blockchain
hash_blockchain = None

def store_temp_user(user_id, nombre, correo, modo, correoA):
    """Almacenar usuario temporal en BD"""
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return False
    
    try:
        with mydb.cursor() as cursor:
            sql = "INSERT INTO temp_logged_user (user_id, nombre, correo, modo, correoA) VALUES (%s, %s, %s, %s, %s)"
            val = (user_id, nombre, correo, modo, correoA)
            cursor.execute(sql, val)
        mydb.commit()
        logger.info(f"Usuario temporal almacenado: {user_id}")
        return True
    except Exception as e:
        logger.error(f"Error almacenando usuario temporal: {e}")
        return False

def get_temp_user():
    """Obtener último usuario temporal"""
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return None
    
    try:
        with mydb.cursor() as cursor:
            sql = "SELECT user_id, nombre, correo, modo, correoA, timestamp FROM temp_logged_user ORDER BY timestamp DESC LIMIT 1"
            cursor.execute(sql)
            user = cursor.fetchone()
            return user
    except Exception as e:
        logger.error(f"Error obteniendo usuario temporal: {e}")
        return None

def delete_temp_user():
    """Eliminar usuarios temporales"""
    if not mydb:
        logger.error("No hay conexión a la base de datos disponible")
        return False
    
    try:
        with mydb.cursor() as cursor:
            sql = "DELETE FROM temp_logged_user"
            cursor.execute(sql)
        mydb.commit()
        return True
    except Exception as e:
        logger.error(f"Error eliminando usuario temporal: {e}")
        return False

@app.route('/health', methods=['GET'])
def health_check():
    """Endpoint de verificación de salud"""
    status = {
        'status': 'healthy',
        'database': 'connected' if mydb else 'disconnected',
        'mail': 'configured' if mail else 'not_configured',
        'timestamp': datetime.datetime.now().isoformat(),
        'port': os.environ.get('PORT', '5000')
    }
    return jsonify(status)

@app.route('/login_user', methods=['POST'])
def login_user():
    """Endpoint para login de usuario"""
    try:
        data = request.json
        if not data:
            return jsonify({"error": "No se recibieron datos"}), 400
            
        user_id = data.get('id')
        nombre = data.get('nombre', '')
        correo = data.get('correo', '')
        modo = data.get('modo', '')
        correoA = data.get('correoA', '')

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
    """Verificar código QR"""
    try:
        data = request.get_json()
        if not data:
            return jsonify({"verified": False, "error": "No se recibieron datos"}), 400
            
        contenido_qr = data.get('contenido_qr')
        
        global hash_blockchain

        if not contenido_qr:
            return jsonify({"verified": False, "error": "No se recibió el contenido del QR"}), 400

        # Verificar el contenido del QR con el hash de la blockchain
        if contenido_qr == hash_blockchain:
            user = get_temp_user()
            if user:
                user_id, nombre, correo, modo, correoA, timestamp = user[:6]

                # Verificar restricción de tiempo
                restriccion = datetime.timedelta(minutes=10)

                if mydb:
                    try:
                        with mydb.cursor() as cursor:
                            sql = "SELECT timestamp FROM reportes WHERE user_id = %s AND modo = %s ORDER BY timestamp DESC LIMIT 1"
                            val = (user_id, modo)
                            cursor.execute(sql, val)
                            last_record = cursor.fetchone()

                        if last_record:
                            last_timestamp = last_record[0]
                            now = datetime.datetime.now()
                            time_difference = now - last_timestamp

                            if time_difference < restriccion:
                                return jsonify({"error": "No puede generar el mismo modo de QR en un corto tiempo."}), 400
                    except Exception as e:
                        logger.error(f"Error verificando último registro: {e}")

                # Generar reporte
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
                return jsonify({"verified": True, "reporte": reporte_response.get_json()})

        return jsonify({"verified": False})
        
    except Exception as e:
        logger.error(f"Error en verify_qr: {e}")
        return jsonify({"error": "Error interno del servidor"}), 500

@app.route('/reporte', methods=['POST'])
def reporte(data=None):
    """Generar reporte de acceso"""
    try:
        if not data:
            data = request.json
            if not data:
                return jsonify({"error": "No se recibieron datos"}), 400

        user_id = data.get('id')
        nombres = data.get('nombre', '')
        correo = data.get('correo', '')
        modo = data.get('modo', '')
        correoA = data.get('correoA', '')

        if not user_id:
            return jsonify({"error": "ID de usuario no proporcionado"}), 400

        if not mydb:
            logger.warning("Generando reporte sin conexión a BD")
            return jsonify({"reported": False, "error": "No hay conexión a la base de datos"}), 500

        # Insertar en BD
        try:
            with mydb.cursor() as cursor:
                # Verificar último registro
                sql = "SELECT timestamp FROM reportes WHERE user_id = %s AND modo = %s ORDER BY timestamp DESC LIMIT 1"
                val = (user_id, modo)
                cursor.execute(sql, val)
                last_record = cursor.fetchone()

                if last_record:
                    last_timestamp = last_record[0]
                    now = datetime.datetime.now()
                    time_difference = now - last_timestamp

                    if time_difference.total_seconds() < 10 * 60:
                        return jsonify({"error": "No puede generar el mismo modo de QR en un corto tiempo."}), 400

                # Insertar nuevo reporte
                sql = "INSERT INTO reportes (fecha, hora, user_id, nombre, email, modo, timestamp) VALUES (%s, %s, %s, %s, %s, %s, %s)"
                fecha_actual = datetime.datetime.now().date()
                hora_actual = datetime.datetime.now().time()
                timestamp = datetime.datetime.now()
                val = (fecha_actual, hora_actual, user_id, nombres, correo, modo, timestamp)
                cursor.execute(sql, val)

            mydb.commit()
            logger.info(f"Reporte generado para usuario {user_id}")
            
        except Exception as e:
            logger.error(f"Error insertando reporte en BD: {e}")
            return jsonify({"error": "Error guardando reporte"}), 500
        
        # Enviar correo electrónico
        try:
            if correoA and mail:
                msg = Message("Ingreso a las instalaciones", 
                            sender=app.config['MAIL_DEFAULT_SENDER'],
                            recipients=[correoA])
                msg.body = f"El usuario {nombres} ingresó a las instalaciones a las {hora_actual} del {fecha_actual}."
                mail.send(msg)
                logger.info(f"Correo enviado exitosamente a {correoA}")
        except Exception as e:
            logger.error(f"Error enviando correo: {e}")
            # No fallar el reporte por error de correo
            
        return jsonify({"reported": True})
        
    except Exception as e:
        logger.error(f"Error en reporte: {e}")
        return jsonify({"error": "Error interno del servidor"}), 500

@app.errorhandler(404)
def not_found(error):
    return jsonify({"error": "Endpoint no encontrado"}), 404

@app.errorhandler(500)
def internal_error(error):
    return jsonify({"error": "Error interno del servidor"}), 500

if __name__ == "__main__":
    try:
        # Inicializar base de datos
        logger.info("Inicializando aplicación...")
        initialize_database()
        
        if len(sys.argv) > 1:
            # Modo de línea de comandos para procesar imagen
            logger.info("Ejecutando en modo de línea de comandos")
            image_path = sys.argv[1]
            
            try:
                # Importar módulos necesarios para procesamiento de imagen
                from capturar_imagenes.captura import capturar_imagen
                from analisis_objetos.analisis import analizar_imagen, extraer_metadatos, generar_hash_para_blockchain
                from seguridad_blockchain.blockchain import enviar_a_blockchain as enviar_hash_a_blockchain
                from QR.generar import generar_codigo_qr as generar_codigo_qr
                
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
                logger.error(f"Error en procesamiento de imagen: {e}")
                print(json.dumps({"error": f"Error en el script Python: {str(e)}"}), file=sys.stderr)
                sys.exit(1)
        else:
            # Modo servidor Flask
            port = int(os.environ.get('PORT', 5000))
            logger.info(f"Iniciando servidor Flask en puerto {port}")
            
            # Verificar configuración
            logger.info("=== Verificación de configuración ===")
            logger.info(f"Puerto: {port}")
            logger.info(f"Mail configurado: {bool(app.config.get('MAIL_USERNAME'))}")
            logger.info(f"Base de datos conectada: {bool(mydb)}")
            logger.info(f"Directorio de uploads: {app.config['UPLOAD_FOLDER']}")
            logger.info("=====================================")
            
            # Ejecutar la aplicación
            app.run(host='0.0.0.0', port=port, debug=False, threaded=True)
            
    except KeyboardInterrupt:
        logger.info("Aplicación detenida por el usuario")
    except Exception as e:
        logger.error(f"Error crítico en la aplicación: {e}")
        sys.exit(1)
    finally:
        # Cerrar conexión a BD si existe
        if mydb:
            try:
                mydb.close()
                logger.info("Conexión a la base de datos cerrada")
            except:
                pass