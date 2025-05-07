#!/bin/bash

# Carica le variabili d'ambiente dal file .env.prod dalla root del progetto
source "$(dirname "$0")/../.env.prod"

# Configura sshpass se la password è impostata
if [ -n "$REMOTE_PASSWORD" ]; then
    RSYNC_CMD="sshpass -p $REMOTE_PASSWORD rsync"
else
    RSYNC_CMD="rsync"
fi

echo "Avvio sincronizzazione files incrementale..."

# Crea la directory di destinazione se non esiste
mkdir -p ./web/sites/default/files

# Sincronizza solo i file modificati dal server remoto
$RSYNC_CMD -vrahe ssh \
    --progress \
    --info=progress2 \
    --update \
    --partial \
    --omit-dir-times \
    --checksum \
    "$REMOTE_USER@$REMOTE_HOST:$REMOTE_WEBROOT/web/sites/default/files/" \
    "./web/sites/default/files/"

if [ $? -eq 0 ]; then
    echo "✅ Sincronizzazione incrementale completata con successo!"
else
    echo "❌ Errore durante la sincronizzazione"
    exit 1
fi