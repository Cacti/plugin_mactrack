#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
IMAGE="mactrack-tests:php83"

docker build --file "$SOURCE_DIR/tests/docker/Dockerfile" --tag "$IMAGE" "$SOURCE_DIR"
docker run --rm "$IMAGE"
