#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"$ROOT_DIR/guiones/generar_antlr_php.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase1.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase2.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase3.sh"

php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_arreglos_builtins_ok.gol" >/dev/null

if command -v aarch64-linux-gnu-as >/dev/null 2>&1 \
  && command -v aarch64-linux-gnu-ld >/dev/null 2>&1 \
  && command -v qemu-aarch64 >/dev/null 2>&1; then
  "$ROOT_DIR/guiones/probar_arm64_linux.sh"
else
  echo "[warn] Herramientas ARM64/QEMU no disponibles. Se omite prueba de ejecución ARM64."
fi

echo "[ok] Suite de aceptación completada."
