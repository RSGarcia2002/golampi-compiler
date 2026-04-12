#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase1_ok.gol"
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase2_error_semantico.gol" || true
php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase2_error_tipos.gol" || true

echo "[ok] Demo Fase 2 ejecutada."
echo "     Revisa reportes/errores_semanticos_fase2.json y reportes/tabla_simbolos_fase2.json"
