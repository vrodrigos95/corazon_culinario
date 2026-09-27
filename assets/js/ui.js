// Componentes de interfaz reutilizables (tarjeta de producto).

import { dinero, precioMinimo, tieneOpciones, escapar } from './catalogo.js';
import { agregar } from './carrito.js';
import { avisar } from './layout.js';

export function tarjetaHTML(p) {
  const conOpciones = tieneOpciones(p);
  const precio = conOpciones
    ? `<small>Desde</small>${dinero(precioMinimo(p))}`
    : dinero(p.precio);
  const accion = conOpciones
    ? `<a class="btn btn--contorno btn--sm" href="producto.html?id=${p.id}">Elegir opciones</a>`
    : `<button class="btn btn--contorno btn--sm" type="button" data-agregar="${p.id}">Agregar</button>`;
  return `
    <article class="tarjeta revelar" data-categoria="${p.categoria}">
      <a class="tarjeta__foto" href="producto.html?id=${p.id}">
        <img src="${p.imagen}" alt="${escapar(p.nombre)}" loading="lazy" width="800" height="1000">
        ${p.etiqueta ? `<span class="tarjeta__marca">${escapar(p.etiqueta)}</span>` : ''}
      </a>
      <div class="tarjeta__info">
        <a class="tarjeta__nombre" href="producto.html?id=${p.id}">${escapar(p.nombre)}</a>
        <div class="tarjeta__pres">${escapar(p.presentacion)}</div>
        <div class="tarjeta__precio">${precio}</div>
        <div class="tarjeta__accion">${accion}</div>
      </div>
    </article>`;
}

// Delegación: botones "Agregar" de productos sin opciones.
export function conectarAgregar(contenedor, catalogo) {
  contenedor.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-agregar]');
    if (!btn) return;
    const p = catalogo.productos.find((x) => x.id === btn.dataset.agregar);
    if (!p) return;
    agregar(p.id, {}, 1);
    avisar(`${p.nombre} se agregó a tu carrito`);
  });
}
