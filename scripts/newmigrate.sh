#!/bin/bash

# Sincronizza i file (decommenta se necessario)
# echo "Sincronizzazione dei file in corso..."
# rsync -vrahe ssh dhgeo@90.147.144.148:/home/dhgeo/archiviostorico2/web/sites/default/files/*  ~/docker/archivio/web/sites/default/files
# echo "Sincronizzazione completata."


rsync -vrahe ssh ubuntu@51.77.137.57:/home/ubuntu/asel/web/sites/default/files/*  ~/docker/ales_new/web/sites/default/files

rsync -vrahe ssh ~/docker/asel_new/web/sites/default/files/* ubuntu@51.77.137.57:/home/ubuntu/asel/web/sites/default/files


echo "Arresto dei servizi Docker..."
docker compose down && echo "Servizi Docker arrestati con successo." || { echo "Errore durante l'arresto dei servizi Docker."; exit 1; }

echo "Avvio dei servizi Docker..."
docker compose up -d && echo "Servizi Docker avviati con successo." || { echo "Errore durante l'avvio dei servizi Docker."; exit 1; }

echo "Attesa di 10 secondi..."
sleep 10

echo "Aggiornamento del database Drupal..."
drush updb -y && echo "Database aggiornato con successo." || { echo "Errore durante l'aggiornamento del database."; exit 1; }

echo "Stai lì per 10 secondi..."
sleep 10

echo "Importazione delle configurazioni..."
drush cim -y && echo "Configurazioni importate con successo." || { echo "Errore durante l'importazione delle configurazioni."; exit 1; }

echo "Ok ci siamo 1 2 3....."
sleep 3

echo "Ricostruzione della cache..."
drush cr && echo "Cache ricostruita con successo." || { echo "Errore durante la ricostruzione della cache."; exit 1; }

# echo "Pulizia dell'indice di ricerca..."
# drush search-api-clear && echo "Indice di ricerca pulito con successo." || { echo "Errore durante la pulizia dell'indice di ricerca."; exit 1; }

# echo "Reindicizzazione della ricerca..."
# drush search-api-index && echo "Reindicizzazione completata con successo." || { echo "Errore durante la reindicizzazione."; exit 1; }

# echo "Aggiornamento della password dell'utente admin..."
# drush upwd admin admin && echo "Password aggiornata con successo." || { echo "Errore durante l'aggiornamento della password."; exit 1; }

echo "Abilitazione del modulo 'migrando'..."
drush en migrando && echo "Modulo 'migrando' abilitato con successo." || { echo "Errore durante l'abilitazione del modulo 'migrando'."; exit 1; }

echo "Stiamo andando alla grande..."
sleep 3

# Lista delle migrazioni
migrations=(
    "utenti"
    "termini_4"
    "termini_5"
    "allegati"
    "immagini"
    "media_allegati"
    "media_immagini"
    "articoli"
    "comunicati_stampa"
    "documentazione"
    "documenti_area_riservata_soci"
    "formazione"
    "pubblicazioni"
    "pubblicazioni_area_appalti"
    "pubblicazioni_area_finanziaria"
    "quesiti"
    "quesiti_area_appalti"
    "seminari"
    "strumenti_di_lavoro"
)

for migration in "${migrations[@]}"
do
    echo "Importazione della migrazione ${migration}..."
    drush mim "${migration}" && echo "Migrazione ${migration} completata con successo." || { echo "Errore durante la migrazione ${migration}."; exit 1; }
    echo "...e questo è andato...."
    sleep 40
done

echo "Script completato con successo."




