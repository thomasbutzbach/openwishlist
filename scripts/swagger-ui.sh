#!/usr/bin/env bash
# Startet Swagger UI für die lokale OpenAPI-Spec
# Nutzung: ./scripts/swagger-ui.sh

# Port kann optional als Argument übergeben werden (Default 8081)
PORT=${1:-8081}

docker run --rm -p ${PORT}:8080 \
  -e SWAGGER_JSON=/openapi.yml \
  -v "$(pwd)/api/openapi.yml":/openapi.yml \
  swaggerapi/swagger-ui
