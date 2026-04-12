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
- [ ] Variables, constantes, nil
- [ ] Operadores aritméticos, relacionales, lógicos
- [~] if / switch / for (if y for listos; switch pendiente)
- [~] break / continue / return (break y continue listos; return base ya soportado)
- [ ] Arreglos (incluye multidimensional)
- [ ] Funciones, parámetros, retornos múltiples, main
- [ ] Built-ins: fmt.Println, len, now, substr, typeOf

## Fase 4 - Codegen ARM64
- [ ] Modelo de memoria (stack/heap)
- [ ] Generación de .s por programa
- [ ] Prólogo/epílogo de funciones
- [ ] Labels y saltos para control de flujo
- [ ] Llamadas a funciones y retorno

## Fase 5 - GUI y reportes
- [ ] Editor, consola, barra de acciones
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
