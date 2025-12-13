#!/bin/bash

# HSE API Test Script
# Bu script tüm endpoint'leri test eder ve sonuçları loglar

BASE_URL="http://localhost:8081/api/v1"
LOG_FILE="test_results_$(date +%Y%m%d_%H%M%S).log"
TOKEN=""

# Renkli output için
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log fonksiyonu
log() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Test başlığı
test_header() {
    log "\n${BLUE}========================================${NC}"
    log "${BLUE}TEST: $1${NC}"
    log "${BLUE}========================================${NC}"
}

# Test sonucu
test_result() {
    if [ $1 -eq 0 ]; then
        log "${GREEN}✅ BAŞARILI${NC}"
    else
        log "${RED}❌ BAŞARISIZ${NC}"
    fi
}

# JSON pretty print
pretty_json() {
    echo "$1" | python3 -m json.tool 2>/dev/null || echo "$1"
}

log "${YELLOW}╔════════════════════════════════════════╗${NC}"
log "${YELLOW}║   HSE API ENDPOINT TEST SUITE          ║${NC}"
log "${YELLOW}║   $(date)                    ║${NC}"
log "${YELLOW}╔════════════════════════════════════════╗${NC}"

# Test 1: Health Check
test_header "1. Health Check - Base URL"
log "Request: GET $BASE_URL"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
test_result 0

# Test 2: Login
test_header "2. Authentication - Login"
log "Request: POST $BASE_URL/auth/login"
log "Body: {\"email\":\"test@hse.com\",\"password\":\"test123\"}"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"test@hse.com","password":"test123"}')
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"

# Token'ı çıkar
TOKEN=$(echo "$BODY" | python3 -c "import sys, json; print(json.load(sys.stdin)['data']['tokens']['access_token'])" 2>/dev/null)
if [ -n "$TOKEN" ]; then
    log "${GREEN}Token alındı: ${TOKEN:0:50}...${NC}"
    test_result 0
else
    log "${RED}Token alınamadı!${NC}"
    test_result 1
    exit 1
fi

# Test 3: Get Profile
test_header "3. Authentication - Get Profile"
log "Request: GET $BASE_URL/auth/me"
log "Authorization: Bearer {token}"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/auth/me" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 4: Dashboard Stats
test_header "4. Dashboard - Get Statistics"
log "Request: GET $BASE_URL/dashboard/stats"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/dashboard/stats" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 5: Get Checklists
test_header "5. Field Tours - Get Checklists"
log "Request: GET $BASE_URL/field-tours/checklists"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/field-tours/checklists" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 6: Get Checklist Detail
test_header "6. Field Tours - Get Checklist Detail (ID: 1)"
log "Request: GET $BASE_URL/field-tours/checklists/1"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/field-tours/checklists/1" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 7: Create Field Tour
test_header "7. Field Tours - Create New Field Tour"
log "Request: POST $BASE_URL/field-tours"
log "Body: {\"checklist_id\":1,\"location\":\"Test Lokasyonu - $(date +%H:%M:%S)\"}"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/field-tours" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"checklist_id\":1,\"location\":\"Test Lokasyonu - $(date +%H:%M:%S)\"}")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"

# Field Tour ID'yi çıkar
FIELD_TOUR_ID=$(echo "$BODY" | python3 -c "import sys, json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null)
if [ -n "$FIELD_TOUR_ID" ]; then
    log "${GREEN}Field Tour oluşturuldu, ID: $FIELD_TOUR_ID${NC}"
    test_result 0
else
    log "${YELLOW}Field Tour ID alınamadı${NC}"
    test_result 1
fi

# Test 8: Store Responses
if [ -n "$FIELD_TOUR_ID" ]; then
    test_header "8. Field Tours - Store Responses"
    log "Request: POST $BASE_URL/field-tours/$FIELD_TOUR_ID/responses"
    log "Body: Responses array"
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/field-tours/$FIELD_TOUR_ID/responses" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"responses":[{"question_id":1,"answer":"yes","notes":"Test notu"},{"question_id":2,"answer":"yes"},{"question_id":3,"answer":"4","notes":"İyi durumda"}]}')
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    log "HTTP Code: $HTTP_CODE"
    log "Response:"
    log "$(pretty_json "$BODY")"
    [ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1
fi

# Test 9: Complete Field Tour
if [ -n "$FIELD_TOUR_ID" ]; then
    test_header "9. Field Tours - Complete Field Tour"
    log "Request: POST $BASE_URL/field-tours/$FIELD_TOUR_ID/complete"
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/field-tours/$FIELD_TOUR_ID/complete" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"summary":"Test turu tamamlandı","overall_score":85.5}')
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    log "HTTP Code: $HTTP_CODE"
    log "Response:"
    log "$(pretty_json "$BODY")"
    [ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1
fi

# Test 10: Get Actions
test_header "10. Actions - Get Actions List"
log "Request: GET $BASE_URL/actions"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/actions" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 11: Create Action
test_header "11. Actions - Create New Action"
log "Request: POST $BASE_URL/actions"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/actions" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"title":"Test Aksiyonu","description":"Test açıklaması","priority":"high","due_date":"2025-12-31"}')
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"

ACTION_ID=$(echo "$BODY" | python3 -c "import sys, json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null)
if [ -n "$ACTION_ID" ]; then
    log "${GREEN}Action oluşturuldu, ID: $ACTION_ID${NC}"
    test_result 0
else
    log "${YELLOW}Action ID alınamadı${NC}"
    test_result 1
fi

# Test 12: Get Action Detail
if [ -n "$ACTION_ID" ]; then
    test_header "12. Actions - Get Action Detail"
    log "Request: GET $BASE_URL/actions/$ACTION_ID"
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/actions/$ACTION_ID" \
        -H "Authorization: Bearer $TOKEN")
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    log "HTTP Code: $HTTP_CODE"
    log "Response:"
    log "$(pretty_json "$BODY")"
    [ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1
fi

# Test 13: Add Comment
if [ -n "$ACTION_ID" ]; then
    test_header "13. Actions - Add Comment"
    log "Request: POST $BASE_URL/actions/$ACTION_ID/comments"
    RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/actions/$ACTION_ID/comments" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d '{"comment":"Test yorumu - İşlem devam ediyor"}')
    HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
    BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
    log "HTTP Code: $HTTP_CODE"
    log "Response:"
    log "$(pretty_json "$BODY")"
    [ "$HTTP_CODE" = "201" ] && test_result 0 || test_result 1
fi

# Test 14: Get Notifications
test_header "14. Notifications - Get Notifications"
log "Request: GET $BASE_URL/notifications"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/notifications" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 15: Get Departments
test_header "15. Helpers - Get Departments"
log "Request: GET $BASE_URL/departments"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/departments" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 16: Get Users
test_header "16. Helpers - Get Users"
log "Request: GET $BASE_URL/users"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/users" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Test 17: Unauthorized Test
test_header "17. Security - Unauthorized Access Test"
log "Request: GET $BASE_URL/dashboard/stats (without token)"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" "$BASE_URL/dashboard/stats")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "401" ] && test_result 0 || test_result 1

# Test 18: Logout
test_header "18. Authentication - Logout"
log "Request: POST $BASE_URL/auth/logout"
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "$BASE_URL/auth/logout" \
    -H "Authorization: Bearer $TOKEN")
HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE/d')
log "HTTP Code: $HTTP_CODE"
log "Response:"
log "$(pretty_json "$BODY")"
[ "$HTTP_CODE" = "200" ] && test_result 0 || test_result 1

# Özet
log "\n${YELLOW}╔════════════════════════════════════════╗${NC}"
log "${YELLOW}║          TEST ÖZET                      ║${NC}"
log "${YELLOW}╚════════════════════════════════════════╝${NC}"
log "Log dosyası: $LOG_FILE"
log "Test tamamlandı: $(date)"
log "\n${GREEN}Tüm testler tamamlandı!${NC}"
log "${BLUE}Detaylı sonuçlar için log dosyasını inceleyin.${NC}"
