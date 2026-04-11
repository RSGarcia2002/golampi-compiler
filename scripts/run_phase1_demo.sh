#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase1_ok.gol"
php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase1_error.gol" || true

echo "[ok] Demo Fase 1 ejecutada. Revisa reports/errors_phase1.json"
