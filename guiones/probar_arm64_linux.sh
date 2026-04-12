#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ASM_FILE="$ROOT_DIR/reportes/programa_fase4.s"
OUT_DIR="$ROOT_DIR/reportes/linux"
OBJ_FILE="$OUT_DIR/programa_fase4.o"
BIN_FILE="$OUT_DIR/programa_fase4"

if [[ ! -f "$ASM_FILE" ]]; then
  echo "[error] No existe $ASM_FILE"
  echo "        Genera primero el ASM con: php fuente/backend/analizar.php ejemplos/fase3_arreglos_builtins_ok.gol"
  exit 1
fi

if ! command -v aarch64-linux-gnu-as >/dev/null 2>&1; then
  echo "[error] Falta aarch64-linux-gnu-as"
  echo "        Ubuntu/Debian: sudo apt install -y binutils-aarch64-linux-gnu"
  exit 1
fi

if ! command -v aarch64-linux-gnu-ld >/dev/null 2>&1; then
  echo "[error] Falta aarch64-linux-gnu-ld"
  echo "        Ubuntu/Debian: sudo apt install -y binutils-aarch64-linux-gnu"
  exit 1
fi

if ! command -v qemu-aarch64 >/dev/null 2>&1; then
  echo "[error] Falta qemu-aarch64"
  echo "        Ubuntu/Debian: sudo apt install -y qemu-user"
  exit 1
fi

mkdir -p "$OUT_DIR"

aarch64-linux-gnu-as "$ASM_FILE" -o "$OBJ_FILE"
aarch64-linux-gnu-ld "$OBJ_FILE" -o "$BIN_FILE"

set +e
qemu-aarch64 "$BIN_FILE"
EXIT_CODE=$?
set -e

echo "[ok] Binario ARM64 generado: $BIN_FILE"
echo "[ok] Ejecución en QEMU finalizada con código: $EXIT_CODE"
