# Golampi Compiler (Proyecto 2)

Compilador de **Golampi** con interfaz web, análisis léxico/sintáctico/semántico y generación de código **ARM64**.

## Estado actual
Fase 3 en progreso: semántica base completa y control de flujo inicial (`if`, `for`, `break`, `continue`).

## Estructura
- `docs/`: planificación, guía de commits, estado de avances.
- `reports/`: reportes del compilador (errores, tabla de símbolos, asm).
- `examples/`: entradas de prueba del lenguaje.
- `src/frontend/`: GUI (HTML/CSS/JS).
- `src/backend/`: punto de entrada backend PHP.
- `src/compiler/grammar/`: gramática ANTLR4.
- `src/compiler/generated/`: lexer/parser/visitor generados por ANTLR (no versionados).
- `src/compiler/semantic/`: validaciones semánticas.
- `src/compiler/codegen/`: generación ARM64.
- `src/compiler/symbols/`: tabla de símbolos.
- `src/compiler/errors/`: manejo de errores.
- `scripts/`: scripts utilitarios para build/test.

## Requisitos Fase 1
- Java 11+ (para ANTLR)
- PHP 8.1+
- Composer
- `antlr-4.x-complete.jar`

## Configuración rápida
1. Instalar dependencias PHP:
   ```bash
   composer install
   ```
2. Colocar ANTLR jar en `tools/antlr-4.13.2-complete.jar` o exportar `ANTLR_JAR`.
3. Generar lexer/parser/visitor:
   ```bash
   ./scripts/generate_antlr_php.sh
   ```
4. Probar parseo:
   ```bash
   ./scripts/run_phase1_demo.sh
   ```
5. Probar semántica y control de flujo:
   ```bash
   ./scripts/run_phase2_demo.sh
   ./scripts/run_phase3_demo.sh
   ```

## Uso manual del parser
Entrada por archivo:
```bash
php src/backend/parse.php examples/fase1_ok.gol
```

Entrada por STDIN:
```bash
echo 'package main\nfunc main() { var x int = 1; }' | php src/backend/parse.php
```

Salida de errores:
- Archivo: `reports/errors_phase1.json`
- Formato: `type`, `description`, `line`, `column`

Salida semántica:
- Archivo: `reports/semantic_errors_phase2.json`
- Tabla de símbolos: `reports/symbol_table_phase2.json`
- Validaciones activas: redeclaración en mismo ámbito, uso de identificadores no declarados, tipos en asignaciones/operaciones y contexto válido de `break/continue`

## Flujo de trabajo
1. Planificar fase.
2. Implementar alcance pequeño.
3. Probar local (cuando toque Linux, validar ahí).
4. Actualizar documentación de progreso.
5. Commit atómico con mensaje claro.

Ver detalle en `docs/PROJECT_PLAN.md` y `docs/COMMIT_GUIDELINES.md`.
