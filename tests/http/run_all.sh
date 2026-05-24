#!/usr/bin/env bash
# SnapBooth — master test runner
# Jalankan: bash tests/http/run_all.sh
#
# Apa yang dilakukan script ini:
#   1. Pastikan server berjalan di localhost:8000
#   2. Generate test.png (untuk upload test) jika belum ada
#   3. Jalankan auth.sh, events.sh, templates.sh secara berurutan
#   4. Tampilkan ringkasan keseluruhan

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE="http://localhost:8000/api/v1"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

# ── 1. Cek server aktif ────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}${CYAN}║   SnapBooth API — Full Test Suite        ║${RESET}"
echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${RESET}"
echo ""

echo -e "${YELLOW}→ Memeriksa koneksi ke $BASE/ping...${RESET}"
PING=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/ping" 2>/dev/null)

if [ "$PING" != "200" ]; then
    echo -e "${RED}✗ Server tidak merespons (HTTP $PING).${RESET}"
    echo -e "${YELLOW}  Jalankan terlebih dahulu:${RESET}"
    echo -e "  php artisan serve"
    echo ""
    exit 1
fi

echo -e "${GREEN}✓ Server aktif (HTTP 200)${RESET}"
echo ""

# ── 2. Generate test.png ───────────────────────────────────────────────────────
TEST_PNG="$SCRIPT_DIR/test.png"
if [ ! -f "$TEST_PNG" ]; then
    echo -e "${YELLOW}→ Generate test.png via PHP GD...${RESET}"
    php -r "
\$img = imagecreatetruecolor(300, 400);
\$bg  = imagecolorallocate(\$img, 255, 165, 0);
\$txt = imagecolorallocate(\$img, 255, 255, 255);
imagefill(\$img, 0, 0, \$bg);
imagestring(\$img, 5, 80, 180, 'SnapBooth Test', \$txt);
imagepng(\$img, '$TEST_PNG');
imagedestroy(\$img);
" 2>/dev/null

    if [ -f "$TEST_PNG" ]; then
        echo -e "${GREEN}✓ test.png berhasil dibuat ($(du -b "$TEST_PNG" | cut -f1) bytes)${RESET}"
    else
        echo -e "${YELLOW}⚠ PHP GD tidak tersedia, test upload akan di-skip secara otomatis.${RESET}"
    fi
    echo ""
fi

# ── 3. Jalankan test suite ─────────────────────────────────────────────────────
TOTAL_PASS=0
TOTAL_FAIL=0

run_suite() {
    local name="$1" script="$2"
    echo -e "${BOLD}${CYAN}▶ Menjalankan $name...${RESET}"
    echo ""

    bash "$SCRIPT_DIR/$script"
    local exit_code=$?

    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}  → $name: semua passed ✓${RESET}"
    else
        echo -e "${RED}  → $name: ada yang failed ($exit_code) ✗${RESET}"
        TOTAL_FAIL=$((TOTAL_FAIL + exit_code))
    fi
    echo ""
}

run_suite "AUTH"      "auth.sh"
run_suite "EVENTS"    "events.sh"
run_suite "TEMPLATES" "templates.sh"
run_suite "SHARE"     "share.sh"

# ── 4. Ringkasan keseluruhan ──────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${RESET}"
if [ "$TOTAL_FAIL" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}║   SEMUA TEST PASSED ✓                    ║${RESET}"
else
    echo -e "${BOLD}${RED}║   ADA TEST YANG FAILED ($TOTAL_FAIL suite) ✗              ║${RESET}"
fi
echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${RESET}"
echo ""

exit $TOTAL_FAIL
