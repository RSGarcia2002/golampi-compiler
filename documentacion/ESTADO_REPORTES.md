# Estado de Reportes

## Última actualización
- Fecha: 2026-04-28
- Estado general: Fases 1-6 cerradas (semántica + codegen + suite de aceptación)

## Reporte de Errores
- Estado: Implementado (léxico/sintáctico)
- Archivo generado: `reportes/errores_fase1.json`
- Formato: `type`, `description`, `line`, `column`
- Fuente: `fuente/backend/analizar.php`

## Tabla de Símbolos
- Estado: Implementado (Fase 2 base)
- Archivo generado: `reportes/tabla_simbolos_fase2.json`
- Formato: scopes + symbols (id, tipo, ámbito, línea, columna)

## Reporte Semántico
- Estado: Implementado (declaración/uso + redeclaración + chequeo de tipos)
- Archivo generado: `reportes/errores_semanticos_fase2.json`
- Fuente: `fuente/compilador/semantica/AnalizadorSemantico.php`

## Código ARM64 (.s)
- Estado: Implementado (Fase 4)
- Archivo generado: `reportes/programa_fase4.s`
- Salida actual: `_start` con salto a `main`, funciones compiladas desde AST, stack frame por función, variables locales en stack y operaciones aritméticas/lógicas.
- Salida actual (control): `if`/`for`/`switch` con labels/saltos, soporte de `break`/`continue`, llamadas a funciones y retorno.
- Salida actual (heap): arreglos literales y tipados (1D y multidimensionales) en heap con validación OOM.
- Salida actual (built-ins): `len`, `now`, `substr`, `typeOf` disponibles para compilación y generación de salida ASM en los escenarios cubiertos por aceptación.

## Notas
- Gramática base creada en `fuente/compilador/gramatica/Golampi.g4`.
- Script de generación ANTLR listo en `guiones/generar_antlr_php.sh`.
- Semántica actual: tabla de símbolos con ámbitos, validación de uso/declaración y reglas de tipos en expresiones/asignaciones.
- Control de flujo activo: `if`, `for`, `break`, `continue` con validaciones semánticas de contexto y condición booleana.
- Nuevos soportes Fase 3: `switch` con validación de tipos en `case`, y `const` con prohibición de reasignación.
- Funciones activas: parámetros tipados, llamadas con validación de aridad/tipos, retorno simple tipado.
- Built-ins activos: `fmt.Println`, `len`, `now`, `substr`, `typeOf`.
- Arreglos: soporte semántico para tipos `[]T` y literales (`[1,2,3]`) con validación de homogeneidad.
- Arreglos: soporte semántico para `[]T`, arreglos tipados `[N]T`, multidimensionales, indexación y asignación por índice.
- GUI activa en `fuente/frontend/` con estilo de panel (editor + consola ARM64), botones de reportes (`Ver Errores`, `Ver Tabla de Símbolos`, `Descargar ARM64`) y modales de visualización.
- Scripts Linux: `guiones/probar_arm64_linux.sh` y `guiones/ejecutar_suite_aceptacion.sh` para validación de ensamblado/ejecución ARM64 con QEMU.
- Suite de aceptación: `guiones/ejecutar_suite_aceptacion.sh` valida demos de fases y todos los `ejemplos/aceptacion_*.gol`.
