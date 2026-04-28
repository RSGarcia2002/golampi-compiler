# Plan Maestro - Golampi Compiler

## Fase 0 - Preparación
- [x] Estructura base del proyecto
- [x] Checklist maestro y política de commits
- [x] Inicializar repositorio y primer commit

## Fase 1 - Base de compilador
- [x] Definir gramática ANTLR4 mínima (programa, main, sentencias básicas)
- [x] Generar lexer/parser/visitor
- [x] Integrar parser con backend PHP
- [x] Primer reporte de errores léxicos/sintácticos

## Fase 2 - Semántica
- [x] Tabla de símbolos (ámbitos)
- [x] Validación de declaración/uso de variables
- [x] Validación de tipos en asignación y operaciones
- [x] Reporte de errores semánticos

## Fase 3 - Lenguaje requerido
- [x] Variables, constantes, nil
- [x] Operadores aritméticos, relacionales, lógicos
- [x] if / switch / for
- [x] break / continue / return
- [x] Arreglos (1D y multidimensionales con indexación)
- [x] Funciones, parámetros, retornos múltiples, main
- [x] Built-ins: fmt.Println, len, now, substr, typeOf

## Fase 4 - Codegen ARM64
- [x] Modelo de memoria (stack + heap para arreglos)
- [x] Generación de .s por programa desde AST
- [x] Prólogo/epílogo de funciones
- [x] Labels y saltos para control de flujo (`if`/`for`/`switch`)
- [x] Llamadas a funciones y retorno

## Fase 5 - GUI y reportes
- [x] Editor, consola, barra de acciones
- [x] Visualización/descarga ASM
- [x] Visualización/descarga errores
- [x] Visualización/descarga tabla de símbolos

## Fase 6 - Validación Linux
- [x] Ensamblar y enlazar ARM64 en Linux (script: `guiones/probar_arm64_linux.sh`)
- [x] Ejecutar con qemu-aarch64 (cuando toolchain está disponible)
- [x] Suite de pruebas de aceptación (script: `guiones/ejecutar_suite_aceptacion.sh`)
- [x] Ajustes finales

## Criterio de "Done" por fase
- Código funcional
- Prueba mínima documentada
- Reporte actualizado (`documentacion/ESTADO_REPORTES.md`)
- Commit(s) atómicos y descriptivos
