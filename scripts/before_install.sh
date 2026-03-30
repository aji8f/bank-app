#!/bin/bash
# before_install.sh - Runs before the application is installed
# Executed during CodeDeploy BeforeInstall lifecycle event

set -e

echo "=== Before Install Script ==="
echo "Timestamp: $(date)"

# Stop existing containers if running
if command -v docker &> /dev/null; then
    echo "Stopping existing Docker containers..."
    docker stop simple-banking-app 2>/dev/null || true
    docker rm simple-banking-app 2>/dev/null || true
fi

# Install system dependencies if needed
if ! command -v docker &> /dev/null; then
    echo "Installing Docker..."
    yum update -y
    yum install -y docker
    systemctl start docker
    systemctl enable docker
    usermod -aG docker ec2-user
fi

# Ensure required directories exist
mkdir -p /var/www/simple-banking
mkdir -p /var/log/simple-banking

# Clean up old deployment files
rm -rf /var/www/simple-banking/current || true

echo "Before install completed successfully."
