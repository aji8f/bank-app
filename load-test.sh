#!/bin/bash
# load-test.sh - Load test script for auto scaling testing
# Simulates high traffic to trigger Auto Scaling Group scale-out events

set -e

ALB_URL=${1:-"http://localhost:8000"}
CONCURRENT=${2:-200}
DURATION=${3:-60}
LOG_FILE="load-test-$(date +%Y%m%d-%H%M%S).log"

echo "========================================"
echo "  Simple Banking API Load Test"
echo "========================================"
echo "Target URL  : $ALB_URL"
echo "Concurrent  : $CONCURRENT requests"
echo "Duration    : $DURATION seconds"
echo "Log file    : $LOG_FILE"
echo "========================================"
echo ""

# Check required tools
check_tool() {
    if ! command -v "$1" &>/dev/null; then
        echo "ERROR: '$1' is required but not installed."
        echo "Install with: apt-get install -y $1"
        exit 1
    fi
}

check_tool curl
check_tool bc

# Stats counters
SUCCESS=0
FAILED=0
TOTAL=0
START_TIME=$(date +%s)

# Function to send a single request and log result
send_request() {
    local endpoint="$1"
    local method="${2:-GET}"
    local data="$3"

    local start=$(date +%s%3N)

    if [ "$method" = "POST" ] && [ -n "$data" ]; then
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
            -X POST \
            -H "Content-Type: application/json" \
            -d "$data" \
            --max-time 10 \
            "${ALB_URL}${endpoint}" 2>/dev/null)
    else
        HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
            --max-time 10 \
            "${ALB_URL}${endpoint}" 2>/dev/null)
    fi

    local end=$(date +%s%3N)
    local duration=$((end - start))

    echo "$method $endpoint → HTTP $HTTP_STATUS (${duration}ms)" >> "$LOG_FILE"

    if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 400 ]; then
        echo "success"
    else
        echo "failed"
    fi
}

# Function to run a wave of concurrent requests
run_wave() {
    local wave_num="$1"
    echo "Wave $wave_num: Sending $CONCURRENT concurrent requests..."

    PIDS=()
    RESULTS=()

    for i in $(seq 1 $CONCURRENT); do
        # Rotate through different endpoints
        CASE=$((i % 6))
        case $CASE in
            0) ENDPOINT="/api/health"; METHOD="GET"; DATA="" ;;
            1) ENDPOINT="/api/users"; METHOD="GET"; DATA="" ;;
            2) ENDPOINT="/api/balance/1"; METHOD="GET"; DATA="" ;;
            3) ENDPOINT="/api/balance/2"; METHOD="GET"; DATA="" ;;
            4) ENDPOINT="/api/transactions/1"; METHOD="GET"; DATA="" ;;
            5)
                AMOUNT=$((RANDOM % 1000 + 100))
                ENDPOINT="/api/deposit"
                METHOD="POST"
                DATA="{\"user_id\": 1, \"amount\": $AMOUNT}"
                ;;
        esac

        ( result=$(send_request "$ENDPOINT" "$METHOD" "$DATA")
          echo "$result" >> /tmp/load_test_results_$$
        ) &
        PIDS+=($!)
    done

    # Wait for all background jobs
    for pid in "${PIDS[@]}"; do
        wait "$pid" 2>/dev/null || true
    done

    # Count results
    if [ -f /tmp/load_test_results_$$ ]; then
        WAVE_SUCCESS=$(grep -c "^success$" /tmp/load_test_results_$$ 2>/dev/null || echo 0)
        WAVE_FAILED=$(grep -c "^failed$" /tmp/load_test_results_$$ 2>/dev/null || echo 0)
        rm -f /tmp/load_test_results_$$
        SUCCESS=$((SUCCESS + WAVE_SUCCESS))
        FAILED=$((FAILED + WAVE_FAILED))
        TOTAL=$((TOTAL + CONCURRENT))
        echo "  Wave $wave_num complete: $WAVE_SUCCESS success, $WAVE_FAILED failed"
    fi
}

# Also include transfer tests (may fail due to insufficient balance, that's ok)
run_transfer_wave() {
    echo "Running transfer wave..."
    for i in $(seq 1 20); do
        FROM=$((RANDOM % 5 + 1))
        TO=$((RANDOM % 5 + 1))
        while [ "$TO" -eq "$FROM" ]; do
            TO=$((RANDOM % 5 + 1))
        done
        AMOUNT=$((RANDOM % 500 + 100))
        DATA="{\"from\": $FROM, \"to\": $TO, \"amount\": $AMOUNT}"

        ( curl -s -o /dev/null -w "%{http_code}" \
            -X POST \
            -H "Content-Type: application/json" \
            -d "$DATA" \
            --max-time 10 \
            "${ALB_URL}/api/transfer" >> /tmp/transfer_results_$$ 2>/dev/null
        ) &
    done
    wait
}

# Main load test loop
WAVE=1
END_TIME=$((START_TIME + DURATION))

while [ $(date +%s) -lt $END_TIME ]; do
    run_wave "$WAVE"
    WAVE=$((WAVE + 1))

    # Include transfer tests every 5 waves
    if [ $((WAVE % 5)) -eq 0 ]; then
        run_transfer_wave
    fi

    # Small sleep between waves to avoid overwhelming
    sleep 0.5
done

# Cleanup transfer results
rm -f /tmp/transfer_results_$$

ELAPSED=$(($(date +%s) - START_TIME))
RPS=$(echo "scale=2; $TOTAL / $ELAPSED" | bc)
SUCCESS_RATE=$(echo "scale=2; $SUCCESS * 100 / $TOTAL" | bc 2>/dev/null || echo "N/A")

echo ""
echo "========================================"
echo "  Load Test Complete"
echo "========================================"
echo "Duration      : ${ELAPSED}s"
echo "Total Requests: $TOTAL"
echo "Successful    : $SUCCESS"
echo "Failed        : $FAILED"
echo "Req/sec       : $RPS"
echo "Success Rate  : ${SUCCESS_RATE}%"
echo "Log file      : $LOG_FILE"
echo "========================================"
