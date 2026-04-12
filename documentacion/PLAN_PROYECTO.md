# Plan Maestro - Golampi Compiler

## Fase 0 - Preparación
- [x] Estructura base del proyecto
- [x] Checklist maestro y política de commits
- [x] Inicializar repositorio y primer commit

## Fase 1 - Base de compilador
- [x] Definir gramática ANTLR4 mínima (programa, main, sentencias básicas)
- [~] Generar lexer/parser/visitor (script listo, pendiente ejecutar en entorno con ANTLR)
- [x] Integrar parser con backend PHP
- [x] Primer reporte de errores léxicos/sintácticos

## Fase 2 - Semántica
- [x] Tabla de símbolos (ámbitos)
- [x] Validación de declaración/uso de variables
- [x] Validación de tipos en asignación y operaciones
- [x] Reporte de errores semánticos

## Fase 3 - Lenguaje requerido
- [~] Variables, constantes, nil (variables y constantes listas; nil pendiente semántica dedicada)
- [x] Operadores aritméticos, relacionales, lógicos
- [x] if / switch / for
- [~] break / continue / return (break/continue listos; return con tipo simple listo)
- [~] Arreglos (literales y tipos `[]T` listos; multidimensional e indexación pendientes)
- [~] Funciones, parámetros, retornos múltiples, main (parámetros/llamadas/retorno simple listos; retornos múltiples pendientes)
- [x] Built-ins: fmt.Println, len, now, substr, typeOf

## Fase 4 - Codegen ARM64
- [~] Modelo de memoria (stack + heap 1D base para arreglos literales)
- [~] Generación de .s por programa (AST + instrucciones base para declaraciones/asignaciones/expresiones enteras)
- [~] Prólogo/epílogo de funciones (stack frame fijo base + etiqueta de salida por función)
- [~] Labels y saltos para control de flujo (`if`/`for`/`switch` base compilados con saltos)
- [~] Llamadas a funciones y retorno (llamadas básicas + retorno con salto a etiqueta de salida)

## Fase 5 - GUI y reportes
- [x] Editor, consola, barra de acciones
- [~] Visualización/descarga ASM (descarga directa desde respuesta backend)
- [~] Visualización/descarga errores (consola + descarga JSON)
- [~] Visualización/descarga tabla de símbolos (descarga JSON desde frontend)

## Fase 6 - Validación Linux
- [~] Ensamblar y enlazar ARM64 en Linux (script listo: `guiones/probar_arm64_linux.sh`)
- [~] Ejecutar con qemu-aarch64 (script listo, sujeto a toolchain instalada)
- [~] Suite de pruebas de aceptación (script listo: `guiones/ejecutar_suite_aceptacion.sh`)
- [ ] Ajustes finales

## Criterio de "Done" por fase
- Código funcional
- Prueba mínima documentada
- Reporte actualizado (`documentacion/ESTADO_REPORTES.md`)
- Commit(s) atómicos y descriptivos
