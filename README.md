# Corazón Culinario — Tienda en línea

Tienda en línea de salsas, vinagretas y aderezos de **Corazón Culinario**, con el diseño **1c Gourmet minimalista**:

- Paleta: `#FAF7F1` `#EDE6D8` `#8A8F6F` `#A9713F` `#1F1D1A`
- Tipografía: Cormorant Garamond (títulos) y Jost (texto y etiquetas)

Es un sitio estático (HTML, CSS y JavaScript sin frameworks). Tiene un backend mínimo en PHP para cobrar con **Mercado Pago** y funciona en el hosting compartido de **Hostinger** sin Composer ni Node.

## Páginas

| Archivo | Sección |
|---|---|
| `index.html` | Inicio: hero, colección, salsas universales, pilares, vinagretas, contacto |
| `productos.html` | Catálogo con filtros por categoría (`#salsas`, `#vinagretas`…) |
| `producto.html?id=…` | Detalle: galería, selector de opciones (sabor y tamaño), cantidad, relacionados |
| `checkout.html` | Datos de contacto y entrega, resumen y pago con Mercado Pago |
| `pedido.html` | Página a la que regresa Mercado Pago (pago aprobado, pendiente o fallido) |
| `nosotros.html` | Historia y valores de la familia |

El carrito es un panel lateral disponible en todas las páginas y se guarda en `localStorage`.

## Estructura

```
data/productos.json        ← catálogo: productos, precios, opciones y configuración de envío
assets/css/styles.css      ← sistema visual 1c
assets/js/                 ← config.js (datos de la tienda), catalogo.js, carrito.js, layout.js, ui.js
assets/img/                ← marca/ (logo), productos/ (fotos de producto), escenas/ (fotos editoriales)
api/crear-preferencia.php  ← valida el carrito, recalcula precios y crea el pago en Mercado Pago
api/webhook.php            ← recibe notificaciones de pago, actualiza el pedido y envía un correo de aviso
private/                   ← config.php (credenciales) y pedidos/ (bloqueada por .htaccess)
```

## Editar productos y precios

Todo el catálogo está en `data/productos.json`:

- `precio`: precio de menudeo en MXN.
- `opciones`: variantes (por ejemplo Salsa Universal → Sabor: Verde/Roja, Tamaño: 460/925 ml). Un valor con `precio` reemplaza el precio base y uno con `imagen` cambia la foto.
- `envio.gratisDesde` y `envio.costo`: umbral de envío gratis y costo del envío normal.

El servidor recalcula siempre los precios desde este archivo, así que el precio que se cobra no se puede alterar desde el navegador.

## Pendientes

- [ ] **Salsa Morita**: la imagen (`assets/img/productos/salsa-morita.svg`) y el precio ($125) son de referencia.
- [ ] **Costo de envío** para compras menores a $500: por ahora es $80 de referencia (`envio.costo`).
- [ ] Fotos y logo en alta calidad. Las imágenes actuales son recortes del brochure.
- [ ] Presentación del Aderezo 3 Vinagres: se asumió 350 ml.
- [ ] Opción de enviar el pedido por WhatsApp (queda para una segunda etapa).

## Probar en local

```bash
cp private/config.example.php private/config.php   # y pon tu Access Token de PRUEBA
php -S localhost:8000
```

Abre <http://localhost:8000>. Sin `config.php` el sitio funciona normal y el checkout muestra un aviso de que la tienda aún no cobra.

## Publicar en Hostinger

1. Sube el contenido del repositorio a `public_html/`, con el Administrador de archivos, por FTP o con la integración Git de hPanel.
2. En `public_html/private/`, copia `config.example.php` como `config.php` y llénalo:
   - `site_url`: `https://tudominio.com`
   - `mp_access_token`: Access Token de Mercado Pago (panel de desarrolladores → tu aplicación → Credenciales)
   - `mp_modo`: `pruebas` mientras pruebas y `produccion` al lanzar
   - `correo_remitente`: un correo de tu dominio creado en Hostinger (por ejemplo `pedidos@tudominio.com`)
3. Activa SSL en hPanel y descomenta el bloque "Forzar HTTPS" en `.htaccess`.
4. En Mercado Pago → Tu aplicación → **Webhooks**, registra `https://tudominio.com/api/webhook.php`, marca el evento **Pagos** y copia la clave secreta en `mp_webhook_secret`.
5. Usa PHP 8.1 o superior (hPanel → Avanzado → Configuración de PHP).

Los pedidos quedan guardados como JSON en `private/pedidos/`. Cuando un pago se aprueba llega un correo a `correo_tienda`.
