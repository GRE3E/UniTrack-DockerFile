#!/bin/bash

# Wait for PostgreSQL to be ready
until pg_isready -h localhost -p 5432 -U proyuser_user
do
  echo "Waiting for database connection..."
  sleep 1
done

# Execute SQL script
PGPASSWORD="1Pi94v788RMiCObSqGYuPZVVwv8pv6em" psql -h localhost -p 5432 -U proyuser_user -d proyuser -f /var/www/html/BD_PROYUSER.sql