#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

echo "Starting Drupal update process..."

# Run composer update inside the php container
echo "Updating Drupal core and modules with Composer..."
docker compose exec -T php composer update "drupal/*" --with-all-dependencies

# Run database updates inside the php container
echo "Running database updates (drush updatedb)..."
docker compose exec -T php drush updatedb -y

# Rebuild cache inside the php container
echo "Rebuilding cache (drush cache:rebuild)..."
docker compose exec -T php drush cache:rebuild

echo "Drupal update process completed successfully."
