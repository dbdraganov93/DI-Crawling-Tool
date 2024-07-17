#!/bin/bash

# If available, this script is used to build and push all images.
# The image names and tags are determined by directory structure and names.

print_header "Build images"

export BUILD_DIR=${DEVTOOLS_DIR}/build

IMAGE_NAMES=$(find ${BUILD_DIR}/ -maxdepth 1 -mindepth 1 -type d -exec basename {} \; )
for IMAGE_NAME in ${IMAGE_NAMES}
do
  IMAGE_TAGS=$(find ${BUILD_DIR}/${IMAGE_NAME}/ -maxdepth 1 -mindepth 1 -type d -exec basename {} \; )
  for IMAGE_TAG in ${IMAGE_TAGS}
  do
    export IMAGE_CONTAINER=${IMAGE_NAME}-${IMAGE_TAG}
    export IMAGE_DIR=${BUILD_DIR}/${IMAGE_NAME}/${IMAGE_TAG}
    export IMAGE=${REGISTRY_PROJECT}/${IMAGE_NAME}:${IMAGE_TAG}

    echo ""
    echo "Build image: '${IMAGE}'"
    echo ""

    export DOCKER_COMPOSE_FILE=${IMAGE_DIR}/docker-compose.yml
    if [ -f ${DOCKER_COMPOSE_FILE} ]
    then
        echo "Stop and clean containers before build..."

        docker-compose -f ${DOCKER_COMPOSE_FILE} stop
        docker-compose -f ${DOCKER_COMPOSE_FILE} rm -f -v
    else
        echo "Skip stop and clean before build, because image doesn't define file: '${DOCKER_COMPOSE_FILE}'."
    fi

    if [ -f ${DOCKER_COMPOSE_FILE} ]
    then
        echo "Pull..."
        if ! docker-compose -f ${DOCKER_COMPOSE_FILE} pull --ignore-pull-failures; then
            echo "Pull failed!"
            exit 1
        fi
    else
        echo "Skip pull, because image doesn't define file: '${DOCKER_COMPOSE_FILE}'."
    fi

    export BEFORE_BUILD_FILE=${IMAGE_DIR}/before_build.sh
    if [ -f ${BEFORE_BUILD_FILE} ]
    then
        echo "Before build..."
        . ${BEFORE_BUILD_FILE}

        if [ ! $? -eq 0 ]
        then
            echo "Failed before build!"
            exit 1
        fi
    else
        echo "Skip before build, because image doesn't define file: '${BEFORE_BUILD_FILE}'."
    fi

    if [ -f ${DOCKER_COMPOSE_FILE} ]
    then
        echo "Build..."
        if ! docker-compose -f ${DOCKER_COMPOSE_FILE} build --force-rm --pull; then
            echo "Build failed!"
            exit 1
        fi
    else
        echo "Skip build, because image doesn't define file: '${DOCKER_COMPOSE_FILE}'."
    fi

    export AFTER_BUILD_FILE=${IMAGE_DIR}/after_build.sh
    if [ -f ${AFTER_BUILD_FILE} ]
    then
        echo "After build..."
        . ${AFTER_BUILD_FILE}

        if [ ! $? -eq 0 ]
        then
            echo "Failed after build!"
            exit 1
        fi
    else
        echo "Skip after build, because image doesn't define file: '${AFTER_BUILD_FILE}'."
    fi

    if [ -f ${DOCKER_COMPOSE_FILE} ]
    then
        echo "Stop and clean containers after build..."

        docker-compose -f ${DOCKER_COMPOSE_FILE} stop
        docker-compose -f ${DOCKER_COMPOSE_FILE} rm -f -v
    else
        echo "Skip stop and clean containers after build, because image doesn't define file: '${DOCKER_COMPOSE_FILE}'."
    fi

    echo "Delete exited containers and unused images..."

    container=$(docker ps -q -f status=exited)
    if [ -n "$container" ]; then
        echo $container | xargs docker rm
    fi

    images=$(docker images -q -f dangling=true)
    if [ -n $"images" ]; then
        echo $images | xargs docker rmi
    fi

    if [ "${OPTION_1}" = "--push" ]
    then
        export PUSH_BUILD_FILE=${IMAGE_DIR}/push_build.sh
        if [ -f ${PUSH_BUILD_FILE} ]
        then
            echo "Push build..."
            . ${PUSH_BUILD_FILE}

            if [ ! $? -eq 0 ]
            then
                echo "Failed push build!"
                exit 1
            fi
        else
            echo "Skip push build, because image doesn't define file: '${PUSH_BUILD_FILE}'."
        fi
    fi
  done
done