# Guía de Commits

## Convención
`<tipo>(<alcance>): <resumen>`

Tipos sugeridos:
- `feat`: nueva funcionalidad
- `fix`: corrección
- `refactor`: mejora interna sin cambiar comportamiento
- `docs`: documentación
- `test`: pruebas
- `chore`: tareas de mantenimiento

## Reglas
- Un commit = un cambio lógico.
- No mezclar frontend + backend + gramática en el mismo commit si no es necesario.
- Mensajes claros y en presente.

## Ejemplos
- `feat(grammar): add program and main function rules`
- `feat(semantic): add symbol table with scoped environments`
- `feat(codegen): generate arm64 for int32 arithmetic`
- `docs(progress): update phase 1 completion status`
