# Golampi Compiler (Proyecto 2)

Compilador de **Golampi** con interfaz web, análisis léxico/sintáctico/semántico y generación de código **ARM64**.

## Objetivo
Construir el proyecto de forma profesional, ordenada y trazable para luego validarlo en Linux.

## Estructura
- `docs/`: planificación, guía de commits, estado de avances.
- `reports/`: reportes del compilador (errores, tabla de símbolos, asm).
- `examples/`: entradas de prueba del lenguaje.
- `src/frontend/`: GUI (HTML/CSS/JS).
- `src/backend/`: punto de entrada backend PHP.
- `src/compiler/grammar/`: gramática ANTLR4.
- `src/compiler/semantic/`: validaciones semánticas.
- `src/compiler/codegen/`: generación ARM64.
- `src/compiler/symbols/`: tabla de símbolos.
- `src/compiler/errors/`: manejo de errores.
- `scripts/`: scripts utilitarios para build/test.

## Flujo de trabajo
1. Planificar fase.
2. Implementar alcance pequeño.
3. Probar local (cuando toque Linux, validar ahí).
4. Actualizar documentación de progreso.
5. Commit atómico con mensaje claro.

Ver detalle en `docs/PROJECT_PLAN.md` y `docs/COMMIT_GUIDELINES.md`.
