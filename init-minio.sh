#!/bin/bash
# Script to initialize MinIO bucket
# This should be run after MinIO container is up

echo "Waiting for MinIO to be ready..."
sleep 5

# Create bucket using MinIO client (mc)
docker-compose exec -T minio mc alias set myminio http://localhost:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-minioadmin}
docker-compose exec -T minio mc mb myminio/ailitreview --ignore-existing
docker-compose exec -T minio mc anonymous set download myminio/ailitreview

echo "MinIO bucket 'ailitreview' created and configured!"
