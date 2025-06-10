import psycopg2
import os
from dotenv import load_dotenv

# Cargar variables de entorno desde .env
load_dotenv()

# Configuración de la conexión a la base de datos PostgreSQL
mydb = psycopg2.connect(
    host=os.environ.get('PG_HOST', 'localhost'),
    user=os.environ.get('PG_USER', 'proyuser_user'),
    password=os.environ.get('PG_PASSWORD', '1Pi94v788RMiCObSqGYuPZVVwv8pv6em'),
    dbname=os.environ.get('PG_DATABASE', 'proyuser'),
    port=os.environ.get('PG_PORT', 5432)
)

# Crea un cursor para ejecutar consultas SQL
mycursor = mydb.cursor()

