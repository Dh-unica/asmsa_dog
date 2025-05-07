#!/bin/bash

# Carica le variabili d'ambiente
source "$(dirname "$0")/../.env.prod"

# Configura questi parametri
DUMP_NAME="db_backup_$(date +%F_%H%M)"    # Nome del file di dump con data e ora
LOCAL_BACKUP_DIR="mariadb-init"          # Cartella locale dove salvare il dump
REMOTE_TEMP_DIR="$REMOTE_WEBROOT/web/dump-tmp"  # Directory temporanea per il dump
REMOTE_DUMP_PATH="$REMOTE_TEMP_DIR/$DUMP_NAME.sql.gz"  # Percorso completo del dump remoto

# Configura sshpass se la password è impostata
if [ -n "$REMOTE_PASSWORD" ]; then
    SSH_CMD="sshpass -p $REMOTE_PASSWORD ssh"
    SCP_CMD="sshpass -p $REMOTE_PASSWORD scp"
else
    SSH_CMD="ssh"
    SCP_CMD="scp"
fi

echo " Avvio del backup del database..."

# 1 Esegui il dump del DB sul server remoto
$SSH_CMD "$REMOTE_USER@$REMOTE_HOST" "mkdir -p $REMOTE_TEMP_DIR && cd $REMOTE_WEBROOT && docker exec asel_php bash -c 'mkdir -p /var/www/html/web/dump-tmp && drush sql-dump --gzip --result-file=/var/www/html/web/dump-tmp/$DUMP_NAME.sql'"

# 2 Scarica il dump in locale
$SCP_CMD "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DUMP_PATH" "$LOCAL_BACKUP_DIR/$DUMP_NAME.sql.gz"

# 3 Verifica il trasferimento
if [ -f "$LOCAL_BACKUP_DIR/$DUMP_NAME.sql.gz" ]; then
    echo " Backup scaricato con successo in $LOCAL_BACKUP_DIR/$DUMP_NAME.sql.gz"
    
    # 4 Decomprimi il file
    echo " Decompressione del dump..."
    gunzip -f "$LOCAL_BACKUP_DIR/$DUMP_NAME.sql.gz"
    
    if [ -f "$LOCAL_BACKUP_DIR/$DUMP_NAME.sql" ]; then
        echo " Dump decompresso con successo in $LOCAL_BACKUP_DIR/$DUMP_NAME.sql"
    else
        echo " Errore nella decompressione del dump!"
        exit 1
    fi
    
    # Rimuovi la directory temporanea remota
    $SSH_CMD "$REMOTE_USER@$REMOTE_HOST" "rm -rf $REMOTE_TEMP_DIR"
    echo "  Directory temporanea remota rimossa"
else
    echo " Errore nel trasferimento del backup!"
    # Pulisci comunque la directory temporanea in caso di errore
    $SSH_CMD "$REMOTE_USER@$REMOTE_HOST" "rm -rf $REMOTE_TEMP_DIR"
    exit 1
fi

echo " Backup completato!"
