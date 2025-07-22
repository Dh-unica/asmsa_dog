#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Starting Themes update process..."

# Ensure npm is installed in the php container (if needed)
docker compose exec -T php sudo apk update
docker compose exec -T php sudo apk add npm

# Run npm build for the custom theme
docker compose exec -T php sh -c "cd web/themes/custom/italiagov && npm install && npm run build:dev"

# Run clear cache inside the php container
echo "Rebuilding cache (drush cache:rebuild)..."
docker compose exec -T php drush cache:rebuild      




