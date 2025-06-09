import cv2
import os
from flask import Flask, send_file, jsonify
from captura import capturar_imagen
from analisis import  analizar_imagen, extraer_metadatos, generar_hash_para_blockchain
from blockchain import enviar_a_blockchain as enviar_hash_a_blockchain
from generar import generar_codigo_qr
from verificar import capturar_codigo_qr
app = Flask(__name__)


def main():
    imagen = capturar_imagen()
    if imagen is None:
        return jsonify({"error": "Error al capturar la imagen."}), 500

    imagen_path = "captura_temp.png"
    cv2.imwrite(imagen_path, imagen)

    suma_pixeles = analizar_imagen(imagen)
    metadatos = extraer_metadatos(imagen)
    hash_blockchain = generar_hash_para_blockchain(suma_pixeles, metadatos)

    if enviar_hash_a_blockchain(hash_blockchain):
        nombre_archivo_qr = "codigo_qr"
        generar_codigo_qr(hash_blockchain, nombre_archivo_qr)

        # Mostrar la imagen y hash

        print("Hash paraaa blockchain:", hash_blockchain)

        # Verificar el código QR
        contenido_qr = capturar_codigo_qr()

        if contenido_qr == hash_blockchain:
            print("Entrar")
        else:
            print("El código QR no coincide con el hash de la blockchain.")

        os.remove(imagen_path)
        return send_file(f"{nombre_archivo_qr}.png", mimetype='image/png')
    else:
        os.remove(imagen_path)
        return jsonify({"error": "Error al enviar el hash a la blockchain."}), 500

if __name__ == "__main__":
    app.run(debug=True)

