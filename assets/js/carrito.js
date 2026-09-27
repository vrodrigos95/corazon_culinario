// Estado del carrito en localStorage. Solo guarda id, opciones y cantidad;
// los precios siempre se calculan desde el catálogo (y el servidor los vuelve a validar).

import { cargarCatalogo, precioDe, normalizarOpciones, calcularEnvio } from './catalogo.js';

const CLAVE = 'cc_carrito_v1';
const MAX_POR_LINEA = 24;
const oyentes = new Set();

function leer() {
  try {
    const data = JSON.parse(localStorage.getItem(CLAVE) || '[]');
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
}

function guardar(items) {
  try { localStorage.setItem(CLAVE, JSON.stringify(items)); } catch { /* modo privado */ }
  oyentes.forEach((fn) => fn(items));
}

export const claveLinea = (id, opciones = {}) =>
  id + Object.keys(opciones).sort().map((k) => `|${k}=${opciones[k]}`).join('');

export const obtenerItems = () => leer();

export function contarUnidades() {
  return leer().reduce((n, it) => n + it.cantidad, 0);
}

export function agregar(id, opciones = {}, cantidad = 1) {
  const items = leer();
  const clave = claveLinea(id, opciones);
  const existente = items.find((it) => claveLinea(it.id, it.opciones) === clave);
  if (existente) existente.cantidad = Math.min(MAX_POR_LINEA, existente.cantidad + cantidad);
  else items.push({ id, opciones, cantidad: Math.min(MAX_POR_LINEA, cantidad) });
  guardar(items);
}

export function cambiarCantidad(clave, cantidad) {
  let items = leer();
  if (cantidad <= 0) items = items.filter((it) => claveLinea(it.id, it.opciones) !== clave);
  else items.forEach((it) => { if (claveLinea(it.id, it.opciones) === clave) it.cantidad = Math.min(MAX_POR_LINEA, cantidad); });
  guardar(items);
}

export const quitar = (clave) => cambiarCantidad(clave, 0);
export const vaciar = () => guardar([]);
export const alCambiar = (fn) => { oyentes.add(fn); return () => oyentes.delete(fn); };

// Sincroniza entre pestañas.
window.addEventListener('storage', (e) => { if (e.key === CLAVE) oyentes.forEach((fn) => fn(leer())); });

// Devuelve las líneas con producto y precio resueltos, y los totales.
export async function resumen() {
  const cat = await cargarCatalogo();
  const lineas = [];
  for (const it of leer()) {
    const producto = cat.productos.find((p) => p.id === it.id);
    if (!producto) continue;
    const opciones = normalizarOpciones(producto, it.opciones);
    const precio = precioDe(producto, opciones);
    lineas.push({ ...it, opciones, producto, precio, total: precio * it.cantidad, clave: claveLinea(it.id, it.opciones) });
  }
  const subtotal = lineas.reduce((s, l) => s + l.total, 0);
  const envio = calcularEnvio(subtotal, cat.envio);
  return { lineas, subtotal, envio, total: subtotal + envio, config: cat.envio };
}
