#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase1_ok.gol"
php "$ROOT_DIR/src/backend/parse.php" "$ROOT_DIR/examples/fase2_semantic_error.gol" || true

echo "[ok] Demo Fase 2 ejecutada."
echo "     Revisa reports/semantic_errors_phase2.json y reports/symbol_table_phase2.json"
