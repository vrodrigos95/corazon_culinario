<?php
// Copia este archivo como private/config.php y llena tus datos.
// private/config.php NO se sube a git (está en .gitignore).

return [
    // URL pública del sitio, sin diagonal final. Debe ser https en producción.
    'site_url' => 'https://www.tudominio.com',

    // Credenciales de Mercado Pago: https://www.mercadopago.com.mx/developers/panel/app
    // Usa las credenciales de PRUEBA mientras desarrollas y las de PRODUCCIÓN al lanzar.
    'mp_access_token' => 'APP_USR-xxxxxxxxxxxxxxxxxxxxxxxxxxxx',

    // 'pruebas' usa sandbox_init_point; 'produccion' usa init_point.
    'mp_modo' => 'pruebas',

    // Clave secreta de webhooks (Tus integraciones > Webhooks). Opcional pero recomendada.
    'mp_webhook_secret' => '',

    // Texto que aparece en el estado de cuenta del cliente (máx. 22 caracteres).
    'mp_descriptor' => 'CORAZON CULINARIO',

    // Correo donde llegan los avisos de pedidos pagados.
    'correo_tienda' => 'corazonculinario.productos@gmail.com',

    // Remitente de los avisos. En Hostinger conviene que sea un correo de tu dominio.
    'correo_remitente' => 'pedidos@tudominio.com',
];
