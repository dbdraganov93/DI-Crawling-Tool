#!/bin/bash

# This script is used to refresh the docker
# images from the current environment.

. ${PREPARE_COMMAND}

print_header "Pull environment: ${ENVIRONMENT}"

if [ -f ${DOCKER_COMPOSE_FILE} ]
then
    echo "Pull environment..."
    if ! docker-compose -f ${DOCKER_COMPOSE_FILE} pull --ignore-pull-failures; then
        echo "Failed to pull environment!"
        exit 1
    fi
else
    echo "Skip pull, because environment doesn't define file: '${DOCKER_COMPOSE_FILE}' ."
fi