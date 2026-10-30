#!/usr/bin/env bash
# Avvia DOMUS con il server PHP integrato su database temporanei ed esegue i test dell'API.
# Uso:  bash tests/run.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PIDS=()
DIRS=()
cleanup() { for p in "${PIDS[@]}"; do kill "$p" 2>/dev/null || true; done; rm -rf "${DIRS[@]}"; }
trap cleanup EXIT

serve() { # $1 = porta, $2 = cartella dati, $3 = fuso orario dello studio
  # TZ=UTC: il server "di sistema" è in UTC, come capita spesso sugli hosting.
  TZ=UTC DOMUS_DATA_DIR="$2" DOMUS_TIMEZONE="$3" php -S 127.0.0.1:$1 -t . >"$2/server.log" 2>&1 &
  PIDS+=($!)
  for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$1/index.php" && break; sleep 0.1; done
}

# Per il test del fuso serve uno studio la cui data, adesso, sia diversa da quella UTC
# (come a Roma tra mezzanotte e l'1): UTC-12 la mattina UTC, UTC+14 dalle 11 UTC in poi.
FUSO=$(php -r 'echo gmdate("G") <= 10 ? "Etc/GMT+12" : "Pacific/Kiritimati";')

A=$(mktemp -d); B=$(mktemp -d); C=$(mktemp -d); DIRS+=("$A" "$B" "$C")
serve 8765 "$A" Europe/Rome
serve 8766 "$B" Europe/Rome
serve 8767 "$C" "$FUSO"
status=0
php tests/api_test.php "http://127.0.0.1:8765" Europe/Rome || status=1
php tests/demo_test.php "http://127.0.0.1:8766" Europe/Rome || status=1
php tests/fuso_test.php "http://127.0.0.1:8767" "$FUSO" || status=1
exit $status
