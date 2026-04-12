#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_control_ok.gol"
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_error_control.gol" || true
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_switch_const_ok.gol"
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_switch_const_error.gol" || true
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_funciones_ok.gol"
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_funciones_error.gol" || true

echo "[ok] Demo Fase 3 ejecutada."
echo "     Revisa reportes/errores_fase1.json y reportes/errores_semanticos_fase2.json"
