#!/bin/bash

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}=== Diagnostica Accesso File Drupal ===${NC}\n"

# Directory da controllare
DRUPAL_ROOT="/var/www/html"
FILES_DIR="$DRUPAL_ROOT/web/sites/default/files"
PRIVATE_DIR="$FILES_DIR/private"
SETTINGS_FILE="$DRUPAL_ROOT/web/sites/default/settings.php"

# 1. Verifica esistenza directory
check_directory() {
    local dir=$1
    echo -e "\n${YELLOW}Controllo directory: $dir${NC}"
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓ Directory esiste${NC}"
        echo -e "\nPermessi e proprietà:"
        ls -la "$dir"
    else
        echo -e "${RED}✗ Directory non trovata${NC}"
    fi
}

# 2. Verifica permessi e proprietà
check_permissions() {
    local path=$1
    echo -e "\n${YELLOW}Analisi permessi per: $path${NC}"
    
    # Verifica proprietario
    owner=$(stat -c '%U:%G' "$path")
    echo "Proprietario: $owner"
    
    # Verifica permessi numerici
    perms=$(stat -c '%a' "$path")
    echo "Permessi: $perms"
    
    # Verifica se www-data ha accesso
    if groups www-data | grep -q "\b$(stat -c '%G' "$path")\b"; then
        echo -e "${GREEN}✓ www-data ha accesso tramite gruppo${NC}"
    else
        echo -e "${RED}✗ www-data potrebbe non avere accesso${NC}"
    fi
}

# 3. Verifica configurazione Drupal
check_drupal_config() {
    echo -e "\n${YELLOW}Verifica configurazione Drupal${NC}"
    
    # Controlla settings.php
    if [ -f "$SETTINGS_FILE" ]; then
        echo -e "${GREEN}✓ settings.php trovato${NC}"
        echo "Cercando configurazione file privati..."
        grep -n "private" "$SETTINGS_FILE"
    else
        echo -e "${RED}✗ settings.php non trovato${NC}"
    fi
}

# 4. Verifica processo web server
check_webserver() {
    echo -e "\n${YELLOW}Informazioni Web Server${NC}"
    
    # Controlla se Apache o Nginx
    if pgrep apache2 > /dev/null; then
        echo "Apache2 in esecuzione"
        ps aux | grep apache2 | grep -v grep
        apache2_user=$(ps -ef | grep apache2 | grep -v root | head -n1 | awk '{print $1}')
        echo "Apache2 esegue come: $apache2_user"
    elif pgrep nginx > /dev/null; then
        echo "Nginx in esecuzione"
        ps aux | grep nginx | grep -v grep
        nginx_user=$(ps -ef | grep nginx | grep -v root | head -n1 | awk '{print $1}')
        echo "Nginx esegue come: $nginx_user"
    else
        echo -e "${RED}✗ Nessun web server (Apache2/Nginx) trovato in esecuzione${NC}"
    fi
}

# 5. Verifica log recenti
check_logs() {
    echo -e "\n${YELLOW}Ultimi log rilevanti${NC}"
    
    # Apache/Nginx error log
    if [ -f "/var/log/apache2/error.log" ]; then
        echo -e "\nUltimi errori Apache2:"
        tail -n 10 /var/log/apache2/error.log
    elif [ -f "/var/log/nginx/error.log" ]; then
        echo -e "\nUltimi errori Nginx:"
        tail -n 10 /var/log/nginx/error.log
    fi
    
    # Drupal log
    if [ -f "$DRUPAL_ROOT/web/sites/default/files/logs/drupal.log" ]; then
        echo -e "\nUltimi log Drupal:"
        tail -n 10 "$DRUPAL_ROOT/web/sites/default/files/logs/drupal.log"
    fi
}

# Esegui tutti i controlli
main() {
    # Header con info sistema
    echo "Data: $(date)"
    echo "Server: $(hostname)"
    echo "Sistema: $(uname -a)"
    
    # Esegui controlli
    check_directory "$FILES_DIR"
    check_directory "$PRIVATE_DIR"
    check_permissions "$FILES_DIR"
    check_permissions "$PRIVATE_DIR"
    check_drupal_config
    check_webserver
    check_logs
    
    echo -e "\n${YELLOW}=== Diagnostica Completata ===${NC}"
}

main
