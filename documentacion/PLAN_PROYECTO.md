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
- [ ] Modelo de memoria (stack/heap)
- [~] Generación de .s por programa (esqueleto con funciones detectadas y salto a `main`)
- [~] Prólogo/epílogo de funciones (base por función emitida)
- [ ] Labels y saltos para control de flujo
- [ ] Llamadas a funciones y retorno

## Fase 5 - GUI y reportes
- [~] Editor, consola, barra de acciones (UI base implementada)
- [ ] Visualización/descarga ASM
- [ ] Visualización/descarga errores
- [ ] Visualización/descarga tabla de símbolos

## Fase 6 - Validación Linux
- [ ] Ensamblar y enlazar ARM64 en Linux
- [ ] Ejecutar con qemu-aarch64
- [ ] Suite de pruebas de aceptación
- [ ] Ajustes finales

## Criterio de "Done" por fase
- Código funcional
- Prueba mínima documentada
- Reporte actualizado (`documentacion/ESTADO_REPORTES.md`)
- Commit(s) atómicos y descriptivos
