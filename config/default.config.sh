#!/bin/bash
# Default config. To overwrite values, create a file local.config.yml in this folder.
# Creating /etc/fdcs/config will also work and has the highest priority.

# Web root of Froxlor
FROXLOR_DIR="/var/www/froxlor"

# Directory with the Froxlor web/temp/mail directories
FROXLOR_CUSTOMER_DIR="/var/customers"

# Docker image to be used
DOCKER_IMAGE="mmunz/docker-customer-chroot"

# Max Number of cores the container can use
CONTAINER_MAX_CPU="1"

# Max RAM allowed to use by the container (-m option in docker)
CONTAINER_MAX_MEMORY="512m"

# Ulimits options for the container
CONTAINER_ULIMIT="--ulimit nproc=128:256"
