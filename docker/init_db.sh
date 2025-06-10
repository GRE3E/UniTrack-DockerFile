#!/bin/bash

# Wait for MySQL to be ready
until nc -z -v -w30 localhost 3306
do
  echo "Waiting for database connection..."
  sleep 1
done

# Execute SQL script
mysql -u root -p"root" BD_PROYUSER < /var/www/html/BD_PROYUSER.sql