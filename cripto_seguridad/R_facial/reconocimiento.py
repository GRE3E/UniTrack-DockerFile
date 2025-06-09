import face_recognition
import cv2
import numpy as np
import os

# Directorio donde se encuentran las fotos de los estudiantes
DATABASE_DIR = 'fotos_estudiantes'


# Función para cargar las imágenes y aprender las características faciales
def cargar_imagenes_de_base_datos(database_dir):
    rostros_conocidos = []
    nombres_conocidos = []

    for archivo in os.listdir(database_dir):
        if archivo.endswith(('.jpg', '.jpeg', '.png')):
            ruta_imagen = os.path.join(database_dir, archivo)
            imagen = face_recognition.load_image_file(ruta_imagen)
            codificaciones = face_recognition.face_encodings(imagen)
            if codificaciones:
                rostros_conocidos.append(codificaciones[0])
                nombres_conocidos.append(os.path.splitext(archivo)[0])

    return rostros_conocidos, nombres_conocidos


# Cargar las imágenes de la base de datos
rostros_conocidos, nombres_conocidos = cargar_imagenes_de_base_datos(DATABASE_DIR)

# Capturar una imagen desde la webcam
video_capture = cv2.VideoCapture(0)

while True:
    # Capturar un solo frame de video
    ret, frame = video_capture.read()

    # Convertir la imagen de BGR (OpenCV) a RGB (face_recognition)
    rgb_frame = frame[:, :, ::-1]

    # Encontrar todas las caras y sus codificaciones en el frame de video
    ubicaciones_rostros = face_recognition.face_locations(rgb_frame)
    codificaciones_rostros = face_recognition.face_encodings(rgb_frame, ubicaciones_rostros)

    for (top, right, bottom, left), codificacion in zip(ubicaciones_rostros, codificaciones_rostros):
        # Comparar la cara capturada con las caras de la base de datos
        coincidencias = face_recognition.compare_faces(rostros_conocidos, codificacion)

        nombre = "Desconocido"

        if True in coincidencias:
            # Encuentra los índices de todas las caras coincidentes
            indices_coincidencias = [i for i, coincidir in enumerate(coincidencias) if coincidir]

            # Selecciona el primer nombre coincidente
            nombre = nombres_conocidos[indices_coincidencias[0]]
            print(f"Hola {nombre}, puedes ingresar.")
        else:
            print("No se ha encontrado coincidencia en la base de datos.")

        # Dibujar un rectángulo alrededor de la cara
        cv2.rectangle(frame, (left, top), (right, bottom), (0, 0, 255), 2)

        # Dibujar una etiqueta con un nombre debajo de la cara
        cv2.rectangle(frame, (left, bottom - 35), (right, bottom), (0, 0, 255), cv2.FILLED)
        font = cv2.FONT_HERSHEY_DUPLEX
        cv2.putText(frame, nombre, (left + 6, bottom - 6), font, 1.0, (255, 255, 255), 1)

    # Mostrar el frame con los rectángulos y nombres
    cv2.imshow('Video', frame)

    # Salir del loop si se presiona 'q'
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

# Liberar la captura de video y cerrar todas las ventanas
video_capture.release()
cv2.destroyAllWindows()
