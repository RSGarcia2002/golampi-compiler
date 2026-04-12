const editor = document.getElementById('editor');
const consola = document.getElementById('consola');
const resumen = document.getElementById('resumen');

const btnNuevo = document.getElementById('btn-nuevo');
const btnCargar = document.getElementById('btn-cargar');
const btnGuardar = document.getElementById('btn-guardar');
const btnCompilar = document.getElementById('btn-compilar');
const btnLimpiar = document.getElementById('btn-limpiar');

const btnVerErrores = document.getElementById('btn-ver-errores');
const btnVerTabla = document.getElementById('btn-ver-tabla');
const btnDescargarAsm = document.getElementById('btn-descargar-asm');

const inputCargarArchivo = document.getElementById('input-cargar-archivo');

const modalErrores = document.getElementById('modal-errores');
const contenidoErrores = document.getElementById('contenido-errores');
const btnCerrarErrores = document.getElementById('btn-cerrar-errores');

const modalTabla = document.getElementById('modal-tabla');
const contenidoTabla = document.getElementById('contenido-tabla');
const btnCerrarTabla = document.getElementById('btn-cerrar-tabla');

let ultimoResultado = null;

const plantillaNueva = `package main

func main() {
  var x int = 1;
  fmt.Println(x);
}`;

function escribirConsola(texto) {
  consola.textContent = texto;
}

function escribirResumen(texto) {
  resumen.textContent = texto;
}

function descargarArchivo(nombre, contenido, tipo) {
  const blob = new Blob([contenido], { type });
  const url = URL.createObjectURL(blob);
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombre;
  document.body.appendChild(enlace);
  enlace.click();
  enlace.remove();
  URL.revokeObjectURL(url);
}

function mostrarErrores() {
  if (!ultimoResultado) {
    contenidoErrores.textContent = 'Sin datos de análisis.';
  } else {
    const payload = {
      ok: ultimoResultado.ok,
      errores: ultimoResultado.errors ?? [],
      errores_semanticos: ultimoResultado.semantic_errors ?? [],
    };
    contenidoErrores.textContent = JSON.stringify(payload, null, 2);
  }

  modalErrores.showModal();
}

function mostrarTabla() {
  if (!ultimoResultado || !ultimoResultado.symbol_table) {
    contenidoTabla.textContent = 'Sin tabla de símbolos disponible.';
  } else {
    contenidoTabla.textContent = JSON.stringify(ultimoResultado.symbol_table, null, 2);
  }

  modalTabla.showModal();
}

btnNuevo.addEventListener('click', () => {
  editor.value = plantillaNueva;
  ultimoResultado = null;
  escribirResumen('Editor reiniciado.');
  escribirConsola('Compila para ver el código ARM64 generado...');
});

btnCargar.addEventListener('click', () => {
  inputCargarArchivo.click();
});

inputCargarArchivo.addEventListener('change', async () => {
  const archivo = inputCargarArchivo.files?.[0];
  if (!archivo) {
    return;
  }

  const contenido = await archivo.text();
  editor.value = contenido;
  ultimoResultado = null;
  escribirResumen(`Archivo cargado: ${archivo.name}`);
  escribirConsola('Archivo cargado. Presiona Compilar.');
  inputCargarArchivo.value = '';
});

btnGuardar.addEventListener('click', () => {
  descargarArchivo('programa.gol', editor.value, 'text/plain;charset=utf-8');
  escribirResumen('Código guardado como programa.gol');
});

btnLimpiar.addEventListener('click', () => {
  escribirConsola('Consola limpia.');
});

btnCompilar.addEventListener('click', async () => {
  const source = editor.value;
  if (!source.trim()) {
    escribirResumen('No hay código para compilar.');
    escribirConsola('Ingresa código antes de compilar.');
    return;
  }

  escribirResumen('Compilando...');
  escribirConsola('Compilando...');

  try {
    const resp = await fetch('../backend/analizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ source, execute: true }),
    });

    const data = await resp.json();
    ultimoResultado = data;

    const totalErrores = Array.isArray(data.errors) ? data.errors.length : 0;
    const asmGenerado = data.arm64?.generado === true;
    const ejecucion = data.ejecucion ?? null;
    const ejecucionOk = ejecucion?.intentada === true && ejecucion?.ok === true;
    escribirResumen(
      `OK: ${data.ok ? 'sí' : 'no'} | errores: ${totalErrores} | ASM: ${asmGenerado ? 'sí' : 'no'} | Ejecutado: ${ejecucionOk ? 'sí' : 'no'}`
    );

    if (asmGenerado && typeof data.arm64?.contenido === 'string') {
      const secciones = [];
      if (ejecucion && ejecucion.mensaje) {
        const salida = typeof ejecucion.stdout === 'string' && ejecucion.stdout !== ''
          ? ejecucion.stdout
          : '(sin salida)';
        const stderr = typeof ejecucion.stderr === 'string' && ejecucion.stderr !== ''
          ? `\n\nstderr:\n${ejecucion.stderr}`
          : '';
        const codigo = ejecucion.codigo_salida !== null && ejecucion.codigo_salida !== undefined
          ? `\n\ncódigo de salida: ${ejecucion.codigo_salida}`
          : '';
        secciones.push(`== Ejecución ARM64 ==\n${ejecucion.mensaje}\n\nstdout:\n${salida}${stderr}${codigo}`);
      }

      secciones.push(`== Código ARM64 ==\n${data.arm64.contenido}`);
      escribirConsola(secciones.join('\n\n'));
      return;
    }

    if (totalErrores > 0) {
      const resumenErrores = (data.errors ?? [])
        .map((err) => `- [${err.type}] línea ${err.line}, col ${err.column}: ${err.description}`)
        .join('\n');
      escribirConsola(`Compilación con errores:\n${resumenErrores}`);
      return;
    }

    escribirConsola('Compilación finalizada sin ASM disponible.');
  } catch (error) {
    const mensaje = error instanceof Error ? error.message : String(error);
    escribirResumen('Error de conexión con backend.');
    escribirConsola(`Error de conexión: ${mensaje}`);
  }
});

btnVerErrores.addEventListener('click', mostrarErrores);
btnVerTabla.addEventListener('click', mostrarTabla);

btnDescargarAsm.addEventListener('click', () => {
  const asm = ultimoResultado?.arm64?.contenido;
  if (typeof asm !== 'string' || asm.trim() === '') {
    escribirResumen('No hay ASM para descargar.');
    return;
  }

  descargarArchivo('programa_fase4.s', asm, 'text/plain;charset=utf-8');
});

btnCerrarErrores.addEventListener('click', () => modalErrores.close());
btnCerrarTabla.addEventListener('click', () => modalTabla.close());
