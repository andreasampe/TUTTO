#!/usr/bin/env bash
# Avvia DOMUS con il server PHP integrato su database temporanei ed esegue i test dell'API.
# Uso:  bash tests/run.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PIDS=()
DIRS=()
cleanup() { for p in "${PIDS[@]}"; do kill "$p" 2>/dev/null || true; done; rm -rf "${DIRS[@]}"; }
trap cleanup EXIT

serve() { # $1 = porta, $2 = cartella dati
  DOMUS_DATA_DIR="$2" php -S 127.0.0.1:$1 -t . >"$2/server.log" 2>&1 &
  PIDS+=($!)
  for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$1/index.php" && break; sleep 0.1; done
}

A=$(mktemp -d); B=$(mktemp -d); DIRS+=("$A" "$B")
serve 8765 "$A"
serve 8766 "$B"
status=0
php tests/api_test.php "http://127.0.0.1:8765" || status=1
php tests/demo_test.php "http://127.0.0.1:8766" || status=1
exit $status
