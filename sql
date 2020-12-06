#!/bin/sh

docker-compose run platform vendor/bin/doctrine dbal:run-sql "$@"
