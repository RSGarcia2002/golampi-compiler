const editor = document.getElementById('editor');
const consola = document.getElementById('consola');
const btnAnalizar = document.getElementById('btn-analizar');
const btnLimpiar = document.getElementById('btn-limpiar');
const btnCargar = document.getElementById('btn-cargar');
const selector = document.getElementById('selector-ejemplo');
const resumen = document.getElementById('resumen');
const btnDescargarErrores = document.getElementById('btn-descargar-errores');
const btnDescargarTabla = document.getElementById('btn-descargar-tabla');
const btnDescargarAsm = document.getElementById('btn-descargar-asm');

let ultimoResultado = null;

const ejemplos = {
  fase1_ok: `package main

func main() {
  var x int = 1;
  fmt.Println(x);
}`,
  fase2_error_semantico: `package main

func main() {
  var a int = 10;
  var a int = 20;
  b = a + 1;
}`,
  fase3_funciones_ok: `package main

func suma(a int, b int) int {
  return a + b;
}

func main() {
  var x int = suma(2, 3);
  fmt.Println(typeOf(x), len("hola"));
}`,
  fase3_arreglos_builtins_ok: `package main

func sumaParcial(a int, b int) int {
  return a + b;
}

func main() {
  var numeros []int = [1, 2, 3, 4];
  var texto string = "golampi";
  var tramo string = substr(texto, 0, 3);
  var fecha string = now();
  var total int = len(numeros) + len(tramo);
  fmt.Println(typeOf(numeros), total, fecha, sumaParcial(2, 5));
}`,
  fase3_arreglos_builtins_error: `package main

func main() {
  var mezcla []int = [1, 2.5];
  var bandera bool = true;
  var largo int = len(bandera);
  var recorte string = substr("compiler", 1, false);
  fmt.Println(mezcla, largo, recorte);
}`,
};

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

btnCargar.addEventListener('click', () => {
  const key = selector.value;
  if (!key || !ejemplos[key]) {
    escribirConsola('Selecciona un ejemplo válido.');
    return;
  }
  editor.value = ejemplos[key];
  escribirConsola(`Ejemplo cargado: ${key}`);
});

btnLimpiar.addEventListener('click', () => {
  escribirConsola('Consola limpia.');
});

btnDescargarErrores.addEventListener('click', () => {
  if (!ultimoResultado) {
    escribirConsola('Aún no hay resultados para descargar.');
    return;
  }

  const contenido = JSON.stringify({
    ok: ultimoResultado.ok,
    errors: ultimoResultado.errors ?? [],
    semantic_errors: ultimoResultado.semantic_errors ?? [],
  }, null, 2);

  descargarArchivo('errores_fase1.json', contenido, 'application/json;charset=utf-8');
});

btnDescargarTabla.addEventListener('click', () => {
  if (!ultimoResultado || !ultimoResultado.symbol_table) {
    escribirConsola('Aún no hay tabla de símbolos para descargar.');
    return;
  }

  const contenido = JSON.stringify(ultimoResultado.symbol_table, null, 2);
  descargarArchivo('tabla_simbolos_fase2.json', contenido, 'application/json;charset=utf-8');
});

btnDescargarAsm.addEventListener('click', () => {
  const asm = ultimoResultado?.arm64?.contenido;
  if (typeof asm !== 'string' || asm.trim() === '') {
    escribirConsola('No hay ASM disponible (corrige errores y vuelve a analizar).');
    return;
  }

  descargarArchivo('programa_fase4.s', asm, 'text/plain;charset=utf-8');
});

btnAnalizar.addEventListener('click', async () => {
  const source = editor.value;
  if (!source.trim()) {
    escribirConsola('No hay código para analizar.');
    return;
  }

  escribirConsola('Analizando...');

  try {
    const resp = await fetch('../backend/analizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ source }),
    });

    const data = await resp.json();
    ultimoResultado = data;

    const totalErrores = Array.isArray(data.errors) ? data.errors.length : 0;
    const totalScopes = data.symbol_table?.total_scopes ?? 0;
    const totalSimbolos = data.symbol_table?.total_symbols ?? 0;
    const asmEstado = data.arm64?.generado ? 'sí' : 'no';
    escribirResumen(
      `OK: ${data.ok ? 'sí' : 'no'} | errores: ${totalErrores} | scopes: ${totalScopes} | símbolos: ${totalSimbolos} | ASM: ${asmEstado}`
    );
    escribirConsola(JSON.stringify(data, null, 2));
  } catch (error) {
    escribirConsola(`Error de conexión: ${error.message}`);
    escribirResumen('Error de conexión con backend.');
  }
});
