# Golampi Compiler (Proyecto 2)

Compilador de **Golampi** con interfaz web, análisis léxico/sintáctico/semántico y generación de código **ARM64**.

## Estado actual
Proyecto cerrado por fases: análisis léxico/sintáctico/semántico, funciones (incluyendo retornos múltiples), arreglos (incluyendo tipados y multidimensionales), built-ins (`fmt.Println`, `len`, `now`, `substr`, `typeOf`) y generación ARM64 desde AST.

## Estructura
- `documentacion/`: planificación, guía de commits, estado de avances.
- `reportes/`: reportes del compilador (errores, tabla de símbolos, asm).
- `ejemplos/`: entradas de prueba del lenguaje.
- `fuente/frontend/`: GUI (HTML/CSS/JS).
- `fuente/backend/`: punto de entrada backend PHP.
- `fuente/compilador/gramatica/`: gramática ANTLR4.
- `fuente/compilador/generado/`: lexer/parser/visitor generados por ANTLR (no versionados).
- `fuente/compilador/semantica/`: validaciones semánticas.
- `fuente/compilador/generacion_codigo/`: generación ARM64.
- `fuente/compilador/simbolos/`: tabla de símbolos.
- `fuente/compilador/errores/`: manejo de errores.
- `guiones/`: scripts utilitarios para build/test.

## Requisitos Fase 1
- Java 11+ (para ANTLR)
- PHP 8.1+
- Composer
- `antlr-4.x-complete.jar`

## Configuración rápida
1. Instalar dependencias PHP:
   ```bash
   composer install
   ```
2. Colocar ANTLR jar en `herramientas/antlr-4.13.2-complete.jar` o exportar `ANTLR_JAR`.
3. Generar lexer/parser/visitor:
   ```bash
   ./guiones/generar_antlr_php.sh
   ```
4. Probar parseo:
   ```bash
   ./guiones/ejecutar_demo_fase1.sh
   ```
5. Probar semántica y control de flujo:
   ```bash
   ./guiones/ejecutar_demo_fase2.sh
   ./guiones/ejecutar_demo_fase3.sh
   ```
6. Ejecutar suite completa local:
   ```bash
   ./guiones/ejecutar_suite_aceptacion.sh
   ```
   Esta suite incluye demos por fase y validación de `ejemplos/aceptacion_*.gol`.
7. Probar ensamblado/ejecución ARM64 en Linux (si tienes toolchain):
   ```bash
   ./guiones/probar_arm64_linux.sh
   ```
9. Validación final para calificación en Linux/QEMU:
   ```bash
   ./guiones/precheck_linux_qemu.sh
   ./guiones/validar_entrega_linux.sh
   ```
8. (Opcional) Levantar interfaz web local:
   ```bash
   php -S 127.0.0.1:8080 -t fuente
   ```
   Abrir en navegador: `http://127.0.0.1:8080/frontend/`
   Incluye: carga de ejemplos, análisis, resumen de estado y descarga de `errores_fase1.json`, `tabla_simbolos_fase2.json` y `programa_fase4.s` cuando aplique.

## Uso manual del parser
Entrada por archivo:
```bash
php fuente/backend/analizar.php ejemplos/fase1_ok.gol
```

Entrada por STDIN:
```bash
echo 'package main\nfunc main() { var x int = 1; }' | php fuente/backend/analizar.php
```

Salida de errores:
- Archivo: `reportes/errores_fase1.json`
- Formato: `type`, `description`, `line`, `column`

Salida semántica:
- Archivo: `reportes/errores_semanticos_fase2.json`
- Tabla de símbolos: `reportes/tabla_simbolos_fase2.json`
- Validaciones activas: redeclaración en mismo ámbito, uso de identificadores no declarados, tipos en asignaciones/operaciones, contexto válido de `break/continue`, compatibilidad de `case` en `switch`, protección de `const`, aridad/tipos de llamadas y retorno tipado de funciones.
- Extras Fase 3: validación semántica de arreglos (`[]T` y literales homogéneos) y built-ins `now`/`substr`.

Salida ARM64 (fase 4 base avanzada):
- Archivo: `reportes/programa_fase4.s`
- Estado: generación desde AST cuando el análisis no tiene errores, con `_start`, funciones compiladas, variables locales en stack, control de flujo y manejo de arreglos en heap.

## Flujo de trabajo
1. Planificar fase.
2. Implementar alcance pequeño.
3. Probar local (cuando toque Linux, validar ahí).
4. Actualizar documentación de progreso.
5. Commit atómico con mensaje claro.

## Linux / QEMU (calificación)
Comando recomendado en la laptop Linux:

```bash
./guiones/validar_entrega_linux.sh
```

Si falta toolchain, instalar en Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y php-cli composer openjdk-17-jre-headless binutils-aarch64-linux-gnu qemu-user
```

Ver detalle en `documentacion/PLAN_PROYECTO.md` y `documentacion/GUIA_COMMITS.md`.

## Documentación formal
- Manual de Usuario: `documentacion/MANUAL_USUARIO.md`
- Manual Técnico: `documentacion/MANUAL_TECNICO.md`
