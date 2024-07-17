#!/bin/bash

if ! docker push ${IMAGE}; then
    return 1
fi
