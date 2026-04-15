# Manual Técnico - Golampi Compiler

## 1. Resumen técnico
Proyecto de compilador para Golampi con:
- Frontend web (HTML/CSS/JS).
- Backend PHP para análisis y reportes.
- Gramática ANTLR4.
- Análisis semántico con tabla de símbolos por ámbitos.
- Generación de código ARM64 (`.s`).

## 2. Arquitectura
- `fuente/frontend/`
  - `index.html`: layout GUI
  - `estilos.css`: estilos
  - `app.js`: integración con backend y reportes
- `fuente/backend/analizar.php`
  - API principal (HTTP/CLI)
  - parseo, semántica, codegen ARM64
  - respuesta JSON consolidada
- `fuente/compilador/gramatica/Golampi.g4`
  - reglas léxicas/sintácticas del lenguaje
- `fuente/compilador/semantica/AnalizadorSemantico.php`
  - validaciones semánticas
  - control de tipos
  - tabla de símbolos
- `fuente/compilador/generacion_codigo/GeneradorARM64.php`
  - ensamblador ARM64 generado desde AST
- `fuente/compilador/simbolos/TablaSimbolos.php`
  - scopes y símbolos

## 3. Flujo de compilación
1. Entrada de código (GUI, archivo o STDIN).
2. Lexer/parser ANTLR.
3. Recolección de errores sintácticos.
4. Análisis semántico.
5. Si no hay errores: generación ARM64.
6. Escritura de reportes JSON y `.s`.
7. (Linux opcional) ensamblado/enlazado/ejecución con QEMU.

## 4. Contrato de salida JSON (backend)
Campos principales:
- `ok`: estado global.
- `errors`: errores léxicos/sintácticos/semánticos agregados.
- `semantic_errors`: errores semánticos.
- `symbol_table`: scopes + símbolos.
- `arm64`:
  - `generado` (bool)
  - `archivo`
  - `contenido`
- `ejecucion`:
  - `intentada`, `disponible`, `ok`
  - `stdout`, `stderr`, `codigo_salida`, `mensaje`
- `reportes`: rutas de archivos generados.

## 5. Funcionalidades implementadas (alto nivel)
- Variables: larga/corta, múltiples, constantes, nil.
- Operadores: aritméticos, relacionales, lógicos, asignación compuesta.
- Control: `if`, `if else`, `switch`, `for`, `break`, `continue`.
- Funciones: parámetros, recursión, retornos múltiples, hoisting semántico.
- Built-ins: `fmt.Println`, `len`, `now`, `substr`, `typeOf`.
- Arreglos: 1D y multidimensionales, indexación y actualización.
- Casts base: `int(...)`, `float(...)`, `bool(...)`, `string(...)`, `rune(...)`.

## 6. Generación ARM64
- Entrada de programa en `.section .text` con `_start`.
- Prólogo/epílogo por función.
- Stack frame local base.
- Labels de control para condicionales/bucles.
- Runtime auxiliar para impresión (`_golampi_print_*`).
- Secciones `.rodata` y `.bss` con heap base/end.

Nota: algunas operaciones complejas siguen con aproximaciones/stubs en backend base, pero el flujo de análisis y generación está integrado y estable para la suite de aceptación del proyecto.

## 7. Build y pruebas
Generar ANTLR:
```bash
./guiones/generar_antlr_php.sh
```

Ejecutar demos:
```bash
./guiones/ejecutar_demo_fase1.sh
./guiones/ejecutar_demo_fase2.sh
./guiones/ejecutar_demo_fase3.sh
```

Suite completa:
```bash
./guiones/ejecutar_suite_aceptacion.sh
```

Linux ARM64:
```bash
./guiones/probar_arm64_linux.sh
```

## 8. Mantenimiento recomendado
- Mantener commits atómicos por módulo.
- Regenerar ANTLR después de cambios en `Golampi.g4`.
- Actualizar `documentacion/ESTADO_REPORTES.md` al cerrar cambios grandes.
- Conservar pruebas de aceptación en `ejemplos/`.
