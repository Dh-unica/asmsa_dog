#!/bin/bash

# Imposta i permessi corretti per le directory dei file
fix_permissions() {
    local base_dir=$1
    
    # Assicurati che la directory base esista e abbia i permessi corretti
    mkdir -p "$base_dir"
    chmod 777 "$base_dir"
    
    # Crea la directory per il mese corrente se non esiste
    current_dir="$base_dir/$(date +%Y-%m)"
    mkdir -p "$current_dir"
    chmod 777 "$current_dir"
    
    # Correggi i permessi di tutte le sottodirectory
    find "$base_dir" -type d -exec chmod 777 {} \;
    find "$base_dir" -type f -exec chmod 666 {} \;
    
    echo "Permessi corretti applicati a: $base_dir"
}

# Directory da controllare
DIRS_TO_CHECK=(
    "/var/www/html/web/sites/default/files"
    "/var/www/html/web/sites/default/files/private"
)

# Applica le correzioni a tutte le directory
for dir in "${DIRS_TO_CHECK[@]}"; do
    fix_permissions "$dir"
done
