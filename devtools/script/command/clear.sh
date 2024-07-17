#!/bin/bash

# This script is used to stop and remove all docker containers.

print_header "Clear docker containers and unused images"

# Stop all containers
running=$(docker ps -a -q)
if [ -n $"running" ]; then
    echo $running | xargs docker stop
fi

# Delete all stopped containers
container=$(docker ps -q -f status=exited)
if [ -n "$container" ]; then
    echo $container | xargs docker rm
fi

# Delete all dangling (unused) images
images=$(docker images -q -f dangling=true)
if [ -n $"images" ]; then
    echo $images | xargs docker rmi
fi
