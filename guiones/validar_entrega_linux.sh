#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "[info] Paso 1/5: precheck Linux/QEMU"
"$ROOT_DIR/guiones/precheck_linux_qemu.sh"

echo "[info] Paso 2/5: composer install"
composer install --working-dir "$ROOT_DIR" --no-interaction --prefer-dist

echo "[info] Paso 3/5: generar ANTLR"
"$ROOT_DIR/guiones/generar_antlr_php.sh"

echo "[info] Paso 4/5: suite de aceptación"
"$ROOT_DIR/guiones/ejecutar_suite_aceptacion.sh"

echo "[info] Paso 5/5: ensamblar + ejecutar ARM64 en QEMU"
"$ROOT_DIR/guiones/probar_arm64_linux.sh"

echo "[ok] Validación final completada. Proyecto listo para calificación en Linux/QEMU."
