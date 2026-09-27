<?php
// Recibe el carrito y los datos del cliente, recalcula el pedido con precios
// del catálogo, lo guarda y crea una preferencia de pago en Mercado Pago.
// Respuesta: { ok, pedido, init_point }

declare(strict_types=1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'Método no permitido.']);
}

$raw = file_get_contents('php://input', false, null, 0, 32 * 1024);
$entrada = json_decode((string) $raw, true);
if (!is_array($entrada)) {
    responder(400, ['error' => 'Solicitud inválida.']);
}

try {
    $cfg = config();
    $cliente = validar_cliente($entrada['cliente'] ?? null);
    $pedido = armar_pedido(is_array($entrada['items'] ?? null) ? $entrada['items'] : []);
} catch (InvalidArgumentException $e) {
    responder(422, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    registrar('Error de configuración: ' . $e->getMessage());
    responder(500, ['error' => 'La tienda no está configurada para cobrar todavía.']);
}

$sitio = rtrim($cfg['site_url'], '/');
$esHttps = str_starts_with($sitio, 'https://');
$id = nuevo_id_pedido();

$items = [];
foreach ($pedido['lineas'] as $l) {
    $items[] = [
        'id' => $l['id'],
        'title' => $l['nombre'] . ($l['variante'] ? ' (' . $l['variante'] . ')' : ''),
        'quantity' => $l['cantidad'],
        'unit_price' => round($l['precio'], 2),
        'currency_id' => 'MXN',
        'picture_url' => $sitio . '/' . $l['imagen'],
        'category_id' => 'food',
    ];
}
if ($pedido['envio'] > 0) {
    $items[] = [
        'id' => 'envio',
        'title' => 'Envío a domicilio',
        'quantity' => 1,
        'unit_price' => round($pedido['envio'], 2),
        'currency_id' => 'MXN',
    ];
}

$partesNombre = preg_split('/\s+/', $cliente['nombre'], 2);
$preferencia = [
    'items' => $items,
    'payer' => [
        'name' => $partesNombre[0],
        'surname' => $partesNombre[1] ?? '',
        'email' => $cliente['email'],
        'phone' => ['number' => $cliente['telefono']],
        'address' => [
            'street_name' => $cliente['calle'],
            'zip_code' => $cliente['cp'],
        ],
    ],
    'external_reference' => $id,
    'statement_descriptor' => mb_substr($cfg['mp_descriptor'] ?? 'CORAZON CULINARIO', 0, 22),
    'back_urls' => [
        'success' => $sitio . '/pedido.html?estado=approved',
        'pending' => $sitio . '/pedido.html?estado=pending',
        'failure' => $sitio . '/pedido.html?estado=failure',
    ],
    'metadata' => ['pedido' => $id],
];
// Mercado Pago solo acepta auto_return y notification_url con URLs https públicas.
if ($esHttps) {
    $preferencia['auto_return'] = 'approved';
    $preferencia['notification_url'] = $sitio . '/api/webhook.php?source_news=webhooks';
}

try {
    [$codigo, $resp] = mp_request('POST', '/checkout/preferences', $preferencia, ['X-Idempotency-Key: ' . $id]);
} catch (Throwable $e) {
    registrar("Pedido $id: " . $e->getMessage());
    responder(502, ['error' => 'No pudimos conectar con Mercado Pago. Intenta de nuevo en un momento.']);
}

if ($codigo < 200 || $codigo >= 300 || empty($resp['id'])) {
    registrar("Pedido $id: Mercado Pago respondió $codigo " . json_encode($resp, JSON_UNESCAPED_UNICODE));
    responder(502, ['error' => 'Mercado Pago no aceptó la solicitud de pago.']);
}

$urlPago = ($cfg['mp_modo'] ?? 'pruebas') === 'produccion'
    ? ($resp['init_point'] ?? '')
    : ($resp['sandbox_init_point'] ?? $resp['init_point'] ?? '');

guardar_pedido([
    'id' => $id,
    'creado' => date('c'),
    'estado' => 'esperando_pago',
    'preference_id' => $resp['id'],
    'cliente' => $cliente,
] + $pedido + ['pagos' => []]);

responder(200, ['ok' => true, 'pedido' => $id, 'init_point' => $urlPago]);
