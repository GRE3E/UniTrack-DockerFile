import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
import logging
import os
import sys
from dotenv import load_dotenv

# Agrega el path del directorio padre para encontrar analisis_objetos
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
from analisis_objetos.analisis import token, hash_blockchain

logging.basicConfig(level=logging.DEBUG)

# Cargar variables de entorno
load_dotenv()

def enviar_correo(token, hash_imagen, destinatario):
    mensaje = MIMEMultipart('alternative')
    mensaje['Subject'] = 'Información del Token y Hash de Imagen'
    mensaje['From'] = os.environ.get('MAIL_DEFAULT_SENDER')
    mensaje['To'] = destinatario

    try:
        servidor_smtp = os.environ.get('SMTP_HOST', 'smtp.gmail.com')
        puerto_smtp = int(os.environ.get('SMTP_PORT', 587))
        remitente = os.environ.get('MAIL_USERNAME')
        contrasena = os.environ.get('MAIL_PASSWORD')  # Asegúrate de usar una contraseña segura

        servidor = smtplib.SMTP(servidor_smtp, puerto_smtp)
        servidor.starttls()
        servidor.login(remitente, contrasena)

        mensaje_texto = f"Token: {token}\nHash de la imagen: {hash_imagen}"
        mensaje.attach(MIMEText(mensaje_texto, 'plain'))

        servidor.sendmail(remitente, destinatario, mensaje.as_string())
        print("Correo enviado exitosamente.")
    except smtplib.SMTPException as e:
        print(f"Error al enviar el correo: {e}")
    except Exception as e:
        print(f"Error inesperado: {e}")
    finally:
        servidor.quit()

# Ejemplo de uso
token = "token"
hash_imagen = "hash_blockchain"
destinatario = "mixie.brighit01@gmail.com"

enviar_correo(token, hash_imagen, destinatario)