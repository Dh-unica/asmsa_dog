#!/bin/bash

# Show usage if no arguments provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <command>"
    echo "Commands:"
    echo "  ps        - Show running containers"
    echo "  up        - Start containers in development mode"
    echo "  stop      - Stop containers"
    echo "  restart   - Restart containers"
    echo "  down      - Stop and remove containers"
    echo "  pull      - Pull latest images"
    echo "  exec      - Execute bash in PHP container"
    exit 1
fi

comando=$1
if [ "$comando" = "ps" ]; then
    docker compose ps
fi

if [ "$comando" = "up" ]; then
    docker compose -f docker-compose.yml -f docker-compose-dev.yml up -d
fi

if [ "$comando" = "stop" ]; then
    docker compose stop
fi

if [ "$comando" = "restart" ]; then
    docker compose stop
    docker compose -f docker-compose.yml -f docker-compose-dev.yml up -d
fi

if [ "$comando" = "down" ]; then
    docker compose down
fi

if [ "$comando" = "pull" ]; then
    docker compose pull
fi

if [ "$comando" = "exec" ]; then
    docker compose exec -ti php /bin/bash
fi