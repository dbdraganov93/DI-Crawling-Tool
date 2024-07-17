#!/bin/bash

print_header "Update Devtools..."

docker pull docker-registry.marktjagd.de/docker/devtools:dev
CONTAINER_ID=$(docker create docker-registry.marktjagd.de/docker/devtools:dev)
docker cp ${CONTAINER_ID}:/devtools ../${DEVTOOLS_DIR}/tmp
docker rm -v ${CONTAINER_ID}
cp -r ../${DEVTOOLS_DIR}/tmp/* ${DEVTOOLS_DIR}/
rm -r ../${DEVTOOLS_DIR}/tmp