#!/bin/bash

# Nome del container PHP
PHP_CONTAINER="asmsa_dog_php"

# Imposta i permessi corretti per le directory dei file
fix_permissions() {
    local base_dir=$1
    
    echo "Fixing permissions for $base_dir..."
    
    # Esegui i comandi nel container
    docker exec $PHP_CONTAINER mkdir -p "$base_dir"
    docker exec $PHP_CONTAINER chmod -R 777 "$base_dir"
    docker exec $PHP_CONTAINER find "$base_dir" -type f -exec chmod 666 {} \;
    
    echo "Permessi corretti applicati a: $base_dir"
}

# Directory da controllare
DIRS_TO_CHECK=(
    "/var/www/html/web/sites/default/files"
    "/var/www/html/web/sites/default/files/private"
    "/var/www/html/web/sites/default"
)

# Applica le correzioni a tutte le directory
for dir in "${DIRS_TO_CHECK[@]}"; do
    fix_permissions "$dir"
done
