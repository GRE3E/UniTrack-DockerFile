import cv2
from pyzbar.pyzbar import decode


def capturar_codigo_qr():
    # Inicializar la captura de video desde la cámara
    captura = cv2.VideoCapture(0)

    while True:
        # Capturar un fotograma de la cámara
        _, frame = captura.read()

        # Decodificar el código QR
        resultados = decode(frame)

        if len(resultados) > 0:
            # Obtener el contenido del código QR
            contenido_qr = resultados[0].data.decode('utf-8')
            return contenido_qr

        # Mostrar el fotograma capturado en una ventana
        cv2.imshow('Capturando QR', frame)

        # Esperar a que se presione la tecla 'q' para salir
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    # Liberar la captura
