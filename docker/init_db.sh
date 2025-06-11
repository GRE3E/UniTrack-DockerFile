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
    
    # Usar netcat para verificar si el puerto está abierto
    if command -v nc >/dev/null 2>&1; then
        if nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; then
            log "Conectividad de red establecida con $DB_HOST:$DB_PORT"
            return 0
        else
            log "No se puede establecer conectividad con $DB_HOST:$DB_PORT"
            return 1
        fi
    else
        log "netcat no disponible, continuando con verificación de PostgreSQL..."
        return 0
    fi
}

# Verificar conectividad primero
if ! check_connectivity; then
    log "ADVERTENCIA: Problemas de conectividad detectados"
fi

# Esperar a que PostgreSQL esté listo
log "Esperando a que PostgreSQL esté disponible..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    log "Intento $((attempt + 1))/$max_attempts de conexión a PostgreSQL..."
    
    # Usar variables de entorno para la conexión
    if PGPASSWORD="$DB_PASSWORD" pg_isready \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        -t 10; then
        log "PostgreSQL está disponible y listo para conexiones"
        break
    else
        log "PostgreSQL no está listo todavía. Esperando 10 segundos..."
        sleep 10
        attempt=$((attempt + 1))
    fi
done

if [ $attempt -eq $max_attempts ]; then
    log "ERROR: No se pudo conectar a PostgreSQL después de $max_attempts intentos"
    log "Verificando si es un problema de red o de autenticación..."
    
    # Intentar una conexión simple para diagnosticar el problema
    PGPASSWORD="$DB_PASSWORD" psql \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        -c "SELECT version();" 2>&1 | head -10
    
    # No salir con error para permitir que otros servicios continúen
    log "ADVERTENCIA: Continuando sin inicialización de BD. La aplicación podría fallar."
    exit 0
fi

# Verificar si el archivo SQL existe
SQL_FILE="/var/www/html/BD_PROYUSER.sql"
if [ ! -f "$SQL_FILE" ]; then
    log "ERROR: Archivo SQL no encontrado en $SQL_FILE"
    log "Contenido del directorio /var/www/html/:"
    ls -la /var/www/html/ || true
    exit 1
fi

log "Archivo SQL encontrado: $SQL_FILE"

# Ejecutar el script SQL
log "Ejecutando script de inicialización de base de datos..."
if PGPASSWORD="$DB_PASSWORD" psql \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    -f "$SQL_FILE" \
    -v ON_ERROR_STOP=1; then
    log "Script de base de datos ejecutado exitosamente"
else
    log "ERROR: Falló la ejecución del script de base de datos"
    log "Intentando conectar manualmente para diagnosticar..."
    
    # Diagnóstico adicional
    PGPASSWORD="$DB_PASSWORD" psql \
        -h "$DB_HOST" \
        -p "$DB_PORT" \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        -c "\dt" 2>&1 || true
    
    # No salir con error para permitir que la aplicación intente manejar el problema
    log "ADVERTENCIA: Error en inicialización de BD, pero continuando..."
    exit 0
fi

log "Inicialización de base de datos completada exitosamente"