#!/bin/bash

# If available, this script is used to stop all containers
# from the docker-compose file in the environment directory
# and clean the environment.

. ${PREPARE_COMMAND}

print_header "Stop environment: ${ENVIRONMENT}"

if [ -f ${DOCKER_COMPOSE_FILE} ]
then
    echo "Stop environment..."

	docker-compose -f ${DOCKER_COMPOSE_FILE} stop
    docker-compose -f ${DOCKER_COMPOSE_FILE} rm -f -v
else
    echo "Skip stop, because environment doesn't define file: '${DOCKER_COMPOSE_FILE}'."
fi

export CLEAN_ENVIRONMENT_FILE=${ENVIRONMENT_DIR}/clean_environment.sh

if [ -f ${CLEAN_ENVIRONMENT_FILE} ]
then
    echo "Clean environment..."
	. ${CLEAN_ENVIRONMENT_FILE}

	if [ ! $? -eq 0 ]
    then
        echo "Failed to clean environment!"
        exit 1
    fi
else
    echo "Skip clean, because environment doesn't define file: '${CLEAN_ENVIRONMENT_FILE}'."
fi