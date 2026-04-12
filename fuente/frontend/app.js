const editor = document.getElementById('editor');
const consola = document.getElementById('consola');
const btnAnalizar = document.getElementById('btn-analizar');
const btnLimpiar = document.getElementById('btn-limpiar');
const btnCargar = document.getElementById('btn-cargar');
const selector = document.getElementById('selector-ejemplo');

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
};

function escribirConsola(texto) {
  consola.textContent = texto;
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
    escribirConsola(JSON.stringify(data, null, 2));
  } catch (error) {
    escribirConsola(`Error de conexión: ${error.message}`);
  }
});
