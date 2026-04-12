# Estado de Reportes

## Última actualización
- Fecha: 2026-04-12
- Estado general: Fase 3 en progreso

## Reporte de Errores
- Estado: Implementado (léxico/sintáctico)
- Archivo generado: `reports/errors_phase1.json`
- Formato: `type`, `description`, `line`, `column`
- Fuente: `src/backend/parse.php`

## Tabla de Símbolos
- Estado: Implementado (Fase 2 base)
- Archivo generado: `reports/symbol_table_phase2.json`
- Formato: scopes + symbols (id, tipo, ámbito, línea, columna)

## Reporte Semántico
- Estado: Implementado (declaración/uso + redeclaración + chequeo de tipos)
- Archivo generado: `reports/semantic_errors_phase2.json`
- Fuente: `src/compiler/semantic/SemanticAnalyzer.php`

## Código ARM64 (.s)
- Estado: Pendiente de implementación (Fase 4)
- Salida objetivo: archivo ensamblador descargable y visible en GUI

## Notas
- Gramática base creada en `src/compiler/grammar/Golampi.g4`.
- Script de generación ANTLR listo en `scripts/generate_antlr_php.sh`.
- Semántica actual: tabla de símbolos con ámbitos, validación de uso/declaración y reglas de tipos en expresiones/asignaciones.
- Control de flujo activo: `if`, `for`, `break`, `continue` con validaciones semánticas de contexto y condición booleana.
