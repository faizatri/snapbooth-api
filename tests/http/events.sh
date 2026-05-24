#!/usr/bin/env bash
# SnapBooth Events API — curl test script
# Jalankan: bash tests/http/events.sh
# Membutuhkan: curl, php (untuk JSON extraction)

BASE="http://localhost:8000/api/v1"
FILTER="${1:-all}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Warna & counter ────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'
PASS=0; FAIL=0

header() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
    echo -e "${BOLD}${CYAN}  $1${RESET}"
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
}

# run_test LABEL EXPECTED_STATUS [curl args...]
run_test() {
    local label="$1" expected="$2"
    shift 2
    local response body status
    response=$(curl -s -w "\n__STATUS__%{http_code}" "$@")
    body=$(echo "$response" | sed '$d')
    status=$(echo "$response" | tail -1 | sed 's/__STATUS__//')

    if [ "$status" -eq "$expected" ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP $status] $label"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $status, expected $expected] $label"
        echo -e "    ${YELLOW}Response:${RESET} $body"
        FAIL=$((FAIL + 1))
    fi
    echo ""
}

# jget JSON DOT.PATH  → extracts scalar from JSON using PHP
jget() {
    php "$SCRIPT_DIR/jget.php" "$1" "$2" 2>/dev/null
}

# ── Setup: buat dua user untuk test authorization ──────────────────────────────
header "SETUP — buat user uji"

TS=$(date +%s)
EMAIL1="event_user1_${TS}@example.com"
EMAIL2="event_user2_${TS}@example.com"

echo -e "${YELLOW}  → Registrasi User 1: $EMAIL1${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"User Satu\",\"email\":\"$EMAIL1\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

echo -e "${YELLOW}  → Registrasi User 2: $EMAIL2${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"User Dua\",\"email\":\"$EMAIL2\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

# Login User 1
R1=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL1\",\"password\":\"password123\"}")
TOKEN1=$(jget "$R1" "data.token")

# Login User 2
R2=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL2\",\"password\":\"password123\"}")
TOKEN2=$(jget "$R2" "data.token")

if [ -z "$TOKEN1" ] || [ -z "$TOKEN2" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa mendapatkan token. Pastikan server aktif.${RESET}"
    exit 1
fi

echo -e "${GREEN}  ✓ Token User 1: ${TOKEN1:0:20}...${RESET}"
echo -e "${GREEN}  ✓ Token User 2: ${TOKEN2:0:20}...${RESET}"
echo ""

# ── 1. CREATE EVENT ────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "create" ]]; then
    header "1. CREATE EVENT"

    # 1a — Sukses dengan booth_config lengkap (201)
    CREATE_RESPONSE=$(curl -s -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Wedding Faiza 2026",
            "date": "2026-12-25",
            "location": "Gedung Serbaguna Bandung",
            "is_active": false,
            "booth_config": {
                "countdown": 5,
                "photos_per_session": 4,
                "filter": "grayscale",
                "template_id": null,
                "share_options": ["qr", "email"]
            }
        }')
    CREATE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Wedding Faiza 2026 Dupe",
            "date": "2026-12-25",
            "location": "Test",
            "booth_config": {"countdown":5,"photos_per_session":4,"filter":"grayscale","template_id":null,"share_options":["qr"]}
        }')

    EVENT_ID=$(jget "$CREATE_RESPONSE" "data.id")
    EVENT_SLUG=$(jget "$CREATE_RESPONSE" "data.slug")

    if [ -n "$EVENT_ID" ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /events: sukses (ID=$EVENT_ID, slug=$EVENT_SLUG)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} POST /events: sukses — response tidak mengandung data.id"
        echo -e "    Response: $CREATE_RESPONSE"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 1b — Buat event User 2 (untuk test auth nanti)
    R_U2=$(curl -s -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN2" \
        -H "Content-Type: application/json" \
        -d '{"name":"Event User Dua","date":"2026-11-01","location":"Jakarta"}')
    EVENT2_ID=$(jget "$R_U2" "data.id")
    EVENT2_SLUG=$(jget "$R_U2" "data.slug")

    # 1c — Validasi: field required hilang (422)
    run_test "POST /events: name kosong → 422" 422 \
        -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"date":"2026-12-25"}'

    # 1d — Validasi: booth_config.countdown out of range (422)
    run_test "POST /events: countdown > 60 → 422" 422 \
        -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","date":"2026-12-25","booth_config":{"countdown":999}}'

    # 1e — Validasi: photos_per_session > 10 (422)
    run_test "POST /events: photos_per_session > 10 → 422" 422 \
        -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","date":"2026-12-25","booth_config":{"photos_per_session":99}}'

    # 1f — Tanpa token (401)
    run_test "POST /events: tanpa token → 401" 401 \
        -X POST "$BASE/events" \
        -H "Content-Type: application/json" \
        -d '{"name":"Ghost","date":"2026-12-25"}'
fi

# ── 2. LIST EVENTS ────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "list" ]]; then
    header "2. LIST EVENTS"

    run_test "GET /events: list sukses" 200 \
        -X GET "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events?filter=active" 200 \
        -X GET "$BASE/events?filter=active" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events?filter=upcoming" 200 \
        -X GET "$BASE/events?filter=upcoming" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events?filter=past" 200 \
        -X GET "$BASE/events?filter=past" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events?per_page=5" 200 \
        -X GET "$BASE/events?per_page=5" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events: tanpa token → 401" 401 \
        -X GET "$BASE/events"
fi

# ── 3. GET EVENT BY SLUG ──────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "show" ]]; then
    header "3. GET EVENT BY SLUG"

    run_test "GET /events/{slug}: sukses" 200 \
        -X GET "$BASE/events/$EVENT_SLUG" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events/{slug}: slug tidak ada → 404" 404 \
        -X GET "$BASE/events/slug-yang-tidak-ada-sama-sekali" \
        -H "Authorization: Bearer $TOKEN1"

    # Authorization: User 1 coba lihat event User 2 (403)
    run_test "GET /events/{slug}: event milik user lain → 403" 403 \
        -X GET "$BASE/events/$EVENT2_SLUG" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /events/{slug}: tanpa token → 401" 401 \
        -X GET "$BASE/events/$EVENT_SLUG"
fi

# ── 4. UPDATE EVENT ──────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "update" ]]; then
    header "4. UPDATE EVENT"

    run_test "PUT /events/{id}: update name & booth_config" 200 \
        -X PUT "$BASE/events/$EVENT_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Wedding Faiza 2026 — Updated",
            "location": "Gedung Baru Bandung",
            "booth_config": {
                "countdown": 3,
                "photos_per_session": 6,
                "filter": "sepia",
                "template_id": null,
                "share_options": ["qr"]
            }
        }'

    run_test "PUT /events/{id}: partial update (date saja)" 200 \
        -X PUT "$BASE/events/$EVENT_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"date":"2026-12-31"}'

    # Validasi: date format salah (422)
    run_test "PUT /events/{id}: date format salah → 422" 422 \
        -X PUT "$BASE/events/$EVENT_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"date":"bukan-tanggal"}'

    # Authorization: User 1 coba update event User 2 (403)
    run_test "PUT /events/{id}: event milik user lain → 403" 403 \
        -X PUT "$BASE/events/$EVENT2_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Curi event"}'

    run_test "PUT /events/{id}: tanpa token → 401" 401 \
        -X PUT "$BASE/events/$EVENT_ID" \
        -H "Content-Type: application/json" \
        -d '{"name":"Ghost update"}'
fi

# ── 5. ACTIVATE EVENT ─────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "activate" ]]; then
    header "5. ACTIVATE / DEACTIVATE"

    BEFORE=$(curl -s -X GET "$BASE/events/$EVENT_SLUG" \
        -H "Authorization: Bearer $TOKEN1")
    IS_ACTIVE_BEFORE=$(jget "$BEFORE" "data.is_active")
    echo -e "    ${YELLOW}is_active sebelum: $IS_ACTIVE_BEFORE${RESET}"
    echo ""

    ACTIVATE_RESPONSE=$(curl -s -X POST "$BASE/events/$EVENT_ID/activate" \
        -H "Authorization: Bearer $TOKEN1")
    ACTIVATE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE/events/$EVENT_ID/activate" \
        -H "Authorization: Bearer $TOKEN1")

    IS_ACTIVE_AFTER=$(jget "$ACTIVATE_RESPONSE" "data.is_active")
    MSG=$(jget "$ACTIVATE_RESPONSE" "message")

    if [ "$ACTIVATE_STATUS" -eq 200 ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] POST /events/{id}/activate (message: \"$MSG\")"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $ACTIVATE_STATUS, expected 200] POST /events/{id}/activate"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # Toggle kedua (balik ke semula)
    TOGGLE2=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE/events/$EVENT_ID/activate" \
        -H "Authorization: Bearer $TOKEN1")

    if [ "$TOGGLE2" -eq 200 ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] POST /events/{id}/activate: toggle kedua"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $TOGGLE2, expected 200] toggle kedua"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # Authorization: User 1 coba activate event User 2 (403)
    run_test "POST /events/{id}/activate: event milik user lain → 403" 403 \
        -X POST "$BASE/events/$EVENT2_ID/activate" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "POST /events/{id}/activate: tanpa token → 401" 401 \
        -X POST "$BASE/events/$EVENT_ID/activate"
fi

# ── 6. DELETE EVENT ───────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "delete" ]]; then
    header "6. DELETE EVENT"

    # Buat event khusus untuk dihapus (agar event utama masih ada untuk test lain)
    DEL_RESPONSE=$(curl -s -X POST "$BASE/events" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Event Untuk Dihapus","date":"2026-06-01"}')
    DEL_ID=$(jget "$DEL_RESPONSE" "data.id")

    # Authorization: User 2 coba hapus event User 1 (403)
    run_test "DELETE /events/{id}: event milik user lain → 403" 403 \
        -X DELETE "$BASE/events/$DEL_ID" \
        -H "Authorization: Bearer $TOKEN2"

    run_test "DELETE /events/{id}: tanpa token → 401" 401 \
        -X DELETE "$BASE/events/$DEL_ID"

    run_test "DELETE /events/{id}: sukses (soft delete)" 200 \
        -X DELETE "$BASE/events/$DEL_ID" \
        -H "Authorization: Bearer $TOKEN1"

    # Setelah dihapus: tidak muncul di list (soft deleted)
    LIST_AFTER=$(curl -s "$BASE/events" -H "Authorization: Bearer $TOKEN1")
    TOTAL_AFTER=$(jget "$LIST_AFTER" "data.total")
    echo -e "    ${YELLOW}Total event User 1 setelah delete: $TOTAL_AFTER${RESET}"
    echo ""

    # ID tidak ada (404 via model binding)
    run_test "DELETE /events/{id}: ID tidak ada → 404" 404 \
        -X DELETE "$BASE/events/99999999" \
        -H "Authorization: Bearer $TOKEN1"
fi

# ── Ringkasan ──────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  EVENTS: $PASS/$TOTAL passed ✓${RESET}"
else
    echo -e "${BOLD}${RED}  EVENTS: $PASS/$TOTAL passed, $FAIL failed ✗${RESET}"
fi
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
echo ""
exit $FAIL
