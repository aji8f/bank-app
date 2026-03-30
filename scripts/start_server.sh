#!/bin/bash
# start_server.sh - Starts the application server
# Executed during CodeDeploy ApplicationStart lifecycle event

set -e

echo "=== Start Server Script ==="
echo "Timestamp: $(date)"

# Fetch secrets from Parameter Store
APP_KEY=$(aws ssm get-parameter --name "/simple-banking/app-key" --with-decryption --query "Parameter.Value" --output text)
DB_PASSWORD=$(aws ssm get-parameter --name "/simple-banking/db-password" --with-decryption --query "Parameter.Value" --output text)
RDS_ENDPOINT=$(aws ssm get-parameter --name "/simple-banking/rds-endpoint" --query "Parameter.Value" --output text)

# Start the Docker container
echo "Starting Docker container..."
docker run -d \
    --name simple-banking-app \
    --restart unless-stopped \
    -p 80:80 \
    --env APP_ENV=production \
    --env APP_DEBUG=false \
    --env APP_KEY="${APP_KEY}" \
    --env DB_CONNECTION=mysql \
    --env DB_HOST="${RDS_ENDPOINT}" \
    --env DB_PORT=3306 \
    --env DB_DATABASE=simple_banking \
    --env DB_USERNAME=admin \
    --env DB_PASSWORD="${DB_PASSWORD}" \
    --env CACHE_DRIVER=redis \
    --env REDIS_HOST="${REDIS_HOST:-127.0.0.1}" \
    --log-driver=awslogs \
    --log-opt awslogs-region="${AWS_DEFAULT_REGION:-us-east-1}" \
    --log-opt awslogs-group="/simple-banking/app" \
    --log-opt awslogs-stream="$(curl -s http://169.254.169.254/latest/meta-data/instance-id)" \
    "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_DEFAULT_REGION:-us-east-1}.amazonaws.com/simple-banking-api:latest"

# Wait for container to be healthy
echo "Waiting for container to start..."
RETRIES=30
COUNT=0
until docker exec simple-banking-app php artisan health:check 2>/dev/null || [ $COUNT -ge $RETRIES ]; do
    echo "Waiting... ($COUNT/$RETRIES)"
    sleep 2
    COUNT=$((COUNT + 1))
done

echo "Server started successfully."
