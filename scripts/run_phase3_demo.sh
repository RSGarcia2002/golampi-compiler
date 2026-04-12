#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase3_control_ok.gol"
php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase3_control_error.gol" || true

echo "[ok] Demo Fase 3 ejecutada."
echo "     Revisa reports/errors_phase1.json y reports/semantic_errors_phase2.json"
