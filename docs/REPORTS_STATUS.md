# Estado de Reportes

## Última actualización
- Fecha: 2026-04-11
- Estado general: Fase 1 parcial completada

## Reporte de Errores
- Estado: Implementado (léxico/sintáctico)
- Archivo generado: `reports/errors_phase1.json`
- Formato: `type`, `description`, `line`, `column`
- Fuente: `src/backend/parse.php`

## Tabla de Símbolos
- Estado: Pendiente de implementación (Fase 2)
- Formato objetivo: identificador, tipo, ámbito, valor, línea, columna

## Código ARM64 (.s)
- Estado: Pendiente de implementación (Fase 4)
- Salida objetivo: archivo ensamblador descargable y visible en GUI

## Notas
- Gramática base creada en `src/compiler/grammar/Golampi.g4`.
- Script de generación ANTLR listo en `scripts/generate_antlr_php.sh`.
- Pendiente ejecutar generación real de parser/lexer en entorno con ANTLR + PHP.
