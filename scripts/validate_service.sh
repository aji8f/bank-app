#!/bin/bash
# validate_service.sh - Validates the deployed service is running correctly
# Executed during CodeDeploy ValidateService lifecycle event

set -e

echo "=== Validate Service Script ==="
echo "Timestamp: $(date)"

BASE_URL="${APP_URL:-http://localhost}"
MAX_RETRIES=10
RETRY_INTERVAL=5

# Function to check an endpoint
check_endpoint() {
    local url="$1"
    local expected_status="${2:-200}"
    local description="$3"

    echo "Checking: $description ($url)"
    HTTP_STATUS=$(curl -s -o /tmp/response.json -w "%{http_code}" "$url" --max-time 10)

    if [ "$HTTP_STATUS" -eq "$expected_status" ]; then
        echo "  PASS - HTTP $HTTP_STATUS"
        return 0
    else
        echo "  FAIL - Expected HTTP $expected_status, got HTTP $HTTP_STATUS"
        cat /tmp/response.json
        return 1
    fi
}

# Wait for the application to be ready
echo "Waiting for application to become ready..."
RETRIES=0
until curl -s -f "${BASE_URL}/api/health" --max-time 5 > /dev/null 2>&1 || [ $RETRIES -ge $MAX_RETRIES ]; do
    echo "Not ready yet... retry $((RETRIES + 1))/$MAX_RETRIES"
    sleep $RETRY_INTERVAL
    RETRIES=$((RETRIES + 1))
done

if [ $RETRIES -ge $MAX_RETRIES ]; then
    echo "ERROR: Application did not become ready in time."
    exit 1
fi

echo "Application is responding. Running validation checks..."

# Check health endpoint
check_endpoint "${BASE_URL}/api/health" 200 "Health Check" || exit 1

# Verify health response content
HEALTH_RESPONSE=$(curl -s "${BASE_URL}/api/health")
DB_STATUS=$(echo "$HEALTH_RESPONSE" | grep -o '"db":"[^"]*"' | cut -d'"' -f4)

if [ "$DB_STATUS" = "connected" ]; then
    echo "  PASS - Database is connected"
else
    echo "  FAIL - Database is NOT connected. Status: $DB_STATUS"
    exit 1
fi

# Check users endpoint
check_endpoint "${BASE_URL}/api/users" 200 "List Users" || exit 1

# Check balance endpoint (user 1 should exist after seeding)
check_endpoint "${BASE_URL}/api/balance/1" 200 "Get Balance - User 1" || exit 1

# Check non-existent user returns 404
check_endpoint "${BASE_URL}/api/balance/99999" 404 "Get Balance - Non-existent User" || exit 1

# Check transactions endpoint
check_endpoint "${BASE_URL}/api/transactions/1" 200 "Transaction History - User 1" || exit 1

echo ""
echo "=== All validation checks passed! ==="
echo "Deployment validated successfully at $(date)"
