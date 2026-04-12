# Estado de Reportes

## Última actualización
- Fecha: 2026-04-12
- Estado general: Fase 3 avanzada + Fase 4 base extendida

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
- Estado: Implementado base extendida (Fase 4)
- Archivo generado: `reportes/programa_fase4.s`
- Salida actual: `_start` con salto a `main`, funciones detectadas desde tabla de símbolos, prólogo/epílogo base por función
- Salida objetivo pendiente: control de flujo, llamadas reales y manejo de stack/heap

## Notas
- Gramática base creada en `fuente/compilador/gramatica/Golampi.g4`.
- Script de generación ANTLR listo en `guiones/generar_antlr_php.sh`.
- Semántica actual: tabla de símbolos con ámbitos, validación de uso/declaración y reglas de tipos en expresiones/asignaciones.
- Control de flujo activo: `if`, `for`, `break`, `continue` con validaciones semánticas de contexto y condición booleana.
- Nuevos soportes Fase 3: `switch` con validación de tipos en `case`, y `const` con prohibición de reasignación.
- Funciones activas: parámetros tipados, llamadas con validación de aridad/tipos, retorno simple tipado.
- Built-ins activos: `fmt.Println`, `len`, `now`, `substr`, `typeOf`.
- Arreglos: soporte semántico para tipos `[]T` y literales (`[1,2,3]`) con validación de homogeneidad.
- GUI activa en `fuente/frontend/` con editor, consola, resumen de análisis y descargas directas de errores, tabla de símbolos y ASM.
