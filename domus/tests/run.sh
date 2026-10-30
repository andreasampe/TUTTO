#!/usr/bin/env bash
# Avvia DOMUS con il server PHP integrato su un database temporaneo ed esegue i test dell'API.
# Uso:  bash tests/run.sh
set -euo pipefail
cd "$(dirname "$0")/.."

DATA=$(mktemp -d)
PORT=${PORT:-8765}
trap 'kill $PID 2>/dev/null; rm -rf "$DATA"' EXIT

DOMUS_DATA_DIR="$DATA" php -S 127.0.0.1:$PORT -t . >"$DATA/server.log" 2>&1 &
PID=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PORT/index.php" && break; sleep 0.1; done

php tests/api_test.php "http://127.0.0.1:$PORT"
