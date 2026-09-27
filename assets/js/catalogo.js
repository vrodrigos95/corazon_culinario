// Carga el catálogo (data/productos.json) y calcula precios por variante.
// La misma lógica de precios vive en api/lib.php: si cambias una, cambia la otra.

let cache = null;

export async function cargarCatalogo() {
  if (!cache) {
    cache = fetch('data/productos.json', { cache: 'no-cache' }).then((r) => {
      if (!r.ok) throw new Error('No se pudo cargar el catálogo');
      return r.json();
    });
  }
  return cache;
}

export async function obtenerProducto(id) {
  const cat = await cargarCatalogo();
  return cat.productos.find((p) => p.id === id) || null;
}

export const tieneOpciones = (p) => Array.isArray(p.opciones) && p.opciones.length > 0;

// Selección por defecto: el primer valor de cada opción.
export function opcionesPorDefecto(p) {
  const sel = {};
  (p.opciones || []).forEach((o) => { sel[o.clave] = o.valores[0].id; });
  return sel;
}

// Normaliza una selección: descarta claves desconocidas y completa las faltantes.
export function normalizarOpciones(p, sel = {}) {
  const out = {};
  for (const o of p.opciones || []) {
    const v = o.valores.find((x) => x.id === sel[o.clave]) || o.valores[0];
    out[o.clave] = v.id;
  }
  return out;
}

function valoresElegidos(p, sel) {
  return (p.opciones || []).map((o) => ({
    opcion: o,
    valor: o.valores.find((v) => v.id === sel[o.clave]) || o.valores[0],
  }));
}

export function precioDe(p, sel = {}) {
  let precio = p.precio;
  for (const { valor } of valoresElegidos(p, sel)) {
    if (typeof valor.precio === 'number') precio = valor.precio;
  }
  return precio;
}

export function precioMinimo(p) {
  const precios = [p.precio];
  (p.opciones || []).forEach((o) => o.valores.forEach((v) => typeof v.precio === 'number' && precios.push(v.precio)));
  return Math.min(...precios);
}

export function imagenDe(p, sel = {}) {
  let img = p.imagen;
  for (const { valor } of valoresElegidos(p, sel)) {
    if (valor.imagen) img = valor.imagen;
  }
  return img;
}

// "Verde · 460 ml"
export function describirOpciones(p, sel = {}) {
  if (!tieneOpciones(p)) return p.presentacion || '';
  return valoresElegidos(p, sel).map(({ valor }) => valor.nombre).join(' · ');
}

const fmt = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0, maximumFractionDigits: 2 });
export const dinero = (n) => fmt.format(n);

export function calcularEnvio(subtotal, envio) {
  if (subtotal <= 0) return 0;
  return subtotal >= envio.gratisDesde ? 0 : envio.costo;
}

export const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
