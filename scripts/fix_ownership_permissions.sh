#!/bin/bash

# Directory da sistemare
DRUPAL_ROOT="/var/www/html"
FILES_DIR="$DRUPAL_ROOT/web/sites/default/files"
NGINX_USER="www-data"  # Cambiamo a www-data che è lo standard
NGINX_GROUP="www-data"

echo "=== Correzione permessi e proprietà dei file ==="

# 1. Cambia proprietario delle directory
echo "Cambio proprietario a $NGINX_USER:$NGINX_GROUP..."
chown -R $NGINX_USER:$NGINX_GROUP "$FILES_DIR"

# 2. Imposta i permessi corretti
echo "Imposto i permessi delle directory..."
find "$FILES_DIR" -type d -exec chmod 755 {} \;
echo "Imposto i permessi dei file..."
find "$FILES_DIR" -type f -exec chmod 644 {} \;

# 3. Assicurati che la directory files sia scrivibile
chmod 775 "$FILES_DIR"
chmod 775 "$FILES_DIR/private"

echo "=== Operazioni completate ==="
echo "Per verificare, esegui: ls -la $FILES_DIR"
