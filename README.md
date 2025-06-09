# UniTrack

## Requisitos previos

- Node.js y npm
- Python 3.x y pip
- XAMPP/WAMP (servidor Apache y MySQL)
- Composer (opcional, para PHP)
- PHPMailer (colocar la carpeta `PHPMailer-master` en `BD_PROYUSER` si no usas Composer)

---

## Configuración de variables de entorno

### 1. Backend Python (`cripto_seguridad/.env`)

```
MAIL_USERNAME=tu_correo@gmail.com
MAIL_PASSWORD=tu_contraseña
MAIL_DEFAULT_SENDER=tu_correo@gmail.com
MYSQL_HOST=localhost
MYSQL_USER=root
MYSQL_PASSWORD=root
MYSQL_DATABASE=BD_PROYUSER
MYSQL_PORT=3306
```

### 2. Backend PHP (`C:\xampp\htdocs\BD_PROYUSER\.env`)

```
DB_HOST=localhost
DB_USER=root
DB_PASS=root
DB_NAME=BD_PROYUSER
DB_PORT=3306
```

---

## Levantar el backend

### Python (Flask)

```sh
cd cripto_seguridad
pip install -r requirements.txt
python main.py
```

### PHP (API)

1. Copia la carpeta `BD_PROYUSER` (con tus archivos PHP y `.env`) a `C:\xampp\htdocs\`.
2. Asegúrate de que Apache y MySQL estén corriendo en XAMPP/WAMP.
3. Verifica que puedes acceder a [http://localhost/BD_PROYUSER/api.php](http://localhost/BD_PROYUSER/api.php) en tu navegador.

---

## Levantar el frontend

### PythonUser o PythonAdmin

```sh
cd PythonUser
npm install
ng serve
```
o si tienes Ionic CLI:
```sh
ionic serve
```

Haz lo mismo en la carpeta `PythonAdmin` si tienes un frontend para administradores.

---

## Notas

- Si usas blockchain, asegúrate de desplegar el contrato `SecurityContrat.sol` y configurar la dirección y ABI en el backend Python.
- Si necesitas reconocimiento facial, agrega `face_recognition` a `requirements.txt` y asegúrate de tener CMake instalado.
- El archivo `.env` **no debe subirse al repositorio**. Ya está en `.gitignore`.

---