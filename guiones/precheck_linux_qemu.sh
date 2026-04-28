#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ANTLR_JAR_DEFAULT="$ROOT_DIR/herramientas/antlr-4.13.2-complete.jar"
ANTLR_JAR="${ANTLR_JAR:-$ANTLR_JAR_DEFAULT}"

FALTANTES=0

check_cmd() {
  local cmd="$1"
  local paquete="$2"
  if command -v "$cmd" >/dev/null 2>&1; then
    echo "[ok] $cmd"
  else
    echo "[error] Falta $cmd (instalar paquete sugerido: $paquete)"
    FALTANTES=1
  fi
}

echo "[info] Verificando entorno Linux para validación con QEMU..."

if [[ "${OSTYPE:-}" != linux* ]] && [[ "$(uname -s)" != "Linux" ]]; then
  echo "[warn] Este script está orientado a Linux. Sistema detectado: $(uname -s)"
fi

check_cmd php "php-cli"
check_cmd composer "composer"
check_cmd java "openjdk-17-jre-headless"
check_cmd aarch64-linux-gnu-as "binutils-aarch64-linux-gnu"
check_cmd aarch64-linux-gnu-ld "binutils-aarch64-linux-gnu"
check_cmd qemu-aarch64 "qemu-user"

if [[ -f "$ANTLR_JAR" ]]; then
  echo "[ok] ANTLR jar: $ANTLR_JAR"
else
  echo "[error] No se encontró ANTLR jar en: $ANTLR_JAR"
  echo "       Define ANTLR_JAR=/ruta/antlr-4.13.2-complete.jar o copia el jar en herramientas/."
  FALTANTES=1
fi

if [[ $FALTANTES -ne 0 ]]; then
  echo
  echo "[error] Entorno incompleto para validación final."
  echo "        Ubuntu/Debian sugerido:"
  echo "        sudo apt update && sudo apt install -y php-cli composer openjdk-17-jre-headless binutils-aarch64-linux-gnu qemu-user"
  exit 1
fi

echo "[ok] Entorno listo para validación Linux/QEMU."
