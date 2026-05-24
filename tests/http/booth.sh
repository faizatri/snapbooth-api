#!/usr/bin/env bash
# SnapBooth Booth API — curl test script
# Jalankan: bash tests/http/booth.sh
# Membutuhkan: curl, php (server aktif di localhost:8000)

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

jget() {
    php "$SCRIPT_DIR/jget.php" "$1" "$2" 2>/dev/null
}

# ── Setup: buat user operator dan event aktif ──────────────────────────────────
header "SETUP — user operator + event aktif"

TS=$(date +%s)
EMAIL="booth_op_${TS}@example.com"

echo -e "${YELLOW}  → Registrasi operator: $EMAIL${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"Operator Booth\",\"email\":\"$EMAIL\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

LOGIN_R=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"password123\"}")
OP_TOKEN=$(jget "$LOGIN_R" "data.token")

if [ -z "$OP_TOKEN" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa login operator.${RESET}"
    exit 1
fi
echo -e "${GREEN}  ✓ Operator token: ${OP_TOKEN:0:20}...${RESET}"

# Buat event aktif
EVENT_R=$(curl -s -X POST "$BASE/events" \
    -H "Authorization: Bearer $OP_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Test Booth Event",
        "date": "2026-12-31",
        "location": "Bandung",
        "is_active": false,
        "booth_config": {
            "countdown": 3,
            "photos_per_session": 3,
            "filter": "grayscale",
            "template_id": null,
            "share_options": ["qr", "email"]
        }
    }')
EVENT_ID=$(jget "$EVENT_R" "data.id")
EVENT_SLUG=$(jget "$EVENT_R" "data.slug")

if [ -z "$EVENT_ID" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa membuat event.${RESET}"
    exit 1
fi
echo -e "${GREEN}  ✓ Event dibuat: ID=$EVENT_ID, slug=$EVENT_SLUG${RESET}"

# Aktifkan event
curl -s -X POST "$BASE/events/$EVENT_ID/activate" \
    -H "Authorization: Bearer $OP_TOKEN" > /dev/null
echo -e "${GREEN}  ✓ Event diaktifkan${RESET}"

# Buat event TIDAK aktif untuk test error
INACTIVE_R=$(curl -s -X POST "$BASE/events" \
    -H "Authorization: Bearer $OP_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"name":"Event Tidak Aktif","date":"2026-12-31","is_active":false}')
INACTIVE_SLUG=$(jget "$INACTIVE_R" "data.slug")
echo -e "${GREEN}  ✓ Event non-aktif: slug=$INACTIVE_SLUG${RESET}"
echo ""

# ── 1. START SESSION ──────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "start" ]]; then
    header "1. START SESSION"

    # 1a — Sukses tanpa data tamu (201)
    START_R=$(curl -s -X POST "$BASE/booth/$EVENT_SLUG/start" \
        -H "Content-Type: application/json" \
        -d '{}')
    START_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE/booth/$EVENT_SLUG/start" \
        -H "Content-Type: application/json" \
        -d '{}')
    SESSION_TOKEN=$(jget "$START_R" "data.session_token")

    if [ -n "$SESSION_TOKEN" ] && [ "$START_STATUS" -eq 201 ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /booth/{slug}/start: tanpa data tamu"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $START_STATUS] POST /booth/{slug}/start — response: $START_R"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 1b — Sukses dengan data tamu lengkap (201)
    START_GUEST=$(curl -s -X POST "$BASE/booth/$EVENT_SLUG/start" \
        -H "Content-Type: application/json" \
        -d '{"guest_name":"Faiza","guest_email":"faiza@test.com","guest_phone":"08123456789"}')
    GUEST_TOKEN=$(jget "$START_GUEST" "data.session_token")
    if [ -n "$GUEST_TOKEN" ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /booth/{slug}/start: dengan data tamu"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} POST /booth/{slug}/start dengan data tamu"
        echo -e "    Response: $START_GUEST"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 1c — Event tidak aktif → 403
    run_test "POST /booth/{slug}/start: event tidak aktif → 403" 403 \
        -X POST "$BASE/booth/$INACTIVE_SLUG/start" \
        -H "Content-Type: application/json" \
        -d '{}'

    # 1d — Slug tidak ada → 404
    run_test "POST /booth/{slug}/start: slug tidak ada → 404" 404 \
        -X POST "$BASE/booth/slug-tidak-ada-sama-sekali/start" \
        -H "Content-Type: application/json" \
        -d '{}'

    # 1e — Email invalid → 422
    run_test "POST /booth/{slug}/start: email invalid → 422" 422 \
        -X POST "$BASE/booth/$EVENT_SLUG/start" \
        -H "Content-Type: application/json" \
        -d '{"guest_email":"bukan-email"}'
fi

# ── 2. GET SESSION ────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "show" ]]; then
    header "2. GET SESSION"

    run_test "GET /booth/{token}: sukses" 200 \
        -X GET "$BASE/booth/$SESSION_TOKEN"

    run_test "GET /booth/{token}: token tidak valid → 401" 401 \
        -X GET "$BASE/booth/token-yang-salah-dan-tidak-ada"
fi

# ── 3. UPLOAD PHOTO ──────────────────────────────────────────────────────────

# Siapkan test image (Windows-safe path via cygpath)
TEST_IMAGE_UNIX="$SCRIPT_DIR/test.png"
TEST_IMAGE="$(cygpath -w "$TEST_IMAGE_UNIX" 2>/dev/null || echo "$TEST_IMAGE_UNIX")"

if [ ! -f "$TEST_IMAGE_UNIX" ]; then
    echo -e "${YELLOW}→ Generate test.png...${RESET}"
    php "$SCRIPT_DIR/gen_image.php" 2>/dev/null
fi

if [[ "$FILTER" == "all" || "$FILTER" == "photo" ]]; then
    header "3. UPLOAD PHOTO"

    if [ ! -f "$TEST_IMAGE_UNIX" ]; then
        echo -e "${YELLOW}  ⚠ test.png tidak ada, skip upload tests${RESET}"
    else
        # 3a — Sukses upload (201) — single request, extract body + status together
        UPLOAD_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -F "photo=@$TEST_IMAGE")
        UPLOAD_R=$(echo "$UPLOAD_FULL" | sed '$d')
        UPLOAD_STATUS=$(echo "$UPLOAD_FULL" | tail -1 | sed 's/__STATUS__//')
        PHOTO_ID=$(jget "$UPLOAD_R" "data.id")
        PHOTO_URL=$(jget "$UPLOAD_R" "data.file_url")

        if [ -n "$PHOTO_ID" ] && [ "$UPLOAD_STATUS" -eq 201 ]; then
            echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /booth/{token}/photos: sukses (ID=$PHOTO_ID)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}  ✗ FAIL${RESET} [HTTP $UPLOAD_STATUS] POST /booth/{token}/photos"
            echo -e "    Response: $UPLOAD_R"
            FAIL=$((FAIL + 1))
        fi
        echo ""

        # Verifikasi file bisa diakses via HTTP
        if [ -n "$PHOTO_URL" ]; then
            FILE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$PHOTO_URL")
            if [ "$FILE_STATUS" -eq 200 ]; then
                echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] File processed tersedia di storage: $PHOTO_URL"
                PASS=$((PASS + 1))
            else
                echo -e "${RED}  ✗ FAIL${RESET} [HTTP $FILE_STATUS] File tidak bisa diakses: $PHOTO_URL"
                FAIL=$((FAIL + 1))
            fi
            echo ""
        fi

        # Upload kedua
        UPLOAD_FULL2=$(curl -s -w "\n__STATUS__%{http_code}" \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -F "photo=@$TEST_IMAGE")
        UPLOAD_R2=$(echo "$UPLOAD_FULL2" | sed '$d')
        UPLOAD_STATUS2=$(echo "$UPLOAD_FULL2" | tail -1 | sed 's/__STATUS__//')
        PHOTO_ID2=$(jget "$UPLOAD_R2" "data.id")
        if [ -n "$PHOTO_ID2" ] && [ "$UPLOAD_STATUS2" -eq 201 ]; then
            echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] Upload foto ke-2 (ID=$PHOTO_ID2)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}  ✗ FAIL${RESET} [HTTP $UPLOAD_STATUS2] Upload foto ke-2"
            FAIL=$((FAIL + 1))
        fi
        echo ""

        # Upload ketiga (akan mencapai batas photos_per_session=3)
        UPLOAD_FULL3=$(curl -s -w "\n__STATUS__%{http_code}" \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -F "photo=@$TEST_IMAGE")
        UPLOAD_R3=$(echo "$UPLOAD_FULL3" | sed '$d')
        UPLOAD_STATUS3=$(echo "$UPLOAD_FULL3" | tail -1 | sed 's/__STATUS__//')
        PHOTO_ID3=$(jget "$UPLOAD_R3" "data.id")
        if [ -n "$PHOTO_ID3" ] && [ "$UPLOAD_STATUS3" -eq 201 ]; then
            echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] Upload foto ke-3 (ID=$PHOTO_ID3)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}  ✗ FAIL${RESET} [HTTP $UPLOAD_STATUS3] Upload foto ke-3"
            FAIL=$((FAIL + 1))
        fi
        echo ""

        # 3b — Melebihi photos_per_session (422) — booth_config.photos_per_session=3
        run_test "POST /booth/{token}/photos: melebihi batas sesi → 422" 422 \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -F "photo=@$TEST_IMAGE"

        # 3c — Tanpa file → 422
        run_test "POST /booth/{token}/photos: tanpa file → 422" 422 \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -H "Content-Type: application/json" \
            -d '{}'

        # 3d — Token tidak valid → 401
        run_test "POST /booth/{token}/photos: token tidak valid → 401" 401 \
            -X POST "$BASE/booth/token-salah/photos" \
            -F "photo=@$TEST_IMAGE"
    fi
fi

# ── 4. LIST PHOTOS ────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "list" ]]; then
    header "4. LIST PHOTOS"

    run_test "GET /booth/{token}/photos: list foto sesi" 200 \
        -X GET "$BASE/booth/$SESSION_TOKEN/photos"

    run_test "GET /booth/{token}/photos: token tidak valid → 401" 401 \
        -X GET "$BASE/booth/token-salah/photos"
fi

# ── 5. DELETE PHOTO ──────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "delete-photo" ]]; then
    header "5. DELETE PHOTO"

    if [ -n "$PHOTO_ID3" ]; then
        # Coba hapus foto sesi lain (pakai guest token dari sesi berbeda)
        run_test "DELETE /booth/{token}/photos/{id}: foto bukan milik sesi → 403" 403 \
            -X DELETE "$BASE/booth/$GUEST_TOKEN/photos/$PHOTO_ID3"

        # Hapus foto sendiri
        run_test "DELETE /booth/{token}/photos/{id}: hapus foto sukses" 200 \
            -X DELETE "$BASE/booth/$SESSION_TOKEN/photos/$PHOTO_ID3"
    else
        echo -e "${YELLOW}  ⚠ Skip delete test — PHOTO_ID3 tidak tersedia${RESET}"
        echo ""
    fi

    run_test "DELETE /booth/{token}/photos/{id}: token tidak valid → 401" 401 \
        -X DELETE "$BASE/booth/token-salah/photos/1"
fi

# ── 6. SHARE VIA LINK ────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "share" ]]; then
    header "6. SHARE VIA LINK"

    if [ -n "$PHOTO_ID" ] && [ -n "$SESSION_TOKEN" ]; then
        LINK_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
            -X POST "$BASE/share/photo/$PHOTO_ID/link" \
            -H "Content-Type: application/json" \
            -d "{\"session_token\":\"$SESSION_TOKEN\"}")
        LINK_R=$(echo "$LINK_FULL" | sed '$d')
        LINK_STATUS=$(echo "$LINK_FULL" | tail -1 | sed 's/__STATUS__//')
        LINK_URL=$(jget "$LINK_R" "data.photo_url")

        if [ "$LINK_STATUS" -eq 200 ] && [ -n "$LINK_URL" ]; then
            echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] POST /share/photo/{id}/link (URL: ${LINK_URL:0:50}...)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}  ✗ FAIL${RESET} [HTTP $LINK_STATUS] POST /share/photo/{id}/link"
            echo -e "    Response: $LINK_R"
            FAIL=$((FAIL + 1))
        fi
        echo ""

        # Token salah → 403
        run_test "POST /share/photo/{id}/link: token salah → 403" 403 \
            -X POST "$BASE/share/photo/$PHOTO_ID/link" \
            -H "Content-Type: application/json" \
            -d '{"session_token":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}'

        # Tanpa session_token → 422
        run_test "POST /share/photo/{id}/link: tanpa session_token → 422" 422 \
            -X POST "$BASE/share/photo/$PHOTO_ID/link" \
            -H "Content-Type: application/json" \
            -d '{}'
    else
        echo -e "${YELLOW}  ⚠ Skip share tests — PHOTO_ID tidak tersedia${RESET}"
        echo ""
    fi
fi

# ── 7. END SESSION ────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "end" ]]; then
    header "7. END SESSION"

    run_test "POST /booth/{token}/end: sukses mengakhiri sesi" 200 \
        -X POST "$BASE/booth/$SESSION_TOKEN/end"

    # Coba end lagi → 422 (sudah ended)
    run_test "POST /booth/{token}/end: sesi sudah berakhir → 422" 422 \
        -X POST "$BASE/booth/$SESSION_TOKEN/end"

    # Setelah ended, masih bisa GET show (lihat hasil)
    run_test "GET /booth/{token}: bisa lihat sesi yang sudah ended" 200 \
        -X GET "$BASE/booth/$SESSION_TOKEN"

    # Setelah ended, tidak bisa upload foto → 422
    if [ -f "$TEST_IMAGE_UNIX" ]; then
        run_test "POST /booth/{token}/photos: sesi sudah berakhir → 422" 422 \
            -X POST "$BASE/booth/$SESSION_TOKEN/photos" \
            -F "photo=@$TEST_IMAGE"
    fi

    run_test "POST /booth/{token}/end: token tidak valid → 401" 401 \
        -X POST "$BASE/booth/token-salah/end"
fi

# ── Ringkasan ──────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  BOOTH: $PASS/$TOTAL passed ✓${RESET}"
else
    echo -e "${BOLD}${RED}  BOOTH: $PASS/$TOTAL passed, $FAIL failed ✗${RESET}"
fi
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
echo ""
exit $FAIL
