import qrcode
import  os

def generar_codigo_qr(hash_str, qr_image_path):
    # Verificar si el directorio existe, si no, crearlo
    directorio = os.path.dirname(qr_image_path)
    if not os.path.exists(directorio):
        os.makedirs(directorio)
    # Crear el objeto QRCode
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    # Agregar los datos (en este caso, el hash) al código QR
    qr.add_data(hash_str)
    qr.make(fit=True)

    # Crear una imagen del código QR
    imagen_qr = qr.make_image(fill='black', back_color='white')

    # Guardar la imagen como un archivo PNG
    imagen_qr.save(qr_image_path)

    print(f"Se ha generado el código QR con el hash '{hash_str}' en el archivo '{qr_image_path}.png'")
