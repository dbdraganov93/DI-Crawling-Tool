#!/bin/bash

docker-compose -f ${DOCKER_COMPOSE_FILE} up -d wildfly-crawler
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps wildfly-crawler | grep -q "Exit 1"; then
    return 1
fi

docker-compose -f ${DOCKER_COMPOSE_FILE} up -d apache-di-gui
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps apache-di-gui | grep -q "Exit 1"; then
    return 1
fi

docker-compose -f ${DOCKER_COMPOSE_FILE} up wait-wildfly
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps wait-wildfly | grep -q "Exit 1"; then
    return 1
fi
