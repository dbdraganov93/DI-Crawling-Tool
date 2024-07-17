#!/bin/bash

docker-compose -f ${DOCKER_COMPOSE_FILE} up wait-mysql-crawler
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps wait-mysql-crawler | grep -q "Exit 1"; then
    return 1
fi

docker-compose -f ${DOCKER_COMPOSE_FILE} up migrate-mysql-crawler
if docker-compose -f ${DOCKER_COMPOSE_FILE} ps migrate-mysql-crawler | grep -q "Exit 1"; then
    return 1
fi
