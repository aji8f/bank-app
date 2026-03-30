#!/bin/bash
# after_install.sh - Runs after the application is installed
# Executed during CodeDeploy AfterInstall lifecycle event

set -e

echo "=== After Install Script ==="
echo "Timestamp: $(date)"

APP_DIR="/var/www/simple-banking/current"

# Pull latest Docker image from ECR
echo "Pulling Docker image from ECR..."
aws ecr get-login-password --region ${AWS_DEFAULT_REGION:-us-east-1} \
    | docker login --username AWS --password-stdin \
    "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_DEFAULT_REGION:-us-east-1}.amazonaws.com"

docker pull "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_DEFAULT_REGION:-us-east-1}.amazonaws.com/simple-banking-api:latest"

# Create/update .env from AWS Parameter Store
echo "Fetching configuration from Parameter Store..."
aws ssm get-parameter --name "/simple-banking/app-key" --with-decryption --query "Parameter.Value" --output text > /tmp/app_key
aws ssm get-parameter --name "/simple-banking/db-password" --with-decryption --query "Parameter.Value" --output text > /tmp/db_password

# Set environment variables for the container
export APP_KEY=$(cat /tmp/app_key)
export DB_PASSWORD=$(cat /tmp/db_password)

# Run database migrations
echo "Running database migrations..."
docker run --rm \
    --env DB_CONNECTION=mysql \
    --env DB_HOST="${RDS_ENDPOINT}" \
    --env DB_PORT=3306 \
    --env DB_DATABASE=simple_banking \
    --env DB_USERNAME=admin \
    --env DB_PASSWORD="${DB_PASSWORD}" \
    --env APP_KEY="${APP_KEY}" \
    "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_DEFAULT_REGION:-us-east-1}.amazonaws.com/simple-banking-api:latest" \
    php artisan migrate --force

# Clean up temp files
rm -f /tmp/app_key /tmp/db_password

echo "After install completed successfully."
