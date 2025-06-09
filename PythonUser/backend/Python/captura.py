import cv2
import os

def capturar_imagen():
    # Inicializar la cámara
    capturador = cv2.VideoCapture(0)

    # Verificar si la cámara se abrió correctamente
    if not capturador.isOpened():
        print("Error al abrir la cámara.")
        return None

    # Capturar una imagen
    ret, imagen = capturador.read()

    # Verificar si la imagen se capturó correctamente
    if not ret:
        print("Error al capturar la imagen.")
        return None

    # Liberar la cámara
    capturador.release()

    return imagen

def guardar_imagen(imagen, ruta_carpeta):
    # Verificar si la carpeta existe, si no, crearla
    if not os.path.exists(ruta_carpeta):
        os.makedirs(ruta_carpeta)

    # Guardar la imagen en la carpeta especificada
    ruta_imagen = os.path.join(ruta_carpeta, "imagen_capturada.jpg")
    cv2.imwrite(ruta_imagen, imagen)
    print("Imagen guardada en:", ruta_imagen)

# Carpeta donde se guardarán las imágenes capturadas
ruta_carpeta = "imagenes"

# Capturar una imagen
imagen = capturar_imagen()
if imagen is not None:
    # Guardar la imagen en la carpeta especificada
    guardar_imagen(imagen, ruta_carpeta)