#!/bin/bash

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Funzione per logging
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}" >&2
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

# Funzione per controllare l'esito dei comandi
check_status() {
    if [ $? -eq 0 ]; then
        log "$1 completato con successo"
    else
        error "$1 fallito"
        exit 1
    fi
}

# Directory del progetto
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR" || exit 1

# Carica le variabili d'ambiente dal file .env.prod
if [ ! -f ".env.prod" ]; then
    error "File .env.prod non trovato"
    exit 1
fi


# Carica le variabili dal file .env.prod
source .env.prod

# Controllo connessione VPN tramite ping al REMOTE_HOST
log "Verifico la connessione al server remoto ($REMOTE_HOST)..."
ping -c 2 -W 2 "$REMOTE_HOST" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    error "Impossibile raggiungere $REMOTE_HOST. Probabilmente la VPN non è attiva. Connetti la VPN e riprova."
    exit 1
fi

if [ -z "$REMOTE_HOST" ] || [ -z "$REMOTE_USER" ] || [ -z "$REMOTE_WEBROOT" ] || [ -z "$REMOTE_PASSWORD" ] || [ -z "$CONTAINER_NAME" ]; then
    error "Mancano alcune variabili necessarie nel file .env.prod"
    exit 1
fi

# Verifica che siamo in un repository git
if [ ! -d .git ]; then
    error "Non sei in un repository Git"
    exit 1
fi

# 1. Backup del database (opzionale)
log "Eseguo backup del database remoto..."
if [ -f scripts/backup-remote.sh ]; then
    bash scripts/backup-remote.sh
    check_status "Backup del database"
else
    warning "Script di backup non trovato, proseguo senza backup"
fi

# 2. Git pull sul server remoto
log "Aggiorno il codice sul server remoto..."
PULL_OUTPUT=$(sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && git pull")
echo -e "${GREEN}Output git pull:${NC}\n$PULL_OUTPUT"
if [ $? -eq 0 ]; then
    log "Git pull completato con successo"
else
    error "Git pull fallito"
    exit 1
fi

# 3. Composer install sul server remoto
log "Installo le dipendenze con Composer sul server remoto..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME composer install --no-dev --optimize-autoloader"
check_status "Composer install"

# 4. Attivo modalità manutenzione
log "Attivo la modalità manutenzione..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush sset system.maintenance_mode 1"
check_status "Attivazione modalità manutenzione"

# 5. Database updates
log "Eseguo gli aggiornamenti del database..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush updatedb --yes"
check_status "Database updates"

# 6. Configuration import
log "Verifico i cambiamenti delle configurazioni..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush config:status"
read -p "Vuoi procedere con l'importazione delle configurazioni? [y/N] " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]
then
    log "Importo le configurazioni..."
    sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush config:import -y"
    check_status "Configuration import"
else
    error "Importazione configurazioni annullata dall'utente"
    exit 1
fi

# 7. Clear all caches
log "Pulisco tutte le cache..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush cache:rebuild"
check_status "Cache rebuild"

# 8. Disattivo modalità manutenzione
log "Disattivo la modalità manutenzione..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush sset system.maintenance_mode 0"
check_status "Disattivazione modalità manutenzione"

# 9. Status check
log "Verifico lo stato del sito..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush status"
check_status "Status check"

# 10. Verifica errori nel log
log "Verifico gli ultimi errori nel log..."
sshpass -p "$REMOTE_PASSWORD" ssh -o StrictHostKeyChecking=no "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_WEBROOT && docker exec -t $CONTAINER_NAME vendor/bin/drush watchdog:show --severity=error --count=10"
if [ $? -eq 0 ]; then
    warning "Controlla eventuali errori nel log qui sopra"
fi

log "Deployment remoto completato con successo!"