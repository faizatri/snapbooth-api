#!/usr/bin/env bash
# SnapBooth Templates API — curl test script
# Jalankan: bash tests/http/templates.sh
# Catatan: jalankan storage:link dahulu dan set STORAGE_DISK=local_public di .env
#          untuk test upload preview tanpa butuh Cloudflare R2.

BASE="http://localhost:8000/api/v1"
FILTER="${1:-all}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# curl pada Windows Git Bash tidak bisa baca file via Unix absolute path (/c/Users/...).
# cygpath -w mengkonversi ke Windows path (C:\Users\...) yang bisa dibaca curl.
TEST_IMAGE_UNIX="$SCRIPT_DIR/test.png"
TEST_IMAGE="$(cygpath -w "$TEST_IMAGE_UNIX" 2>/dev/null || echo "$TEST_IMAGE_UNIX")"

# ── Warna & counter ───────────────────────────────────────────────────────────
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

# ── Setup ──────────────────────────────────────────────────────────────────────
header "SETUP — buat user uji"

TS=$(date +%s)
EMAIL1="tmpl_user1_${TS}@example.com"
EMAIL2="tmpl_user2_${TS}@example.com"

echo -e "${YELLOW}  → Registrasi User 1: $EMAIL1${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"Tmpl User1\",\"email\":\"$EMAIL1\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

echo -e "${YELLOW}  → Registrasi User 2: $EMAIL2${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"Tmpl User2\",\"email\":\"$EMAIL2\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

R1=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL1\",\"password\":\"password123\"}")
TOKEN1=$(jget "$R1" "data.token")

R2=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL2\",\"password\":\"password123\"}")
TOKEN2=$(jget "$R2" "data.token")

if [ -z "$TOKEN1" ] || [ -z "$TOKEN2" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa mendapatkan token.${RESET}"
    exit 1
fi
echo -e "${GREEN}  ✓ Token OK untuk User1 dan User2${RESET}"
echo ""

# ── 1. CREATE TEMPLATE — tanpa preview (JSON) ──────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "create" ]]; then
    header "1. CREATE TEMPLATE (JSON, tanpa file upload)"

    CREATE_RESPONSE=$(curl -s -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Classic Strip",
            "is_public": false,
            "config": {
                "width": 1200,
                "height": 1800,
                "layout": "strip",
                "overlay_url": null,
                "background_color": "#ffffff",
                "text_elements": [
                    {"text": "SnapBooth", "x": 600, "y": 50, "size": 48, "color": "#333333", "font": "Roboto"}
                ]
            }
        }')

    TMPL_ID=$(jget "$CREATE_RESPONSE" "data.id")

    if [ -n "$TMPL_ID" ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /templates: sukses (ID=$TMPL_ID)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} POST /templates: sukses — data.id tidak ada"
        echo -e "    ${YELLOW}Response:${RESET} $CREATE_RESPONSE"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # Buat template publik
    PUB_RESPONSE=$(curl -s -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Public Grid Template",
            "is_public": true,
            "config": {"width": 800, "height": 800, "layout": "grid", "background_color": "#000000", "text_elements": []}
        }')
    PUB_ID=$(jget "$PUB_RESPONSE" "data.id")

    # Template User 2 (private)
    U2_RESPONSE=$(curl -s -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN2" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "User 2 Private Template",
            "is_public": false,
            "config": {"width": 600, "height": 900, "layout": "collage", "text_elements": []}
        }')
    TMPL2_ID=$(jget "$U2_RESPONSE" "data.id")

    # Validasi: config tidak ada (422)
    run_test "POST /templates: config hilang → 422" 422 \
        -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"No Config"}'

    # Validasi: text_elements item tanpa text (422)
    run_test "POST /templates: text_elements.*.text hilang → 422" 422 \
        -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Bad Text","config":{"width":100,"height":100,"layout":"strip","text_elements":[{"x":0,"y":0}]}}'

    # Validasi: config.width melebihi 9999 (422)
    run_test "POST /templates: config.width > 9999 → 422" 422 \
        -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Big","config":{"width":99999,"height":100,"layout":"strip","text_elements":[]}}'

    # Tanpa token (401)
    run_test "POST /templates: tanpa token → 401" 401 \
        -X POST "$BASE/templates" \
        -H "Content-Type: application/json" \
        -d '{"name":"Ghost","config":{"width":100,"height":100,"layout":"strip","text_elements":[]}}'
fi

# ── 2. CREATE TEMPLATE — dengan preview image (multipart) ─────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "upload" ]]; then
    header "2. CREATE TEMPLATE + PREVIEW UPLOAD (Storage Test)"

    if [ ! -f "$TEST_IMAGE_UNIX" ]; then
        echo -e "${YELLOW}  → test.png tidak ditemukan, generate via PHP...${RESET}"
        php "$SCRIPT_DIR/gen_image.php" 2>/dev/null
    fi

    if [ -f "$TEST_IMAGE" ]; then
        UPLOAD_RESPONSE=$(curl -s -X POST "$BASE/templates" \
            -H "Authorization: Bearer $TOKEN1" \
            -F "name=Template Dengan Preview" \
            -F "is_public=0" \
            -F "config[width]=1200" \
            -F "config[height]=1800" \
            -F "config[layout]=strip" \
            -F "config[background_color]=#ff6600" \
            -F "config[text_elements][0][text]=SnapBooth" \
            -F "config[text_elements][0][x]=600" \
            -F "config[text_elements][0][y]=50" \
            -F "config[text_elements][0][size]=48" \
            -F "config[text_elements][0][color]=#ffffff" \
            -F "preview=@$TEST_IMAGE;type=image/png")

        UPLOAD_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/templates" \
            -H "Authorization: Bearer $TOKEN1" \
            -F "name=Template Dengan Preview Dupe" \
            -F "config[width]=100" \
            -F "config[height]=100" \
            -F "config[layout]=strip" \
            -F "config[text_elements][0][text]=Test" \
            -F "preview=@$TEST_IMAGE;type=image/png")

        PREVIEW_URL=$(jget "$UPLOAD_RESPONSE" "data.preview_url")
        UPLOAD_TMPL_ID=$(jget "$UPLOAD_RESPONSE" "data.id")

        if [ "$UPLOAD_STATUS" -eq 201 ] && [ -n "$PREVIEW_URL" ]; then
            echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 201] POST /templates: upload preview sukses"
            echo -e "    ${YELLOW}preview_url: $PREVIEW_URL${RESET}"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}  ✗ FAIL${RESET} [HTTP $UPLOAD_STATUS] POST /templates: upload preview"
            echo -e "    ${YELLOW}preview_url kosong atau status bukan 201${RESET}"
            echo -e "    Response: $UPLOAD_RESPONSE"
            FAIL=$((FAIL + 1))
        fi
        echo ""

        # Verifikasi file bisa diakses via URL
        if [ -n "$PREVIEW_URL" ]; then
            FILE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$PREVIEW_URL")
            if [ "$FILE_STATUS" -eq 200 ]; then
                echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] Storage: file bisa diakses di $PREVIEW_URL"
                PASS=$((PASS + 1))
            else
                echo -e "${RED}  ✗ FAIL${RESET} [HTTP $FILE_STATUS] Storage: file tidak bisa diakses di $PREVIEW_URL"
                echo -e "    (Pastikan 'php artisan storage:link' sudah dijalankan)"
                FAIL=$((FAIL + 1))
            fi
            echo ""
        fi

        # Preview bukan file gambar (422)
        EVENTS_SH="$(cygpath -w "$SCRIPT_DIR/events.sh" 2>/dev/null || echo "$SCRIPT_DIR/events.sh")"
        run_test "POST /templates: preview bukan gambar → 422" 422 \
            -X POST "$BASE/templates" \
            -H "Authorization: Bearer $TOKEN1" \
            -F "name=Bad Preview" \
            -F "config[width]=100" \
            -F "config[height]=100" \
            -F "config[layout]=strip" \
            -F "config[text_elements][0][text]=Test" \
            -F "preview=@$EVENTS_SH;type=text/x-sh"
    else
        echo -e "${YELLOW}  ⚠ SKIP: test.png tidak bisa dibuat (PHP GD mungkin tidak aktif)${RESET}"
        echo ""
    fi
fi

# ── 3. LIST TEMPLATES ─────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "list" ]]; then
    header "3. LIST TEMPLATES"

    run_test "GET /templates: milik sendiri + publik" 200 \
        -X GET "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /templates?filter=own" 200 \
        -X GET "$BASE/templates?filter=own" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /templates?filter=public" 200 \
        -X GET "$BASE/templates?filter=public" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /templates?per_page=5" 200 \
        -X GET "$BASE/templates?per_page=5" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /templates: tanpa token → 401" 401 \
        -X GET "$BASE/templates"
fi

# ── 4. GET TEMPLATE BY ID ─────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "show" ]]; then
    header "4. GET TEMPLATE BY ID"

    run_test "GET /templates/{id}: template sendiri" 200 \
        -X GET "$BASE/templates/$TMPL_ID" \
        -H "Authorization: Bearer $TOKEN1"

    # User 1 bisa lihat template publik User 1
    run_test "GET /templates/{id}: template publik milik sendiri" 200 \
        -X GET "$BASE/templates/$PUB_ID" \
        -H "Authorization: Bearer $TOKEN1"

    # User 2 bisa lihat template publik User 1 (karena is_public=true)
    run_test "GET /templates/{id}: template publik milik user lain → 200" 200 \
        -X GET "$BASE/templates/$PUB_ID" \
        -H "Authorization: Bearer $TOKEN2"

    # User 1 TIDAK bisa lihat template PRIVATE User 2 (403)
    run_test "GET /templates/{id}: template private user lain → 403" 403 \
        -X GET "$BASE/templates/$TMPL2_ID" \
        -H "Authorization: Bearer $TOKEN1"

    # ID tidak ada (404)
    run_test "GET /templates/{id}: ID tidak ada → 404" 404 \
        -X GET "$BASE/templates/99999999" \
        -H "Authorization: Bearer $TOKEN1"

    run_test "GET /templates/{id}: tanpa token → 401" 401 \
        -X GET "$BASE/templates/$TMPL_ID"
fi

# ── 5. UPDATE TEMPLATE ────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "update" ]]; then
    header "5. UPDATE TEMPLATE"

    run_test "PUT /templates/{id}: update name & config" 200 \
        -X PUT "$BASE/templates/$TMPL_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Classic Strip — Updated",
            "config": {
                "width": 1200,
                "height": 1800,
                "layout": "strip",
                "background_color": "#eeeeee",
                "text_elements": [
                    {"text": "Updated", "x": 600, "y": 50, "size": 36, "color": "#000000"}
                ]
            }
        }'

    run_test "PUT /templates/{id}: set is_public = true" 200 \
        -X PUT "$BASE/templates/$TMPL_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"is_public": true}'

    # Validasi: config.height < 1 (422)
    run_test "PUT /templates/{id}: config.height < 1 → 422" 422 \
        -X PUT "$BASE/templates/$TMPL_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"config":{"width":100,"height":0,"layout":"strip","text_elements":[]}}'

    # Authorization: User 1 coba update template User 2 (403)
    run_test "PUT /templates/{id}: template milik user lain → 403" 403 \
        -X PUT "$BASE/templates/$TMPL2_ID" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Curi template"}'

    run_test "PUT /templates/{id}: tanpa token → 401" 401 \
        -X PUT "$BASE/templates/$TMPL_ID" \
        -H "Content-Type: application/json" \
        -d '{"name":"Ghost update"}'
fi

# ── 6. DELETE TEMPLATE ────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "delete" ]]; then
    header "6. DELETE TEMPLATE"

    # Buat template khusus untuk dihapus
    DEL_RESPONSE=$(curl -s -X POST "$BASE/templates" \
        -H "Authorization: Bearer $TOKEN1" \
        -H "Content-Type: application/json" \
        -d '{"name":"Template Hapus","config":{"width":100,"height":100,"layout":"strip","text_elements":[]}}')
    DEL_ID=$(jget "$DEL_RESPONSE" "data.id")

    # User 2 coba hapus template User 1 (403)
    run_test "DELETE /templates/{id}: template milik user lain → 403" 403 \
        -X DELETE "$BASE/templates/$DEL_ID" \
        -H "Authorization: Bearer $TOKEN2"

    run_test "DELETE /templates/{id}: tanpa token → 401" 401 \
        -X DELETE "$BASE/templates/$DEL_ID"

    run_test "DELETE /templates/{id}: sukses" 200 \
        -X DELETE "$BASE/templates/$DEL_ID" \
        -H "Authorization: Bearer $TOKEN1"

    # Setelah dihapus → 404
    run_test "DELETE /templates/{id}: sudah dihapus → 404" 404 \
        -X GET "$BASE/templates/$DEL_ID" \
        -H "Authorization: Bearer $TOKEN1"
fi

# ── Ringkasan ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  TEMPLATES: $PASS/$TOTAL passed ✓${RESET}"
else
    echo -e "${BOLD}${RED}  TEMPLATES: $PASS/$TOTAL passed, $FAIL failed ✗${RESET}"
fi
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
echo ""
exit $FAIL
