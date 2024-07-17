#!/bin/bash

# This script is used to prepare, optional pull and
# start the specific environment from the specified
# docker-compose file.

. ${PREPARE_COMMAND}

print_header "Start environment: ${ENVIRONMENT}"

. ${STOP_COMMAND}

if [ "${OPTION_2}" != "--skip_pull" ] && [ "${OPTION_3}" != "--skip_pull" ]
then
    ${DEVTOOLS_DIR}/devtools.sh pull
    if [ ! $? -eq 0 ]
    then
        exit 1
    fi
fi

if [ "${OPTION_2}" = "--build" ] || [ "${OPTION_3}" = "--build" ]
then
    ${DEVTOOLS_DIR}/devtools.sh build
    if [ ! $? -eq 0 ]
    then
        exit 1
    fi
fi

export PREPARE_ENVIRONMENT_FILE=${ENVIRONMENT_DIR}/prepare_environment.sh

if [ -f ${PREPARE_ENVIRONMENT_FILE} ]
then
    echo "Prepare environment..."
	. ${PREPARE_ENVIRONMENT_FILE}

	if [ ! $? -eq 0 ]
    then
        echo "Failed to prepare environment!"
        . ${STOP_COMMAND}
        exit 1
    fi
else
    echo "Skip prepare, because environment doesn't define file: '${PREPARE_ENVIRONMENT_FILE}'."
fi

. ${DEVTOOLS_DIR}/devtools.sh migrate ${ENVIRONMENT}
if [ ! $? -eq 0 ]
then
    exit 1
fi

export START_ENVIRONMENT_FILE=${ENVIRONMENT_DIR}/start_environment.sh

if [ -f ${START_ENVIRONMENT_FILE} ]
then
    echo "Start environment..."
	. ${START_ENVIRONMENT_FILE}

	if [ ! $? -eq 0 ]
    then
        echo "Failed to start environment!"
        . ${STOP_COMMAND}
        exit 1
    fi
else
    echo "Skip start, because environment doesn't define file: '${START_ENVIRONMENT_FILE}'."
fi