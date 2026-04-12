#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase1_ok.gol"
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase1_error.gol" || true

echo "[ok] Demo Fase 1 ejecutada. Revisa reportes/errores_fase1.json"
