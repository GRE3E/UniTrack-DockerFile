#!/bin/bash

# Función para log con timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log "Iniciando script de inicialización de base de datos..."

# Variables de entorno para la conexión
DB_HOST=${PG_HOST:-"dpg-d14br76uk2gs73and8u0-a.oregon-postgres.render.com"}
DB_PORT=${PG_PORT:-"5432"}
DB_USER=${PG_USER:-"proyuser_user"}
DB_NAME=${PG_DATABASE:-"proyuser"}
DB_PASSWORD=${PG_PASSWORD:-"1Pi94v788RMiCObSqGYuPZVVwv8pv6em"}

log "Configuración de conexión:"
log "Host: $DB_HOST"
log "Puerto: $DB_PORT"
log "Usuario: $DB_USER"
log "Base de datos: $DB_NAME"

# Función para verificar conectividad
check_connectivity() {
    log "Verificando conectividad con el servidor de base de datos..."
    
    # Usar timeout para evitar esperas indefinidas
    if timeout 10 bash -c "</dev/tcp/$DB_HOST/$DB_PORT" 2>/dev/null; then
        log "Conectividad de red establecida con $DB_HOST:$DB_PORT"
        return 0
    else
        log "No se puede establecer conectividad con $DB_HOST:$DB_PORT"
        return 1
    fi
}

# Verificar conectividad primero
if ! check_connectivity; then
    log "ERROR: No se puede establecer conectividad de red"
    log "Continuando sin inicialización de BD para permitir que otros servicios funcionen"
    exit 0
fi

# Esperar a que PostgreSQL esté listo con timeout más corto
log "Esperando a que PostgreSQL esté disponible..."
max_attempts=10
attempt=0

while [ $attempt -lt $max_attempts ]; do
    log "Intento $((attempt + 1))/$max_attempts de conexión a PostgreSQL..."
    
    # Usar timeout para evitar bloqueos
    if timeout 15 bash -c "PGPASSWORD='$DB_PASSWORD' pg_isready -h '$DB_HOST' -p '$DB_PORT' -U '$DB_USER' -d '$DB_NAME'" 2>/dev/null; then
        log "PostgreSQL está disponible y listo para conexiones"
        break
    else
        log "PostgreSQL no está listo todavía. Esperando 5 segundos..."
        sleep 5
        attempt=$((attempt + 1))
    fi
done

if [ $attempt -eq $max_attempts ]; then
    log "ADVERTENCIA: No se pudo verificar PostgreSQL después de $max_attempts intentos"
    log "Continuando sin inicialización para permitir que la aplicación maneje la conexión"
    exit 0
fi

# Verificar si el archivo SQL existe
SQL_FILE="/var/www/html/BD_PROYUSER.sql"
if [ ! -f "$SQL_FILE" ]; then
    log "ADVERTENCIA: Archivo SQL no encontrado en $SQL_FILE"
    log "Continuando sin inicialización de esquema"
    exit 0
fi

log "Archivo SQL encontrado: $SQL_FILE"

# Ejecutar el script SQL con timeout
log "Ejecutando script de inicialización de base de datos..."
if timeout 60 bash -c "PGPASSWORD='$DB_PASSWORD' psql -h '$DB_HOST' -p '$DB_PORT' -U '$DB_USER' -d '$DB_NAME' -f '$SQL_FILE' -v ON_ERROR_STOP=1" 2>/dev/null; then
    log "Script de base de datos ejecutado exitosamente"
else
    log "ADVERTENCIA: Error en la ejecución del script de base de datos"
    log "La aplicación intentará crear las tablas necesarias automáticamente"
fi

log "Inicialización de base de datos completada"
exit 0