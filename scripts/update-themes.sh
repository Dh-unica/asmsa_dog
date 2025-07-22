#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Starting Themes update process..."


# Ensure npm is available, try install only if missing
if ! docker compose exec -T php sh -c "command -v npm"; then
    echo "npm non trovato, installo npm nel container php..."
    docker compose exec -u root -T php apk update
    docker compose exec -u root -T php apk add npm
fi

# Installa dipendenze e builda il tema custom
echo "Installazione dipendenze npm e build tema..."
docker compose exec -T php sh -c "cd web/themes/custom/italiagov && npm install && npm run build:dev"

# Ricostruisci la cache Drupal
echo "Ricostruisco la cache (drush cache:rebuild)..."
docker compose exec -T php drush cache:rebuild




