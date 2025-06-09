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

import mysql.connector

load_dotenv()

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

# Configuración de la conexión a la base de datos MySQL
mydb = mysql.connector.connect(
    host=os.getenv('MYSQL_HOST'),
    user=os.getenv('MYSQL_USER'),
    password=os.getenv('MYSQL_PASSWORD'),
    database=os.getenv('MYSQL_DATABASE'),
    port=int(os.getenv('MYSQL_PORT', 3306))
)
app.config['UPLOAD_FOLDER'] = os.path.join(app.root_path, 'static')

# Declarar la variable hash_blockchain como global
global hash_blockchain
hash_blockchain = None
# Variable global para almacenar la URL de la imagen generada

# Funciones para manejar la tabla temporal
def store_temp_user(user_id, nombre, correo, modo, correoA):
    mycursor = mydb.cursor()
    sql = "INSERT INTO temp_logged_user (user_id, nombre, correo, modo, correoA) VALUES (%s, %s, %s, %s, %s)"
    val = (user_id, nombre, correo, modo, correoA)
    mycursor.execute(sql, val)
    mydb.commit()

def get_temp_user():
    mycursor = mydb.cursor()
    sql = "SELECT user_id, nombre, correo, modo, correoA, timestamp FROM temp_logged_user ORDER BY timestamp DESC LIMIT 1"
    mycursor.execute(sql)
    user = mycursor.fetchone()
    return user

def delete_temp_user():
    mycursor = mydb.cursor()
    sql = "DELETE FROM temp_logged_user"
    mycursor.execute(sql)
    mydb.commit()

@app.route('/login_user', methods=['POST'])
def login_user():
    data = request.json
    user_id = data.get('id')
    nombre = data.get('nombre')
    correo = data.get('correo')
    modo = data.get('modo')
    correoA = data.get('correoA')

    if not user_id:
        return jsonify({"error": "ID de usuario no proporcionado"}), 400

    store_temp_user(user_id, nombre, correo, modo, correoA)
    return jsonify({"logged_in": True})


@app.route('/generar_qr')
def generar_qr():

    imagen = capturar_imagen()
    if imagen is None:
        return jsonify({"error": "Error al capturar la imagen."}), 400

    imagen_path = "captura_temp.png"
    cv2.imwrite(imagen_path, imagen)

    suma_pixeles = analizar_imagen(imagen)
    metadatos = extraer_metadatos(imagen)
    global hash_blockchain
    hash_blockchain = generar_hash_para_blockchain(suma_pixeles, metadatos)

    if enviar_hash_a_blockchain(hash_blockchain):
        nombre_archivo_qr = "codigo_qr.png"
        qr_image_path = os.path.join(app.config['UPLOAD_FOLDER'], nombre_archivo_qr)
        generar_codigo_qr(hash_blockchain, qr_image_path)

        os.remove(imagen_path)
        qr_image_url = url_for('static', filename=nombre_archivo_qr, _external=True)
        return jsonify({"qr_image_url": qr_image_url})
    else:
        os.remove(imagen_path)
        return jsonify({"error": "Error al enviar el hash a la blockchain."}), 500



@app.route('/verify_qr', methods=['POST'])
def verify_qr():
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
            mycursor = mydb.cursor()
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


@app.route('/reporte', methods=['POST'])
def reporte(data=None):
    if not data:
        data = request.json

    user_id = data.get('id')
    nombres = data.get('nombre')
    correo = data.get('correo')
    modo = data.get('modo')
    correoA = data.get('correoA')

    if not user_id:
        return jsonify({"error": "ID de usuario no proporcionado"}), 400

    # Crear un cursor para ejecutar consultas SQL
    mycursor = mydb.cursor()

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
        msg = Message("Ingreso a las instalaciones", sender="mixie.brighit01@gmail.com",
                      recipients=[correoA])
        msg.body = f"El usuario {nombres} ingresó a las instalaciones a las {hora_actual} del {fecha_actual}."
        mail.send(msg)
    except Exception as e:
       pass
    return jsonify({"reported": True})

if __name__ == "__main__":
    app.run()
