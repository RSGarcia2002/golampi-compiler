#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GRAMMAR_FILE="$ROOT_DIR/src/compiler/grammar/Golampi.g4"
OUTPUT_DIR="$ROOT_DIR/src/compiler/generated"

ANTLR_JAR="${ANTLR_JAR:-$ROOT_DIR/tools/antlr-4.13.2-complete.jar}"

if [[ ! -f "$ANTLR_JAR" ]]; then
  echo "[error] No se encontro ANTLR jar en: $ANTLR_JAR"
  echo "        Define ANTLR_JAR=/ruta/antlr-4.x-complete.jar o coloca el jar en tools/."
  exit 1
fi

rm -rf "$OUTPUT_DIR"
mkdir -p "$OUTPUT_DIR"

java -jar "$ANTLR_JAR" \
  -Dlanguage=PHP \
  -visitor \
  -no-listener \
  -o "$OUTPUT_DIR" \
  "$GRAMMAR_FILE"

echo "[ok] Archivos ANTLR generados en: $OUTPUT_DIR"
