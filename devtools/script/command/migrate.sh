#!/bin/bash

# This script is used to migrate the current environment.

. ${PREPARE_COMMAND}

print_header "Migrate environment: ${ENVIRONMENT}"

export MIGRATE_ENVIRONMENT_FILE=${ENVIRONMENT_DIR}/migrate_environment.sh

if [ -f ${MIGRATE_ENVIRONMENT_FILE} ]
then
    echo "Migrate environment..."
	. ${MIGRATE_ENVIRONMENT_FILE}

	if [ ! $? -eq 0 ]
    then
        echo "Failed to migrate environment!"
        . ${STOP_COMMAND}
        exit 1
    fi
else
    echo "Skip migrate, because environment doesn't define file: '${MIGRATE_ENVIRONMENT_FILE}'."
fi