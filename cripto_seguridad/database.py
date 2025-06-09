import mysql.connector
import os
from dotenv import load_dotenv

# Cargar variables de entorno desde .env
load_dotenv()

# Configuración de la conexión a la base de datos MySQL
mydb = mysql.connector.connect(
    host=os.environ.get('MYSQL_HOST', 'localhost'),
    user=os.environ.get('MYSQL_USER', 'root'),
    password=os.environ.get('MYSQL_PASSWORD', ''),
    database=os.environ.get('MYSQL_DATABASE', ''),
    port=int(os.environ.get('MYSQL_PORT', 3306))
)

# Crea un cursor para ejecutar consultas SQL
mycursor = mydb.cursor()

