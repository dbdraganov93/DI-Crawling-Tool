#!/bin/bash

# This script ist called from various other scripts
# in order to do preparations for environment commands.

if [ -z "${PREPARED}" ]
then
    export ENVIRONMENT=${OPTION_1}

    if [ -z "${ENVIRONMENT}" ]
    then
        echo "No environment specified! (Example: devtools.sh [start|...] [dev|...])"
        exit 1
    fi

    export ENVIRONMENT_DIR=${DEVTOOLS_DIR}/environment/${ENVIRONMENT}

    if [ ! -d ${ENVIRONMENT_DIR} ]
    then
        echo "The environment: '${ENVIRONMENT}' with path: '${ENVIRONMENT_DIR}' is unknown!"
        exit 1
    fi

    export DOCKER_COMPOSE_FILE=${ENVIRONMENT_DIR}/docker-compose.yml

    export PREPARED=true
fi


