#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"$ROOT_DIR/guiones/generar_antlr_php.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase1.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase2.sh"
"$ROOT_DIR/guiones/ejecutar_demo_fase3.sh"

php "$ROOT_DIR/fuente/backend/analizar.php" "$ROOT_DIR/ejemplos/fase3_arreglos_builtins_ok.gol" >/dev/null

echo "[info] Ejecutando suite de aceptación amplia (aceptacion_*.gol)..."
shopt -s nullglob
for archivo in "$ROOT_DIR"/ejemplos/aceptacion_*.gol; do
  salida="$(php "$ROOT_DIR/fuente/backend/analizar.php" "$archivo" || true)"

  if ! php -r '
    $d = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($d)) { fwrite(STDERR, "JSON invalido\n"); exit(2); }
    $ok = ($d["ok"] ?? false) === true;
    $asm = ($d["arm64"]["generado"] ?? false) === true;
    if (!$ok || !$asm) {
      fwrite(STDERR, "Fallo de aceptación\n");
      foreach (($d["errors"] ?? []) as $err) {
        $tipo = $err["type"] ?? "error";
        $linea = $err["line"] ?? 0;
        $columna = $err["column"] ?? 0;
        $desc = $err["description"] ?? "";
        fwrite(STDERR, "  - [$tipo] L$linea:$columna $desc\n");
      }
      exit(1);
    }
  ' <<<"$salida"; then
    echo "[error] Falló: $(basename "$archivo")"
    exit 1
  fi

  echo "[ok] $(basename "$archivo")"
done

if command -v aarch64-linux-gnu-as >/dev/null 2>&1 \
  && command -v aarch64-linux-gnu-ld >/dev/null 2>&1 \
  && command -v qemu-aarch64 >/dev/null 2>&1; then
  "$ROOT_DIR/guiones/probar_arm64_linux.sh"
else
  echo "[warn] Herramientas ARM64/QEMU no disponibles. Se omite prueba de ejecución ARM64."
fi

echo "[ok] Suite de aceptación completada."
