# Importar librerías necesarias para recibir la información desde Ionic
from flask import Flask, request

app = Flask(__name__)

@app.route('/recibir-correo', methods=['POST'])
def recibir_correo():
    correo = request.json.get('correo')
    # Aquí puedes procesar el correo recibido, utilizar tu IA, o realizar cualquier otra acción necesaria
    return 'Correo recibido correctamente'

if __name__ == '__main__':
    app.run(debug=True)
