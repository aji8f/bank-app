#!/bin/bash
# waf-test.sh - WAF (Web Application Firewall) testing script
# Tests that SQL injection and other malicious payloads are blocked

set -e

ALB_URL=${1:-"http://localhost:8000"}
PASS=0
FAIL=0
BLOCK=0

echo "========================================"
echo "  Simple Banking API - WAF Test Suite"
echo "========================================"
echo "Target: $ALB_URL"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to test an endpoint
test_request() {
    local description="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_block="${5:-false}"  # true = expect WAF block (403/400), false = expect normal response

    if [ "$method" = "POST" ] && [ -n "$data" ]; then
        HTTP_STATUS=$(curl -s -o /tmp/waf_response.json -w "%{http_code}" \
            -X POST \
            -H "Content-Type: application/json" \
            -d "$data" \
            --max-time 10 \
            "${ALB_URL}${endpoint}" 2>/dev/null)
    else
        HTTP_STATUS=$(curl -s -o /tmp/waf_response.json -w "%{http_code}" \
            --max-time 10 \
            "${ALB_URL}${endpoint}" 2>/dev/null)
    fi

    RESPONSE=$(cat /tmp/waf_response.json 2>/dev/null || echo "")

    if [ "$expected_block" = "true" ]; then
        # We expect this to be blocked (403) or rejected (400/422)
        if [ "$HTTP_STATUS" = "403" ] || [ "$HTTP_STATUS" = "400" ] || [ "$HTTP_STATUS" = "422" ]; then
            echo -e "${GREEN}[BLOCKED]${NC} $description → HTTP $HTTP_STATUS"
            BLOCK=$((BLOCK + 1))
        elif [ "$HTTP_STATUS" = "200" ]; then
            echo -e "${RED}[DANGER!]${NC} $description → HTTP $HTTP_STATUS - PAYLOAD NOT BLOCKED!"
            FAIL=$((FAIL + 1))
        else
            echo -e "${YELLOW}[UNKNOWN]${NC} $description → HTTP $HTTP_STATUS"
            PASS=$((PASS + 1))
        fi
    else
        # Normal request - should succeed
        if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 500 ]; then
            echo -e "${GREEN}[PASS]${NC} $description → HTTP $HTTP_STATUS"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}[FAIL]${NC} $description → HTTP $HTTP_STATUS"
            FAIL=$((FAIL + 1))
        fi
    fi
}

# ==========================================
# SECTION 1: Baseline (should all pass)
# ==========================================
echo "--- Section 1: Baseline Requests (should succeed) ---"
test_request "Health check" "GET" "/api/health" "" "false"
test_request "List users" "GET" "/api/users" "" "false"
test_request "Get balance - valid user" "GET" "/api/balance/1" "" "false"
test_request "Get transactions - valid user" "GET" "/api/transactions/1" "" "false"
test_request "Deposit - valid request" "POST" "/api/deposit" '{"user_id": 1, "amount": 1000}' "false"
echo ""

# ==========================================
# SECTION 2: SQL Injection in URL Path
# ==========================================
echo "--- Section 2: SQL Injection in URL Path ---"
test_request "SQLi in user_id path param: 1 OR 1=1" "GET" "/api/balance/1%20OR%201=1" "" "true"
test_request "SQLi in user_id path param: 1; DROP TABLE users--" "GET" "/api/balance/1%3BDROP%20TABLE%20users--" "" "true"
test_request "SQLi in user_id path param: 1 UNION SELECT" "GET" "/api/balance/1%20UNION%20SELECT%20*%20FROM%20users" "" "true"
test_request "SQLi: classic single quote" "GET" "/api/balance/1'" "" "true"
test_request "SQLi: double quote injection" "GET" '/api/balance/1"' "" "true"
echo ""

# ==========================================
# SECTION 3: SQL Injection in POST body
# ==========================================
echo "--- Section 3: SQL Injection in POST Body ---"
test_request "SQLi in deposit user_id: 1 OR 1=1" "POST" "/api/deposit" '{"user_id": "1 OR 1=1", "amount": 1000}' "true"
test_request "SQLi in deposit amount field" "POST" "/api/deposit" '{"user_id": 1, "amount": "1; DROP TABLE transactions--"}' "true"
test_request "SQLi in transfer from field" "POST" "/api/transfer" '{"from": "1 UNION SELECT password FROM users", "to": 2, "amount": 100}' "true"
test_request "SQLi SLEEP injection" "POST" "/api/transfer" '{"from": "1; SELECT SLEEP(5)--", "to": 2, "amount": 100}' "true"
test_request "SQLi boolean injection" "POST" "/api/deposit" '{"user_id": "1 AND 1=1", "amount": 500}' "true"
echo ""

# ==========================================
# SECTION 4: XSS Attempts
# ==========================================
echo "--- Section 4: XSS Attempts ---"
test_request "XSS in POST body" "POST" "/api/deposit" '{"user_id": 1, "amount": "<script>alert(1)</script>"}' "true"
test_request "XSS in URL param" "GET" "/api/balance/%3Cscript%3Ealert(1)%3C/script%3E" "" "true"
test_request "XSS img tag" "POST" "/api/transfer" '{"from": 1, "to": "<img src=x onerror=alert(1)>", "amount": 100}' "true"
echo ""

# ==========================================
# SECTION 5: Path Traversal
# ==========================================
echo "--- Section 5: Path Traversal Attempts ---"
test_request "Path traversal: ../etc/passwd" "GET" "/api/balance/../../../etc/passwd" "" "true"
test_request "Path traversal encoded" "GET" "/api/balance/%2E%2E%2F%2E%2E%2Fetc%2Fpasswd" "" "true"
echo ""

# ==========================================
# SECTION 6: Invalid Inputs (Business Logic)
# ==========================================
echo "--- Section 6: Business Logic Validation ---"
test_request "Negative amount deposit" "POST" "/api/deposit" '{"user_id": 1, "amount": -1000}' "true"
test_request "Zero amount transfer" "POST" "/api/transfer" '{"from": 1, "to": 2, "amount": 0}' "true"
test_request "Insufficient balance transfer" "POST" "/api/transfer" '{"from": 5, "to": 1, "amount": 999999999}' "true"
test_request "Non-existent user balance" "GET" "/api/balance/99999" "" "false"
test_request "Same user transfer" "POST" "/api/transfer" '{"from": 1, "to": 1, "amount": 100}' "true"
test_request "Missing required fields in deposit" "POST" "/api/deposit" '{"user_id": 1}' "true"
test_request "String as amount" "POST" "/api/deposit" '{"user_id": 1, "amount": "not-a-number"}' "true"
echo ""

# ==========================================
# SECTION 7: Header Injection & Other Attacks
# ==========================================
echo "--- Section 7: Malformed Requests ---"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-Forwarded-For: 127.0.0.1' OR '1'='1" \
    -d '{"user_id": 1, "amount": 100}' \
    --max-time 10 \
    "${ALB_URL}/api/deposit" 2>/dev/null)
echo -e "Header injection attempt → HTTP $HTTP_STATUS"

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    -H "Content-Type: text/plain" \
    -d 'user_id=1 OR 1=1&amount=1000' \
    --max-time 10 \
    "${ALB_URL}/api/deposit" 2>/dev/null)
echo -e "Wrong content-type injection → HTTP $HTTP_STATUS"
echo ""

# ==========================================
# RESULTS
# ==========================================
TOTAL=$((PASS + FAIL + BLOCK))
echo "========================================"
echo "  WAF Test Results"
echo "========================================"
echo "Total Tests  : $TOTAL"
echo -e "Passed       : ${GREEN}$PASS${NC}"
echo -e "Blocked/422  : ${GREEN}$BLOCK${NC} (payloads correctly rejected)"
echo -e "Failed       : ${RED}$FAIL${NC} (unexpected responses)"
echo "========================================"

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}All tests passed! Application is properly protected.${NC}"
    exit 0
else
    echo -e "${RED}$FAIL test(s) failed! Review WAF and input validation rules.${NC}"
    exit 1
fi
