#!/usr/bin/env bash
# SnapBooth Auth API — curl test script
# Jalankan: bash tests/http/auth.sh
# Membutuhkan: curl, jq (opsional, untuk pretty-print JSON)
#
# Cara pakai:
#   bash tests/http/auth.sh          → jalankan semua test
#   bash tests/http/auth.sh register → jalankan hanya grup register
#   bash tests/http/auth.sh login    → jalankan hanya grup login
#   bash tests/http/auth.sh me       → jalankan hanya grup me
#   bash tests/http/auth.sh logout   → jalankan hanya grup logout

BASE="http://localhost:8000/api/v1"
FILTER="${1:-all}"

# ── Helpers ──────────────────────────────────────────────────────────────────

# Warna terminal
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
    local label="$1"
    local expected_status="$2"
    shift 2
    local response
    response=$(curl -s -w "\n__STATUS__%{http_code}" "$@")
    local body status
    body=$(echo "$response" | sed '$d')
    status=$(echo "$response" | tail -1 | sed 's/__STATUS__//')

    if [ "$status" -eq "$expected_status" ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP $status] $label"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $status, expected $expected_status] $label"
        FAIL=$((FAIL + 1))
    fi

    # Pretty-print kalau jq tersedia, fallback ke raw
    if command -v jq &>/dev/null; then
        echo "$body" | jq . 2>/dev/null || echo "$body"
    else
        echo "$body"
    fi
    echo ""
}

# ── 1. REGISTER ───────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "register" ]]; then
    header "1. REGISTER"

    # 1a — Sukses (201)
    run_test "Register sukses" 201 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{"name":"Faiza Tri","email":"faiza@example.com","password":"password123","password_confirmation":"password123"}'

    # 1b — Email sudah terdaftar (422)
    run_test "Register: email duplikat" 422 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{"name":"Faiza Tri","email":"faiza@example.com","password":"password123","password_confirmation":"password123"}'

    # 1c — Password tidak cocok (422)
    run_test "Register: password_confirmation tidak cocok" 422 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","email":"new@example.com","password":"password123","password_confirmation":"salah"}'

    # 1d — Password terlalu pendek (422)
    run_test "Register: password < 8 karakter" 422 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","email":"short@example.com","password":"abc","password_confirmation":"abc"}'

    # 1e — Email format salah (422)
    run_test "Register: email bukan format valid" 422 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{"name":"Test","email":"bukan-email","password":"password123","password_confirmation":"password123"}'

    # 1f — Body kosong (422)
    run_test "Register: body kosong" 422 \
        -X POST "$BASE/auth/register" \
        -H "Content-Type: application/json" \
        -d '{}'
fi

# ── 2. LOGIN ──────────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "login" ]]; then
    header "2. LOGIN"

    # 2a — Sukses (200) + simpan token
    echo -e "${YELLOW}  → Menjalankan login untuk mengambil token...${RESET}"
    LOGIN_RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"faiza@example.com","password":"password123"}')

    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"faiza@example.com","password":"password123"}')

    if [ "$HTTP_STATUS" -eq 200 ]; then
        echo -e "${GREEN}  ✓ PASS${RESET} [HTTP 200] Login sukses"
        PASS=$((PASS + 1))
        if command -v jq &>/dev/null; then
            TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token')
            echo "$LOGIN_RESPONSE" | jq .
        else
            TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
            echo "$LOGIN_RESPONSE"
        fi
        echo -e "${YELLOW}  → Token tersimpan: ${TOKEN:0:20}...${RESET}"
    else
        echo -e "${RED}  ✗ FAIL${RESET} [HTTP $HTTP_STATUS, expected 200] Login sukses"
        FAIL=$((FAIL + 1))
        TOKEN="token_tidak_tersedia"
    fi
    echo ""

    # 2b — Password salah (401)
    run_test "Login: password salah" 401 \
        -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"faiza@example.com","password":"passwordsalah"}'

    # 2c — Email tidak terdaftar (401)
    run_test "Login: email tidak terdaftar" 401 \
        -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"tidakada@example.com","password":"password123"}'

    # 2d — Format email salah (422)
    run_test "Login: format email tidak valid" 422 \
        -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"bukan-email","password":"password123"}'

    # 2e — Body kosong (422)
    run_test "Login: body kosong" 422 \
        -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{}'
fi

# ── 3. GET /ME ────────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "me" ]]; then
    header "3. GET /me"

    # Ambil token baru kalau belum ada (ketika filter=me dijalankan sendiri)
    if [ -z "$TOKEN" ] || [ "$TOKEN" == "token_tidak_tersedia" ]; then
        echo -e "${YELLOW}  → Mengambil token via login...${RESET}"
        LOGIN_RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
            -H "Content-Type: application/json" \
            -d '{"email":"faiza@example.com","password":"password123"}')
        if command -v jq &>/dev/null; then
            TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token')
        else
            TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
        fi
        echo -e "${YELLOW}  → Token: ${TOKEN:0:20}...${RESET}"
        echo ""
    fi

    # 3a — Sukses (200)
    run_test "GET /me: sukses dengan token valid" 200 \
        -X GET "$BASE/auth/me" \
        -H "Authorization: Bearer $TOKEN"

    # 3b — Tanpa Authorization header (401)
    run_test "GET /me: tanpa token" 401 \
        -X GET "$BASE/auth/me"

    # 3c — Token palsu (401)
    run_test "GET /me: token palsu" 401 \
        -X GET "$BASE/auth/me" \
        -H "Authorization: Bearer ini_token_palsu_tidak_valid"

    # 3d — Format header salah: tanpa kata "Bearer" (401)
    run_test "GET /me: format header salah (tanpa Bearer)" 401 \
        -X GET "$BASE/auth/me" \
        -H "Authorization: $TOKEN"
fi

# ── 4. LOGOUT ─────────────────────────────────────────────────────────────────

if [[ "$FILTER" == "all" || "$FILTER" == "logout" ]]; then
    header "4. LOGOUT"

    # Pastikan ada token untuk logout
    if [ -z "$TOKEN" ] || [ "$TOKEN" == "token_tidak_tersedia" ]; then
        echo -e "${YELLOW}  → Mengambil token via login...${RESET}"
        LOGIN_RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
            -H "Content-Type: application/json" \
            -d '{"email":"faiza@example.com","password":"password123"}')
        if command -v jq &>/dev/null; then
            TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token')
        else
            TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
        fi
        echo -e "${YELLOW}  → Token: ${TOKEN:0:20}...${RESET}"
        echo ""
    fi

    # 4a — Logout sukses (200)
    run_test "Logout: sukses" 200 \
        -X POST "$BASE/auth/logout" \
        -H "Authorization: Bearer $TOKEN"

    # 4b — Token sudah tidak berlaku setelah logout (401)
    run_test "Logout: token bekas sudah ditolak" 401 \
        -X GET "$BASE/auth/me" \
        -H "Authorization: Bearer $TOKEN"

    # 4c — Logout tanpa token (401)
    run_test "Logout: tanpa token" 401 \
        -X POST "$BASE/auth/logout"

    # 4d — Logout All: login baru lalu hapus semua sesi
    echo -e "${YELLOW}  → Login untuk test logout-all...${RESET}"
    FRESH_RESPONSE=$(curl -s -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"email":"faiza@example.com","password":"password123"}')
    if command -v jq &>/dev/null; then
        FRESH_TOKEN=$(echo "$FRESH_RESPONSE" | jq -r '.data.token')
    else
        FRESH_TOKEN=$(echo "$FRESH_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    fi
    echo -e "${YELLOW}  → Fresh token: ${FRESH_TOKEN:0:20}...${RESET}"
    echo ""

    run_test "Logout-all: hapus semua sesi" 200 \
        -X POST "$BASE/auth/logout-all" \
        -H "Authorization: Bearer $FRESH_TOKEN"

    run_test "Logout-all: semua token bekas sudah ditolak" 401 \
        -X GET "$BASE/auth/me" \
        -H "Authorization: Bearer $FRESH_TOKEN"
fi

# ── Ringkasan ─────────────────────────────────────────────────────────────────

echo ""
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  HASIL: $PASS/$TOTAL test passed ✓${RESET}"
else
    echo -e "${BOLD}${RED}  HASIL: $PASS/$TOTAL passed, $FAIL failed ✗${RESET}"
fi
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
echo ""

exit $FAIL
