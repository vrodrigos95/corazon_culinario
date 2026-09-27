<?php
// Notificaciones de Mercado Pago (Webhooks).
// Consulta el pago directamente en la API de Mercado Pago (no confía en el cuerpo
// de la notificación), actualiza el pedido y avisa por correo cuando se aprueba.

declare(strict_types=1);
require __DIR__ . '/lib.php';

$raw = (string) file_get_contents('php://input', false, null, 0, 64 * 1024);
$cuerpo = json_decode($raw, true) ?: [];

$tipo = $cuerpo['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '';
$pagoId = (string) ($cuerpo['data']['id'] ?? $_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '');

if ($tipo !== 'payment' || !preg_match('/^\d{1,20}$/', $pagoId)) {
    responder(200, ['ok' => true, 'ignorado' => true]);
}

try {
    $cfg = config();
} catch (Throwable $e) {
    responder(500, ['error' => 'Sin configuración']);
}

// Validación de firma (x-signature) si hay clave secreta configurada.
$secreto = $cfg['mp_webhook_secret'] ?? '';
if ($secreto !== '') {
    $firma = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $partes = [];
    foreach (explode(',', $firma) as $par) {
        [$k, $v] = array_map('trim', explode('=', $par, 2) + [1 => '']);
        $partes[$k] = $v;
    }
    $manifiesto = 'id:' . strtolower($pagoId) . ';request-id:' . $requestId . ';ts:' . ($partes['ts'] ?? '') . ';';
    $esperada = hash_hmac('sha256', $manifiesto, $secreto);
    if (empty($partes['v1']) || !hash_equals($esperada, $partes['v1'])) {
        registrar("Webhook con firma inválida para pago $pagoId");
        responder(401, ['error' => 'Firma inválida']);
    }
}

try {
    [$codigo, $pago] = mp_request('GET', '/v1/payments/' . $pagoId);
} catch (Throwable $e) {
    registrar("Webhook pago $pagoId: " . $e->getMessage());
    responder(500, ['error' => 'Reintentar']);
}
if ($codigo !== 200) {
    registrar("Webhook pago $pagoId: consulta respondió $codigo");
    responder(500, ['error' => 'Reintentar']);
}

$pedidoId = (string) ($pago['external_reference'] ?? '');
try {
    $pedido = leer_pedido($pedidoId);
} catch (InvalidArgumentException $e) {
    $pedido = null;
}
if ($pedido === null) {
    registrar("Webhook pago $pagoId: pedido '$pedidoId' no encontrado");
    responder(200, ['ok' => true]);
}

$estado = (string) ($pago['status'] ?? 'desconocido');
$monto = (float) ($pago['transaction_amount'] ?? 0);

$pedido['pagos'][$pagoId] = [
    'estado' => $estado,
    'detalle' => $pago['status_detail'] ?? '',
    'monto' => $monto,
    'metodo' => $pago['payment_method_id'] ?? '',
    'actualizado' => date('c'),
];

$mapa = [
    'approved' => 'pagado',
    'pending' => 'pago_pendiente',
    'in_process' => 'pago_pendiente',
    'authorized' => 'pago_pendiente',
    'rejected' => 'pago_rechazado',
    'cancelled' => 'cancelado',
    'refunded' => 'reembolsado',
    'charged_back' => 'contracargo',
];
// Un pedido pagado solo cambia si el pago se reembolsa o se desconoce.
if ($pedido['estado'] !== 'pagado' || in_array($estado, ['refunded', 'charged_back'], true)) {
    $pedido['estado'] = $mapa[$estado] ?? $pedido['estado'];
}

if ($estado === 'approved' && abs($monto - (float) $pedido['total']) > 0.5) {
    $pedido['alerta'] = 'El monto pagado (' . dinero($monto) . ') no coincide con el total (' . dinero((float) $pedido['total']) . ').';
    registrar("Pedido $pedidoId: " . $pedido['alerta']);
}

$avisar = $estado === 'approved' && empty($pedido['notificado']);
if ($avisar) {
    $pedido['notificado'] = date('c');
}
guardar_pedido($pedido);

if ($avisar) {
    enviar_aviso($pedido, $cfg);
}

responder(200, ['ok' => true]);

function enviar_aviso(array $p, array $cfg): void
{
    $c = $p['cliente'];
    $lineas = array_map(
        fn ($l) => sprintf('  %d × %s (%s) — %s', $l['cantidad'], $l['nombre'], $l['variante'], dinero((float) $l['total'])),
        $p['lineas']
    );
    $texto = implode("\n", [
        "Nuevo pedido pagado: {$p['id']}",
        '',
        ...$lineas,
        '',
        'Subtotal: ' . dinero((float) $p['subtotal']),
        'Envío: ' . ($p['envio'] > 0 ? dinero((float) $p['envio']) : 'Gratis'),
        'Total: ' . dinero((float) $p['total']),
        isset($p['alerta']) ? "\n⚠ {$p['alerta']}" : '',
        '',
        "Cliente: {$c['nombre']}",
        "Teléfono: {$c['telefono']}",
        "Correo: {$c['email']}",
        "Dirección: {$c['calle']}, {$c['colonia']}, CP {$c['cp']}, {$c['municipio']}",
        $c['referencias'] ? "Referencias: {$c['referencias']}" : '',
        $c['notas'] ? "Notas: {$c['notas']}" : '',
    ]);
    $asunto = '=?UTF-8?B?' . base64_encode("Pedido pagado {$p['id']} · " . dinero((float) $p['total'])) . '?=';
    $cabeceras = implode("\r\n", [
        'From: Corazón Culinario <' . $cfg['correo_remitente'] . '>',
        'Reply-To: ' . $c['email'],
        'Content-Type: text/plain; charset=UTF-8',
    ]);
    if (!@mail($cfg['correo_tienda'], $asunto, $texto, $cabeceras)) {
        registrar("Pedido {$p['id']}: no se pudo enviar el correo de aviso");
    }
}
