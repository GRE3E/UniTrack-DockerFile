import hashlib
from PIL import Image
from PIL.ExifTags import TAGS

def analizar_imagen(imagen):
    alto, ancho, _ = imagen.shape
    suma_valores = 0
    for y in range(alto):
        for x in range(ancho):
            pixel = imagen[y, x]
            suma_valores += sum(pixel)
    return suma_valores

def extraer_metadatos(imagen):
    imagen_pil = Image.fromarray(imagen)
    metadatos = {}
    if hasattr(imagen_pil, '_getexif'):
        exif_data = imagen_pil._getexif()
        if exif_data is not None:
            for tag, value in exif_data.items():
                decoded = TAGS.get(tag, tag)
                metadatos[decoded] = value
    return metadatos

def generar_hash_para_blockchain(suma_pixeles, metadatos):
    datos_combinados = str(suma_pixeles) + str(metadatos)
    hash_blockchain = hashlib.sha256(datos_combinados.encode()).hexdigest()
    return hash_blockchain

"""def procesar_imagen():
    imagen = capturar_imagen()
    if imagen is not None:
        suma_pixeles = analizar_imagen(imagen)
        metadatos = extraer_metadatos(imagen)
        hash_blockchain = generar_hash_para_blockchain(suma_pixeles, metadatos)
        enviar_a_blockchain(hash_blockchain)
        cv2.waitKey(3000)
        cv2.destroyAllWindows()
    else:
        print("No se pudo capturar la imagen.")"""

#procesar_imagen()