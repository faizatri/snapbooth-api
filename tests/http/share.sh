#!/usr/bin/env bash
# SnapBooth Share API — E2E test script (new booth flow)
# Covers: start-session → upload-photo (base64) → complete-session → QR, email, WhatsApp, download
# Run: bash tests/http/share.sh
# Requires: php artisan serve (localhost:8000), MAIL_MAILER=log

BASE="http://localhost:8000/api/v1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="$API_DIR/storage/logs/laravel.log"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'
PASS=0; FAIL=0

# On Windows/Git Bash, PHP needs Windows-style paths (C:\...) not Unix-style (/c/...)
win_path() { cygpath -w "$1" 2>/dev/null || echo "$1"; }

header() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
    echo -e "${BOLD}${CYAN}  $1${RESET}"
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
}

pass_test() {
    echo -e "${GREEN}  ✓ PASS${RESET} $1"
    PASS=$((PASS + 1))
    echo ""
}

fail_test() {
    echo -e "${RED}  ✗ FAIL${RESET} $1"
    if [ -n "$2" ]; then
        echo -e "    ${YELLOW}Detail:${RESET} $2"
    fi
    FAIL=$((FAIL + 1))
    echo ""
}

run_test() {
    local label="$1" expected="$2"
    shift 2
    local response body status
    response=$(curl -s -w "\n__STATUS__%{http_code}" "$@")
    body=$(echo "$response" | sed '$d')
    status=$(echo "$response" | tail -1 | sed 's/__STATUS__//')

    if [ "$status" -eq "$expected" ]; then
        pass_test "[HTTP $status] $label"
    else
        fail_test "[HTTP $status, expected $expected] $label" "$body"
    fi
}

jget() {
    php "$SCRIPT_DIR/jget.php" "$1" "$2" 2>/dev/null
}

# Globals set by upload_photo — avoids subshell capture contaminating results
UPLOAD_PID=""
UPLOAD_PROC_URL=""
UPLOAD_THUMB_URL=""

# Upload a single photo via base64 JSON. Sets UPLOAD_PID, UPLOAD_PROC_URL, UPLOAD_THUMB_URL.
upload_photo() {
    local TOKEN="$1" SHOT="$2" LABEL="$3"
    UPLOAD_PID=""; UPLOAD_PROC_URL=""; UPLOAD_THUMB_URL=""

    local WIN_IMAGE
    WIN_IMAGE=$(win_path "$TEST_IMAGE")

    local BODY_FILE WIN_BODY
    BODY_FILE=$(php -r "echo sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'booth_upload_${RANDOM}_${SHOT}.json';")
    WIN_BODY=$(win_path "$BODY_FILE")

    php -r "
\$b64 = base64_encode(file_get_contents('$WIN_IMAGE'));
\$body = json_encode([
    'session_token' => '$TOKEN',
    'photo'         => \$b64,
    'shot_number'   => $SHOT,
]);
file_put_contents('$WIN_BODY', \$body);
" 2>/dev/null

    local FULL_R R STATUS PHOTO_ID PROC_URL THUMB_URL
    FULL_R=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/booth/upload-photo" \
        -H "Content-Type: application/json" \
        --data "@$BODY_FILE")
    rm -f "$BODY_FILE"

    R=$(echo "$FULL_R" | sed '$d')
    STATUS=$(echo "$FULL_R" | tail -1 | sed 's/__STATUS__//')

    PHOTO_ID=$(jget "$R" "data.photo_id")
    PROC_URL=$(jget "$R" "data.processed_url")
    THUMB_URL=$(jget "$R" "data.thumbnail_url")

    if [ -n "$PHOTO_ID" ] && [ "$STATUS" -eq 201 ]; then
        pass_test "[HTTP 201] $LABEL — photo_id=$PHOTO_ID"
        if [ -n "$PROC_URL" ]; then
            local FILE_STATUS
            FILE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$PROC_URL")
            if [ "$FILE_STATUS" -eq 200 ]; then
                pass_test "  Processed URL accessible (HTTP 200): ${PROC_URL##*/snapbooth/}"
            else
                fail_test "  Processed URL inaccessible (HTTP $FILE_STATUS)" "$PROC_URL"
            fi
        fi
        UPLOAD_PID="$PHOTO_ID"
        UPLOAD_PROC_URL="$PROC_URL"
        UPLOAD_THUMB_URL="$THUMB_URL"
    else
        fail_test "[HTTP $STATUS] $LABEL" "$R"
    fi
}

# ── Setup: operator + 2 events ─────────────────────────────────────────────────
header "SETUP — operator + event grayscale + event sepia"

TS=$(date +%s)
EMAIL="share_op_${TS}@example.com"

echo -e "${YELLOW}  → Registrasi operator: $EMAIL${RESET}"
curl -s -X POST "$BASE/auth/register" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"Share Tester\",\"email\":\"$EMAIL\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}" > /dev/null

LOGIN_R=$(curl -s -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"password123\"}")
OP_TOKEN=$(jget "$LOGIN_R" "data.token")

if [ -z "$OP_TOKEN" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa login operator.${RESET}"
    exit 1
fi
echo -e "${GREEN}  ✓ Operator ready${RESET}"

# Event grayscale
GS_EVENT_R=$(curl -s -X POST "$BASE/events" \
    -H "Authorization: Bearer $OP_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
        \"name\": \"Grayscale Event ${TS}\",
        \"date\": \"2026-12-31\",
        \"location\": \"Test Studio\",
        \"booth_config\": {
            \"photos_per_session\": 4,
            \"filter\": \"grayscale\",
            \"template_id\": null,
            \"share_options\": [\"qr\",\"email\",\"whatsapp\"]
        }
    }")
GS_EVENT_ID=$(jget "$GS_EVENT_R" "data.id")
GS_SLUG=$(jget "$GS_EVENT_R" "data.slug")

if [ -z "$GS_EVENT_ID" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa membuat event grayscale.${RESET}"
    echo -e "  Response: $GS_EVENT_R"
    exit 1
fi
curl -s -X POST "$BASE/events/$GS_EVENT_ID/activate" -H "Authorization: Bearer $OP_TOKEN" > /dev/null
echo -e "${GREEN}  ✓ Event grayscale: ID=$GS_EVENT_ID slug=$GS_SLUG${RESET}"

# Event sepia
SP_EVENT_R=$(curl -s -X POST "$BASE/events" \
    -H "Authorization: Bearer $OP_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{
        \"name\": \"Sepia Event ${TS}\",
        \"date\": \"2026-12-31\",
        \"location\": \"Test Studio\",
        \"booth_config\": {
            \"photos_per_session\": 4,
            \"filter\": \"sepia\",
            \"template_id\": null
        }
    }")
SP_EVENT_ID=$(jget "$SP_EVENT_R" "data.id")
SP_SLUG=$(jget "$SP_EVENT_R" "data.slug")

if [ -z "$SP_EVENT_ID" ]; then
    echo -e "${RED}  ✗ Setup gagal: tidak bisa membuat event sepia.${RESET}"
    exit 1
fi
curl -s -X POST "$BASE/events/$SP_EVENT_ID/activate" -H "Authorization: Bearer $OP_TOKEN" > /dev/null
echo -e "${GREEN}  ✓ Event sepia:     ID=$SP_EVENT_ID slug=$SP_SLUG${RESET}"

# Generate test image jika belum ada
TEST_IMAGE="$SCRIPT_DIR/test.png"
if [ ! -f "$TEST_IMAGE" ]; then
    echo -e "${YELLOW}  → Generate test.png...${RESET}"
    php "$SCRIPT_DIR/gen_image.php" 2>/dev/null
fi

if [ ! -f "$TEST_IMAGE" ]; then
    echo -e "${RED}  ✗ Setup gagal: test.png tidak ada.${RESET}"
    exit 1
fi
echo -e "${GREEN}  ✓ test.png ready ($(wc -c < "$TEST_IMAGE" | tr -d ' ') bytes)${RESET}"
echo ""

# ── 1. START SESSION ──────────────────────────────────────────────────────────
header "1. START SESSION"

# Grayscale session
START_GS_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
    -X POST "$BASE/booth/start-session" \
    -H "Content-Type: application/json" \
    -d "{\"event_slug\":\"$GS_SLUG\",\"guest_name\":\"Faiza Test\",\"guest_email\":\"tamu@test.com\"}")
START_GS_R=$(echo "$START_GS_FULL" | sed '$d')
START_GS_STATUS=$(echo "$START_GS_FULL" | tail -1 | sed 's/__STATUS__//')
GS_TOKEN=$(jget "$START_GS_R" "data.session_token")

if [ -n "$GS_TOKEN" ] && [ "$START_GS_STATUS" -eq 201 ]; then
    pass_test "[HTTP 201] POST /booth/start-session (grayscale) — token: ${GS_TOKEN:0:16}..."
else
    fail_test "[HTTP $START_GS_STATUS] POST /booth/start-session (grayscale)" "$START_GS_R"
fi

# Sepia session
START_SP_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
    -X POST "$BASE/booth/start-session" \
    -H "Content-Type: application/json" \
    -d "{\"event_slug\":\"$SP_SLUG\",\"guest_name\":\"Sepia Guest\"}")
START_SP_R=$(echo "$START_SP_FULL" | sed '$d')
START_SP_STATUS=$(echo "$START_SP_FULL" | tail -1 | sed 's/__STATUS__//')
SP_TOKEN=$(jget "$START_SP_R" "data.session_token")

if [ -n "$SP_TOKEN" ] && [ "$START_SP_STATUS" -eq 201 ]; then
    pass_test "[HTTP 201] POST /booth/start-session (sepia) — token: ${SP_TOKEN:0:16}..."
else
    fail_test "[HTTP $START_SP_STATUS] POST /booth/start-session (sepia)" "$START_SP_R"
fi

# Validation
run_test "POST /booth/start-session: slug tidak ada → 404" 404 \
    -X POST "$BASE/booth/start-session" \
    -H "Content-Type: application/json" \
    -d '{"event_slug":"slug-tidak-ada-sama-sekali-xyz"}'

# ── 2. UPLOAD PHOTO (base64) ──────────────────────────────────────────────────
header "2. UPLOAD PHOTO (base64)"

echo -e "${YELLOW}  → Upload 2 foto grayscale...${RESET}"
echo ""
upload_photo "$GS_TOKEN" 1 "Upload shot 1 (grayscale filter)"; GS_PID1="$UPLOAD_PID"
upload_photo "$GS_TOKEN" 2 "Upload shot 2 (grayscale filter)"; GS_PID2="$UPLOAD_PID"

echo -e "${YELLOW}  → Upload 2 foto sepia...${RESET}"
echo ""
upload_photo "$SP_TOKEN" 1 "Upload shot 1 (sepia filter)"; SP_PID1="$UPLOAD_PID"
upload_photo "$SP_TOKEN" 2 "Upload shot 2 (sepia filter)"; SP_PID2="$UPLOAD_PID"

# Validation: token invalid → 401 (with valid base64 body)
INVALID_BODY=$(php -r "echo sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'booth_invalid_$$.json';")
WIN_INVALID=$(win_path "$INVALID_BODY")
WIN_IMG=$(win_path "$TEST_IMAGE")
php -r "
\$b64 = base64_encode(file_get_contents('$WIN_IMG'));
file_put_contents('$WIN_INVALID', json_encode([
    'session_token' => str_repeat('a', 64),
    'photo'         => \$b64,
    'shot_number'   => 1,
]));
" 2>/dev/null
run_test "POST /booth/upload-photo: token tidak valid → 401" 401 \
    -X POST "$BASE/booth/upload-photo" \
    -H "Content-Type: application/json" \
    --data "@$INVALID_BODY"
rm -f "$INVALID_BODY"

run_test "POST /booth/upload-photo: tanpa photo → 422" 422 \
    -X POST "$BASE/booth/upload-photo" \
    -H "Content-Type: application/json" \
    -d "{\"session_token\":\"$GS_TOKEN\",\"shot_number\":3}"

# ── 3. COMPLETE SESSION ────────────────────────────────────────────────────────
header "3. COMPLETE SESSION"

if [ -z "$GS_PID1" ] || [ -z "$GS_PID2" ]; then
    echo -e "${YELLOW}  ⚠ Skip complete grayscale — foto tidak terupload${RESET}"
    echo ""
    GS_SHARE_TOKEN=""
else
    COMPLETE_GS_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/booth/complete-session" \
        -H "Content-Type: application/json" \
        -d "{\"session_token\":\"$GS_TOKEN\",\"selected_photo_ids\":[$GS_PID1,$GS_PID2]}")
    COMPLETE_GS_R=$(echo "$COMPLETE_GS_FULL" | sed '$d')
    COMPLETE_GS_STATUS=$(echo "$COMPLETE_GS_FULL" | tail -1 | sed 's/__STATUS__//')
    GS_SHARE_TOKEN=$(jget "$COMPLETE_GS_R" "data.share_token")

    if [ -n "$GS_SHARE_TOKEN" ] && [ "$COMPLETE_GS_STATUS" -eq 200 ]; then
        pass_test "[HTTP 200] POST /booth/complete-session (grayscale) — share_token: ${GS_SHARE_TOKEN:0:16}..."
    else
        fail_test "[HTTP $COMPLETE_GS_STATUS] POST /booth/complete-session (grayscale)" "$COMPLETE_GS_R"
        GS_SHARE_TOKEN=""
    fi

    # Sesi sudah selesai → 422
    run_test "POST /booth/complete-session: sesi sudah selesai → 422" 422 \
        -X POST "$BASE/booth/complete-session" \
        -H "Content-Type: application/json" \
        -d "{\"session_token\":\"$GS_TOKEN\",\"selected_photo_ids\":[$GS_PID1]}"
fi

if [ -n "$SP_PID1" ] && [ -n "$SP_PID2" ]; then
    COMPLETE_SP_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/booth/complete-session" \
        -H "Content-Type: application/json" \
        -d "{\"session_token\":\"$SP_TOKEN\",\"selected_photo_ids\":[$SP_PID1,$SP_PID2]}")
    COMPLETE_SP_R=$(echo "$COMPLETE_SP_FULL" | sed '$d')
    COMPLETE_SP_STATUS=$(echo "$COMPLETE_SP_FULL" | tail -1 | sed 's/__STATUS__//')
    SP_SHARE_TOKEN=$(jget "$COMPLETE_SP_R" "data.share_token")

    if [ -n "$SP_SHARE_TOKEN" ] && [ "$COMPLETE_SP_STATUS" -eq 200 ]; then
        pass_test "[HTTP 200] POST /booth/complete-session (sepia) — share_token: ${SP_SHARE_TOKEN:0:16}..."
    else
        fail_test "[HTTP $COMPLETE_SP_STATUS] POST /booth/complete-session (sepia)" "$COMPLETE_SP_R"
    fi
fi

# Foto bukan milik sesi → 422 (start fresh session, try to use sepia photo)
if [ -n "$SP_PID1" ]; then
    CROSS_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/booth/start-session" \
        -H "Content-Type: application/json" \
        -d "{\"event_slug\":\"$GS_SLUG\"}")
    CROSS_R=$(echo "$CROSS_FULL" | sed '$d')
    CROSS_TOKEN=$(jget "$CROSS_R" "data.session_token")

    if [ -n "$CROSS_TOKEN" ]; then
        run_test "POST /booth/complete-session: foto bukan milik sesi → 422" 422 \
            -X POST "$BASE/booth/complete-session" \
            -H "Content-Type: application/json" \
            -d "{\"session_token\":\"$CROSS_TOKEN\",\"selected_photo_ids\":[$SP_PID1]}"
    fi
fi

# ── 4. SHOW SESSION (public gallery) ──────────────────────────────────────────
header "4. GET /booth/session/{shareToken} — Public Gallery"

if [ -n "$GS_SHARE_TOKEN" ]; then
    SHOW_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X GET "$BASE/booth/session/$GS_SHARE_TOKEN")
    SHOW_R=$(echo "$SHOW_FULL" | sed '$d')
    SHOW_STATUS=$(echo "$SHOW_FULL" | tail -1 | sed 's/__STATUS__//')
    SHOW_GUEST=$(jget "$SHOW_R" "data.session.guest_name")

    if [ "$SHOW_STATUS" -eq 200 ]; then
        if [ "$SHOW_GUEST" = "Faiza Test" ]; then
            pass_test "[HTTP 200] GET /booth/session/{shareToken} — guest_name: $SHOW_GUEST"
        else
            pass_test "[HTTP 200] GET /booth/session/{shareToken} — data tersedia (guest: ${SHOW_GUEST:-N/A})"
        fi
    else
        fail_test "[HTTP $SHOW_STATUS] GET /booth/session/{shareToken}" "$SHOW_R"
    fi
else
    echo -e "${YELLOW}  ⚠ Skip show session — share_token tidak tersedia${RESET}"; echo ""
fi

run_test "GET /booth/session/{shareToken}: token tidak ada → 404" 404 \
    -X GET "$BASE/booth/session/token-tidak-ada-sama-sekali-xyz"

# ── 5. QR CODE ────────────────────────────────────────────────────────────────
header "5. GET /share/{shareToken}/qr — QR Code PNG"

if [ -n "$GS_SHARE_TOKEN" ]; then
    QR_TEMP=$(php -r "echo sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_test_$$.png';")
    WIN_QR=$(win_path "$QR_TEMP")

    QR_STATUS=$(curl -s -o "$QR_TEMP" -w "%{http_code}" \
        -X GET "$BASE/share/$GS_SHARE_TOKEN/qr")

    if [ "$QR_STATUS" -eq 200 ]; then
        QR_MAGIC=$(php -r "
\$f = fopen('$WIN_QR', 'rb');
\$bytes = fread(\$f, 4);
fclose(\$f);
echo bin2hex(\$bytes);
" 2>/dev/null)
        QR_SIZE=$(wc -c < "$QR_TEMP" 2>/dev/null | tr -d ' ' || echo 0)

        if [ "$QR_MAGIC" = "89504e47" ]; then
            pass_test "[HTTP 200] GET /share/{token}/qr — PNG valid (magic: 89 50 4E 47, size: ${QR_SIZE}b)"
        else
            fail_test "QR response bukan PNG (magic: ${QR_MAGIC:-empty}, expected: 89504e47)"
        fi

        if [ "${QR_SIZE:-0}" -gt 1000 ]; then
            pass_test "  QR PNG size: ${QR_SIZE}b > 1000b — konten QR valid"
        else
            fail_test "  QR PNG terlalu kecil (${QR_SIZE:-0}b)" "Mungkin kosong atau error"
        fi
    else
        fail_test "[HTTP $QR_STATUS] GET /share/{token}/qr" "Expected 200"
    fi

    rm -f "$QR_TEMP"
else
    echo -e "${YELLOW}  ⚠ Skip QR test — share_token tidak tersedia${RESET}"; echo ""
fi

run_test "GET /share/{token}/qr: token tidak ada → 404" 404 \
    -X GET "$BASE/share/token-tidak-ada-abc123/qr"

# ── 6. EMAIL SHARE ────────────────────────────────────────────────────────────
header "6. POST /share/{shareToken}/email — Email Share (MAIL_MAILER=log)"

if [ -n "$GS_SHARE_TOKEN" ]; then
    LOG_SIZE_BEFORE=0
    if [ -f "$LOG_FILE" ]; then
        LOG_SIZE_BEFORE=$(wc -c < "$LOG_FILE" | tr -d ' ')
    fi

    EMAIL_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/share/$GS_SHARE_TOKEN/email" \
        -H "Content-Type: application/json" \
        -d '{"email":"tamu@test.com"}')
    EMAIL_R=$(echo "$EMAIL_FULL" | sed '$d')
    EMAIL_STATUS=$(echo "$EMAIL_FULL" | tail -1 | sed 's/__STATUS__//')
    EMAIL_CHANNEL=$(jget "$EMAIL_R" "data.channel")
    EMAIL_RECIPIENT=$(jget "$EMAIL_R" "data.recipient")
    EMAIL_PHOTOS=$(jget "$EMAIL_R" "data.photos_sent")

    if [ "$EMAIL_STATUS" -eq 200 ] && [ "$EMAIL_CHANNEL" = "email" ]; then
        pass_test "[HTTP 200] POST /share/{token}/email — recipient: $EMAIL_RECIPIENT, photos_sent: $EMAIL_PHOTOS"
    else
        fail_test "[HTTP $EMAIL_STATUS] POST /share/{token}/email" "$EMAIL_R"
    fi

    # Verifikasi email tercatat di laravel.log
    if [ -f "$LOG_FILE" ]; then
        LOG_SIZE_AFTER=$(wc -c < "$LOG_FILE" | tr -d ' ')
        if [ "$LOG_SIZE_AFTER" -gt "$LOG_SIZE_BEFORE" ]; then
            FOUND_URL=$(tail -c 100000 "$LOG_FILE" | grep -c "share/$GS_SHARE_TOKEN" 2>/dev/null || echo 0)
            FOUND_MAIL=$(tail -c 100000 "$LOG_FILE" | grep -c "Foto booth\|SnapBooth\|GalleryShareMail\|gallery-share" 2>/dev/null || echo 0)
            if [ "$FOUND_URL" -gt 0 ]; then
                pass_test "  Email logged — share URL ditemukan di laravel.log ✓"
            elif [ "$FOUND_MAIL" -gt 0 ]; then
                pass_test "  Email logged — GalleryShareMail ter-render di laravel.log ✓"
            else
                fail_test "  Email tidak ditemukan di laravel.log" "Cek MAIL_MAILER=log di .env"
            fi
        else
            fail_test "  laravel.log tidak bertambah setelah kirim email" "Cek MAIL_MAILER=log"
        fi
    else
        echo -e "${YELLOW}  ⚠ laravel.log belum ada, skip log verification${RESET}"; echo ""
    fi

    run_test "POST /share/{token}/email: email tidak valid → 422" 422 \
        -X POST "$BASE/share/$GS_SHARE_TOKEN/email" \
        -H "Content-Type: application/json" \
        -d '{"email":"bukan-email"}'
else
    echo -e "${YELLOW}  ⚠ Skip email test — share_token tidak tersedia${RESET}"; echo ""
fi

run_test "POST /share/{token}/email: token tidak ada → 404" 404 \
    -X POST "$BASE/share/token-tidak-ada-abc123/email" \
    -H "Content-Type: application/json" \
    -d '{"email":"test@test.com"}'

# ── 7. WHATSAPP SHARE ─────────────────────────────────────────────────────────
header "7. POST /share/{shareToken}/whatsapp — WhatsApp Deep Link"

if [ -n "$GS_SHARE_TOKEN" ]; then
    WA_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X POST "$BASE/share/$GS_SHARE_TOKEN/whatsapp" \
        -H "Content-Type: application/json")
    WA_R=$(echo "$WA_FULL" | sed '$d')
    WA_STATUS=$(echo "$WA_FULL" | tail -1 | sed 's/__STATUS__//')
    WA_URL=$(jget "$WA_R" "data.whatsapp_url")
    WA_CHANNEL=$(jget "$WA_R" "data.channel")
    WA_SHARE_URL=$(jget "$WA_R" "data.share_url")

    if [ "$WA_STATUS" -eq 200 ] && [ "$WA_CHANNEL" = "whatsapp" ]; then
        pass_test "[HTTP 200] POST /share/{token}/whatsapp — channel: $WA_CHANNEL"
    else
        fail_test "[HTTP $WA_STATUS] POST /share/{token}/whatsapp" "$WA_R"
    fi

    if [[ "$WA_URL" == https://wa.me/* ]]; then
        pass_test "  whatsapp_url valid — format https://wa.me/?text=... ✓"
    else
        fail_test "  whatsapp_url tidak valid" "Got: $WA_URL"
    fi

    if [[ "$WA_SHARE_URL" == *"$GS_SHARE_TOKEN"* ]]; then
        pass_test "  share_url berisi share_token ✓"
    else
        fail_test "  share_url tidak berisi share_token" "Got: $WA_SHARE_URL"
    fi
else
    echo -e "${YELLOW}  ⚠ Skip WhatsApp test — share_token tidak tersedia${RESET}"; echo ""
fi

run_test "POST /share/{token}/whatsapp: token tidak ada → 404" 404 \
    -X POST "$BASE/share/token-tidak-ada-abc123/whatsapp"

# ── 8. DOWNLOAD — SIGNED URL ──────────────────────────────────────────────────
header "8. GET /download/{photoId} — Signed URL (1 jam)"

if [ -n "$GS_PID1" ]; then
    DL_FULL=$(curl -s -w "\n__STATUS__%{http_code}" \
        -X GET "$BASE/download/$GS_PID1")
    DL_R=$(echo "$DL_FULL" | sed '$d')
    DL_STATUS=$(echo "$DL_FULL" | tail -1 | sed 's/__STATUS__//')
    DL_URL=$(jget "$DL_R" "data.download_url")
    DL_EXPIRES=$(jget "$DL_R" "data.expires_at")
    DL_PHOTO_ID=$(jget "$DL_R" "data.photo_id")

    if [ "$DL_STATUS" -eq 200 ] && [ -n "$DL_URL" ]; then
        pass_test "[HTTP 200] GET /download/$GS_PID1 — download_url tersedia"
    else
        fail_test "[HTTP $DL_STATUS] GET /download/$GS_PID1" "$DL_R"
    fi

    if [ -n "$DL_URL" ]; then
        DL_FILE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$DL_URL")
        if [ "$DL_FILE_STATUS" -eq 200 ]; then
            pass_test "  Download URL accessible (HTTP 200) — file siap diunduh"
        else
            fail_test "  Download URL tidak bisa diakses (HTTP $DL_FILE_STATUS)" "$DL_URL"
        fi
    fi

    if [ -n "$DL_EXPIRES" ]; then
        pass_test "  expires_at: $DL_EXPIRES ✓"
    else
        fail_test "  expires_at tidak ada" "$DL_R"
    fi

    if [ "$DL_PHOTO_ID" = "$GS_PID1" ]; then
        pass_test "  photo_id match: $DL_PHOTO_ID ✓"
    else
        fail_test "  photo_id mismatch: got $DL_PHOTO_ID, expected $GS_PID1"
    fi
else
    echo -e "${YELLOW}  ⚠ Skip download test — GS_PID1 tidak tersedia${RESET}"; echo ""
fi

# Foto belum final (cross session yang tidak di-complete) → 404
if [ -n "$CROSS_TOKEN" ]; then
    CROSS_BODY=$(php -r "echo sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cross_upload_$$.json';")
    WIN_CROSS=$(win_path "$CROSS_BODY")
    WIN_IMG2=$(win_path "$TEST_IMAGE")
    php -r "
\$b64 = base64_encode(file_get_contents('$WIN_IMG2'));
file_put_contents('$WIN_CROSS', json_encode([
    'session_token' => '$CROSS_TOKEN',
    'photo'         => \$b64,
    'shot_number'   => 1,
]));
" 2>/dev/null
    CROSS_UP_R=$(curl -s -X POST "$BASE/booth/upload-photo" \
        -H "Content-Type: application/json" \
        --data "@$CROSS_BODY")
    CROSS_PID=$(jget "$CROSS_UP_R" "data.photo_id")
    rm -f "$CROSS_BODY"

    if [ -n "$CROSS_PID" ]; then
        run_test "GET /download/{id}: foto belum final → 404" 404 \
            -X GET "$BASE/download/$CROSS_PID"
    fi
fi

run_test "GET /download/{id}: ID tidak ada → 404" 404 \
    -X GET "$BASE/download/99999999"

# ── Ringkasan ──────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
TOTAL=$((PASS + FAIL))
if [ "$FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}  SHARE: $PASS/$TOTAL passed ✓${RESET}"
else
    echo -e "${BOLD}${RED}  SHARE: $PASS/$TOTAL passed, $FAIL failed ✗${RESET}"
fi
echo -e "${BOLD}${CYAN}══════════════════════════════════════════${RESET}"
echo ""
exit $FAIL
