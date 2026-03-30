#!/bin/bash
# stop_server.sh - Stops the application server gracefully
# Executed during CodeDeploy ApplicationStop lifecycle event

echo "=== Stop Server Script ==="
echo "Timestamp: $(date)"

# Gracefully stop the Docker container (allow in-flight requests to complete)
if docker ps -q --filter "name=simple-banking-app" | grep -q .; then
    echo "Stopping simple-banking-app container gracefully..."
    docker stop --time 30 simple-banking-app
    docker rm simple-banking-app 2>/dev/null || true
    echo "Container stopped successfully."
else
    echo "No running container found. Skipping stop."
fi

# Optional: Clean up dangling images to free disk space
echo "Cleaning up unused Docker images..."
docker image prune -f 2>/dev/null || true

echo "Stop server completed."
