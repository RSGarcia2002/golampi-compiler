# Estado de Reportes

## Última actualización
- Fecha: 2026-04-12
- Estado general: Fase 3 en progreso

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
- Estado: Implementado base (Fase 4 inicial)
- Archivo generado: `reportes/programa_fase4.s`
- Salida objetivo pendiente: generación completa por funciones/control de flujo

## Notas
- Gramática base creada en `fuente/compilador/gramatica/Golampi.g4`.
- Script de generación ANTLR listo en `guiones/generar_antlr_php.sh`.
- Semántica actual: tabla de símbolos con ámbitos, validación de uso/declaración y reglas de tipos en expresiones/asignaciones.
- Control de flujo activo: `if`, `for`, `break`, `continue` con validaciones semánticas de contexto y condición booleana.
- Nuevos soportes Fase 3: `switch` con validación de tipos en `case`, y `const` con prohibición de reasignación.
- Funciones activas: parámetros tipados, llamadas con validación de aridad/tipos, retorno simple tipado.
- Built-ins activos: `fmt.Println`, `len`, `typeOf`.
- GUI base activa en `fuente/frontend/` con editor, consola y acción de análisis vía `fetch` al backend.
