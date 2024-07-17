#!/bin/bash

export SCRIPT_DIR=`dirname $0`;
export DEVTOOLS_DIR=${SCRIPT_DIR}

export REGISTRY_URL=docker-registry.marktjagd.de
export REGISTRY_PROJECT=${REGISTRY_URL}/docker

export COMMAND=$1

export OPTION_1=$2
export OPTION_2=$3
export OPTION_3=$4

if [ -z "${COMMAND}" ]
then
	echo "No command specified! (Example: devtools.sh [start|...] [dev|...])"
	exit 1
fi

export COMMAND_DIR=${DEVTOOLS_DIR}/script/command
export COMMAND_FILE=${COMMAND_DIR}/${COMMAND}.sh

if [ ! -f ${COMMAND_FILE} ]
then
	echo "The command: '${COMMAND}' with path: '${COMMAND_FILE}' is unknown!"
	exit 1
fi

# Available command files
export PREPARE_COMMAND=${COMMAND_DIR}/prepare.sh
export STOP_COMMAND=${COMMAND_DIR}/stop.sh
export BUILD_COMMAND=${COMMAND_DIR}/build.sh
export START_COMMAND=${COMMAND_DIR}/start.sh
export MIGRATE_COMMAND=${COMMAND_DIR}/migrate.sh
export PREPARE_COMMAND=${COMMAND_DIR}/prepare.sh
export PUSH_COMMAND=${COMMAND_DIR}/push.sh
export UPDATE_COMMAND=${COMMAND_DIR}/update.sh

print_header () {
    if [ -z "${HEADER_PRINTED}" ]
    then
        echo ""
        echo "-------------------------------------"
        echo ""
        echo "$1"
        echo ""
        echo "-------------------------------------"
        echo ""

        export HEADER_PRINTED=true
    fi
}

. ${COMMAND_FILE}

echo "Tasks finished!"