import qrcode

def generar_codigo_qr(hash_str, nombre_archivo):
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
    imagen_qr.save(nombre_archivo + '.png')

    print(f"Se ha generado el código QR con el hash '{hash_str}' en el archivo '{nombre_archivo}.png'")
