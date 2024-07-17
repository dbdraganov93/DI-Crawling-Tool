#!/bin/bash

docker-compose -f ${DOCKER_COMPOSE_FILE} up -d mysql-crawler
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps mysql-crawler | grep -q "Exit 1"; then
    return 1
fi

docker-compose -f ${DOCKER_COMPOSE_FILE} up -d mysql-php-gui
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps mysql-php-gui | grep -q "Exit 1"; then
    return 1
fi
