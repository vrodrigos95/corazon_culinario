// Datos generales de la tienda. Cambia aquí y se actualiza en todo el sitio.
export const TIENDA = {
  nombre: 'Corazón Culinario',
  whatsapp: '523314529429',
  whatsappVisible: '331 452 9429',
  correo: 'corazonculinario.productos@gmail.com',
  instagram: 'corazonculinario',
  direccion: 'Calle Tupátaro 5823, Pinar de la Calma, Zapopan, Jal.',
  zonaEntrega: 'Guadalajara y Zapopan',
};

// Endpoint PHP que crea la preferencia de pago en Mercado Pago.
export const API_CHECKOUT = 'api/crear-preferencia.php';

export const whatsappUrl = (mensaje = '') =>
  `https://wa.me/${TIENDA.whatsapp}${mensaje ? `?text=${encodeURIComponent(mensaje)}` : ''}`;
