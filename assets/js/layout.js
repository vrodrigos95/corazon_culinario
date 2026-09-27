// Header, footer, panel del carrito y utilidades comunes a todas las páginas.

import { TIENDA, whatsappUrl } from './config.js';
import { resumen, contarUnidades, cambiarCantidad, quitar, alCambiar } from './carrito.js';
import { dinero, describirOpciones, imagenDe, escapar } from './catalogo.js';

const ICONOS = {
  bolsa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>',
  menu: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h18"/></svg>',
  cerrar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>',
  camion: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M2 6h12v10H2zM14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/></svg>',
};
export { ICONOS };

const PAGINAS = [
  { href: 'index.html', texto: 'Inicio', id: 'inicio' },
  { href: 'productos.html', texto: 'Productos', id: 'productos' },
  { href: 'nosotros.html', texto: 'Nosotros', id: 'nosotros' },
  { href: 'index.html#contacto', texto: 'Contacto', id: 'contacto' },
];

function header(activa) {
  return `
  <div class="anuncio">Envío gratis a partir de $500 · Entregas en ${TIENDA.zonaEntrega}</div>
  <header class="header">
    <div class="contenedor header__barra">
      <button class="btn-menu" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav">${ICONOS.menu}</button>
      <a class="header__logo" href="index.html" aria-label="${TIENDA.nombre} — inicio">
        <img src="assets/img/marca/logo-horizontal.png" alt="${TIENDA.nombre}" width="1717" height="162">
      </a>
      <nav class="nav" id="nav" aria-label="Principal">
        ${PAGINAS.map((p) => `<a class="nav__link" href="${p.href}"${p.id === activa ? ' aria-current="page"' : ''}>${p.texto}</a>`).join('')}
      </nav>
      <div class="header__acciones">
        <button class="btn-carrito" type="button" data-abrir-carrito aria-label="Abrir carrito">
          ${ICONOS.bolsa}<span class="btn-carrito__txt">Carrito</span><span class="btn-carrito__num" data-contador>0</span>
        </button>
      </div>
    </div>
  </header>`;
}

function footer() {
  const anio = new Date().getFullYear();
  return `
  <footer class="footer">
    <div class="contenedor">
      <div class="footer__grid">
        <div>
          <div class="footer__logo"><img src="assets/img/marca/logo-horizontal-claro.png" alt="${TIENDA.nombre}" width="1717" height="162"></div>
          <p>Salsas, vinagretas y aderezos de receta familiar, hechos a mano en Guadalajara.</p>
        </div>
        <div>
          <h4>Tienda</h4>
          <ul>
            <li><a href="productos.html">Todos los productos</a></li>
            <li><a href="productos.html#salsas">Salsas</a></li>
            <li><a href="productos.html#vinagretas">Vinagretas</a></li>
            <li><a href="productos.html#aderezos">Aderezos</a></li>
          </ul>
        </div>
        <div>
          <h4>La casa</h4>
          <ul>
            <li><a href="nosotros.html">Nosotros</a></li>
            <li><a href="index.html#contacto">Contacto</a></li>
            <li><a href="checkout.html">Finalizar compra</a></li>
          </ul>
        </div>
        <div>
          <h4>Hablemos</h4>
          <ul>
            <li><a href="${whatsappUrl()}" target="_blank" rel="noopener">WhatsApp · ${TIENDA.whatsappVisible}</a></li>
            <li><a href="mailto:${TIENDA.correo}">${TIENDA.correo}</a></li>
            <li><a href="https://instagram.com/${TIENDA.instagram}" target="_blank" rel="noopener">@${TIENDA.instagram}</a></li>
            <li>${TIENDA.direccion}</li>
          </ul>
        </div>
      </div>
      <div class="footer__legal">
        <span>© ${anio} ${TIENDA.nombre}®. Hecho con cariño en Jalisco.</span>
        <span>Pagos seguros con Mercado Pago</span>
      </div>
    </div>
  </footer>`;
}

function panelCarrito() {
  return `
  <div class="carrito-velo" data-cerrar-carrito></div>
  <aside class="carrito" aria-label="Carrito de compras" aria-hidden="true">
    <div class="carrito__cabecera">
      <h2>Tu carrito</h2>
      <button class="btn-cerrar" type="button" data-cerrar-carrito aria-label="Cerrar carrito">${ICONOS.cerrar}</button>
    </div>
    <div class="carrito__cuerpo" data-carrito-lineas></div>
    <div class="carrito__pie" data-carrito-pie></div>
  </aside>
  <div class="toast" role="status" aria-live="polite"><span data-toast-texto></span><button type="button" data-abrir-carrito>Ver carrito</button></div>`;
}

export function progresoEnvio(subtotal, config) {
  const falta = Math.max(0, config.gratisDesde - subtotal);
  const pct = Math.min(100, (subtotal / config.gratisDesde) * 100);
  const texto = falta > 0
    ? `Te faltan <strong>${dinero(falta)}</strong> para el envío gratis`
    : '<span class="gratis">Tu pedido tiene envío gratis</span>';
  return `<div class="progreso-envio"><span>${texto}</span><div class="progreso-envio__barra"><span style="width:${pct}%"></span></div></div>`;
}

export function lineaHTML(l, { editable = true } = {}) {
  const img = imagenDe(l.producto, l.opciones);
  const variante = describirOpciones(l.producto, l.opciones);
  return `
    <div class="linea" data-clave="${escapar(l.clave)}">
      <a class="linea__foto" href="producto.html?id=${l.producto.id}">
        <img src="${img}" alt="" loading="lazy">
        ${editable ? '' : `<span class="linea__cant">${l.cantidad}</span>`}
      </a>
      <div>
        <div class="linea__nombre">${escapar(l.producto.nombre)}</div>
        <div class="linea__var">${escapar(variante)}</div>
        ${editable ? `
        <div class="cantidad cantidad--sm">
          <button type="button" data-menos aria-label="Quitar uno">−</button>
          <input type="number" value="${l.cantidad}" min="1" max="24" aria-label="Cantidad" data-cant>
          <button type="button" data-mas aria-label="Agregar uno">+</button>
        </div>` : ''}
      </div>
      <div>
        <div class="linea__precio">${dinero(l.total)}</div>
        ${editable ? '<button class="linea__quitar" type="button" data-quitar>Quitar</button>' : ''}
      </div>
    </div>`;
}

async function pintarCarrito() {
  const cuerpo = document.querySelector('[data-carrito-lineas]');
  const pie = document.querySelector('[data-carrito-pie]');
  document.querySelectorAll('[data-contador]').forEach((el) => { el.textContent = contarUnidades(); });
  if (!cuerpo) return;
  const r = await resumen();
  if (!r.lineas.length) {
    cuerpo.innerHTML = `
      <div class="carrito__vacio">
        <img src="assets/img/marca/corazon.png" alt="">
        <p>Tu carrito está vacío.</p>
        <a class="btn btn--contorno" href="productos.html">Ver productos</a>
      </div>`;
    pie.innerHTML = '';
    return;
  }
  cuerpo.innerHTML = r.lineas.map((l) => lineaHTML(l)).join('');
  pie.innerHTML = `
    ${progresoEnvio(r.subtotal, r.config)}
    <div class="totales">
      <div class="totales__fila"><span>Subtotal</span><span>${dinero(r.subtotal)}</span></div>
      <div class="totales__fila"><span>Envío</span><span>${r.envio === 0 ? '<span class="gratis">Gratis</span>' : dinero(r.envio)}</span></div>
      <div class="totales__fila totales__fila--total"><span>Total</span><span>${dinero(r.total)}</span></div>
    </div>
    <a class="btn btn--bloque" href="checkout.html">Finalizar compra</a>
    <button class="enlace" type="button" data-cerrar-carrito style="align-self:center">Seguir comprando</button>`;
}

export function abrirCarrito() {
  document.body.classList.add('carrito-abierto');
  document.querySelector('.carrito')?.setAttribute('aria-hidden', 'false');
  document.querySelector('.toast')?.classList.remove('visible');
}
export function cerrarCarrito() {
  document.body.classList.remove('carrito-abierto');
  document.querySelector('.carrito')?.setAttribute('aria-hidden', 'true');
}

let toastTimer;
export function avisar(texto) {
  const t = document.querySelector('.toast');
  if (!t) return;
  t.querySelector('[data-toast-texto]').textContent = texto;
  t.classList.add('visible');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('visible'), 3200);
}

// Controles de cantidad dentro de un contenedor (panel o checkout).
export function conectarLineas(contenedor) {
  contenedor.addEventListener('click', (e) => {
    const linea = e.target.closest('.linea');
    if (!linea) return;
    const clave = linea.dataset.clave;
    const input = linea.querySelector('[data-cant]');
    if (e.target.closest('[data-mas]')) cambiarCantidad(clave, Number(input.value) + 1);
    else if (e.target.closest('[data-menos]')) cambiarCantidad(clave, Number(input.value) - 1);
    else if (e.target.closest('[data-quitar]')) quitar(clave);
  });
  contenedor.addEventListener('change', (e) => {
    if (!e.target.matches('[data-cant]')) return;
    const n = Math.max(0, Math.floor(Number(e.target.value) || 0));
    cambiarCantidad(e.target.closest('.linea').dataset.clave, n);
  });
}

export function revelar(raiz = document) {
  const els = raiz.querySelectorAll('.revelar:not(.visible)');
  if (!('IntersectionObserver' in window)) { els.forEach((el) => el.classList.add('visible')); return; }
  const io = new IntersectionObserver((entradas) => {
    entradas.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('visible'); io.unobserve(en.target); } });
  }, { rootMargin: '0px 0px -8% 0px' });
  els.forEach((el) => io.observe(el));
}

export function iniciarLayout(activa) {
  document.querySelector('[data-layout="header"]')?.insertAdjacentHTML('afterbegin', header(activa));
  document.querySelector('[data-layout="footer"]')?.insertAdjacentHTML('afterbegin', footer());
  document.body.insertAdjacentHTML('beforeend', panelCarrito());

  const btnMenu = document.querySelector('.btn-menu');
  const nav = document.getElementById('nav');
  btnMenu?.addEventListener('click', () => {
    const abierto = nav.classList.toggle('abierto');
    btnMenu.setAttribute('aria-expanded', String(abierto));
    btnMenu.innerHTML = abierto ? ICONOS.cerrar : ICONOS.menu;
  });
  nav?.addEventListener('click', (e) => { if (e.target.closest('a')) { nav.classList.remove('abierto'); btnMenu.innerHTML = ICONOS.menu; } });

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-abrir-carrito]')) abrirCarrito();
    else if (e.target.closest('[data-cerrar-carrito]')) cerrarCarrito();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarCarrito(); });

  const cuerpo = document.querySelector('[data-carrito-lineas]');
  if (cuerpo) conectarLineas(cuerpo);

  pintarCarrito();
  alCambiar(pintarCarrito);
  revelar();
}
