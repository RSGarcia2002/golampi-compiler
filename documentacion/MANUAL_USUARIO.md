# Manual de Usuario - Golampi Compiler

## 1. Objetivo
Golampi Compiler permite:
- Escribir código Golampi en una interfaz web.
- Compilar y ver salida JSON (errores, tabla de símbolos y ARM64).
- Descargar reportes.
- Ejecutar pruebas desde terminal.

## 2. Requisitos
- PHP 8.1+
- Composer
- Java 11+ (para ANTLR)
- ANTLR jar (`herramientas/antlr-4.13.2-complete.jar`)

Para ejecución ARM64 en Linux:
- `binutils-aarch64-linux-gnu`
- `qemu-user`

## 3. Instalación rápida
Desde la raíz del proyecto:

```bash
composer install
./guiones/generar_antlr_php.sh
```

## 4. Uso por Interfaz Web (GUI)
1. Levantar servidor:
```bash
php -S 127.0.0.1:8080 -t fuente
```
2. Abrir:
`http://127.0.0.1:8080/frontend/`

### Botones principales
- `Nuevo`: limpia el editor con plantilla base.
- `Cargar`: carga archivo `.gol` o `.txt`.
- `Guardar`: descarga el contenido del editor.
- `Compilar`: envía código al backend y actualiza consola/reportes.
- `Limpiar Consola`: limpia texto de consola.

### Panel de reportes
- `Ver Errores`: muestra errores léxicos/sintácticos/semánticos.
- `Ver Tabla de Símbolos`: muestra scopes y símbolos detectados.
- `Descargar ARM64`: descarga `programa_fase4.s` si fue generado.

## 5. Uso por Terminal (CLI)
Compilar archivo:
```bash
php fuente/backend/analizar.php ejemplos/aceptacion_basico_v1.gol
```

Compilar con intento de ejecución ARM64 (Linux):
```bash
php fuente/backend/analizar.php ejemplos/aceptacion_basico_v1.gol --ejecutar
```

Compilar por entrada estándar:
```bash
echo 'package main
func main(){ var x int = 1; fmt.Println(x); }' | php fuente/backend/analizar.php
```

## 6. Reportes generados
- `reportes/errores_fase1.json`
- `reportes/errores_semanticos_fase2.json`
- `reportes/tabla_simbolos_fase2.json`
- `reportes/programa_fase4.s`

## 7. Suite de pruebas
Ejecutar pruebas del proyecto:
```bash
./guiones/ejecutar_suite_aceptacion.sh
```

Prueba específica de Linux ARM64:
```bash
./guiones/probar_arm64_linux.sh
```

## 8. Solución de problemas
- Error de runtime ANTLR:
  ejecutar `composer install`.
- No existen `GolampiLexer/GolampiParser`:
  ejecutar `./guiones/generar_antlr_php.sh`.
- No ejecuta ARM64:
  en Linux instalar `binutils-aarch64-linux-gnu` y `qemu-user`.
- En GUI aparece 404 en `/`:
  abrir `http://127.0.0.1:8080/frontend/` (no la raíz).
