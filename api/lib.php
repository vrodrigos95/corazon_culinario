<?php
// Utilidades compartidas por los endpoints de la tienda.
// La lógica de precios replica assets/js/catalogo.js: si cambias una, cambia la otra.

declare(strict_types=1);

const DIR_PRIVADO = __DIR__ . '/../private';
const DIR_PEDIDOS = DIR_PRIVADO . '/pedidos';
const RUTA_CATALOGO = __DIR__ . '/../data/productos.json';
const MP_API = 'https://api.mercadopago.com';

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $ruta = DIR_PRIVADO . '/config.php';
        if (!is_file($ruta)) {
            throw new RuntimeException('Falta private/config.php (copia private/config.example.php).');
        }
        $cfg = require $ruta;
    }
    return $cfg;
}

function responder(int $codigo, array $datos): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function catalogo(): array
{
    static $cat = null;
    if ($cat === null) {
        $cat = json_decode((string) file_get_contents(RUTA_CATALOGO), true, 32, JSON_THROW_ON_ERROR);
    }
    return $cat;
}

function buscar_producto(string $id): ?array
{
    foreach (catalogo()['productos'] as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

/** Devuelve [opcionesNormalizadas, valoresElegidos]. */
function normalizar_opciones(array $p, $sel): array
{
    $sel = is_array($sel) ? $sel : [];
    $norm = [];
    $valores = [];
    foreach ($p['opciones'] ?? [] as $o) {
        $elegido = $o['valores'][0];
        foreach ($o['valores'] as $v) {
            if (isset($sel[$o['clave']]) && $v['id'] === (string) $sel[$o['clave']]) {
                $elegido = $v;
                break;
            }
        }
        $norm[$o['clave']] = $elegido['id'];
        $valores[] = $elegido;
    }
    return [$norm, $valores];
}

function precio_linea(array $p, array $valores): float
{
    $precio = (float) $p['precio'];
    foreach ($valores as $v) {
        if (isset($v['precio']) && is_numeric($v['precio'])) {
            $precio = (float) $v['precio'];
        }
    }
    return $precio;
}

/**
 * Valida los artículos enviados por el navegador contra el catálogo y
 * recalcula precios. Nunca se confía en precios del cliente.
 */
function armar_pedido(array $items): array
{
    if (count($items) === 0 || count($items) > 30) {
        throw new InvalidArgumentException('El carrito está vacío o tiene demasiados artículos.');
    }
    $lineas = [];
    foreach ($items as $it) {
        if (!is_array($it) || !isset($it['id']) || !is_string($it['id'])) {
            throw new InvalidArgumentException('Artículo inválido en el carrito.');
        }
        $p = buscar_producto($it['id']);
        if ($p === null) {
            throw new InvalidArgumentException('Uno de los productos ya no está disponible.');
        }
        $cantidad = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
        if ($cantidad === false) {
            throw new InvalidArgumentException('Cantidad inválida para ' . $p['nombre'] . '.');
        }
        [$opciones, $valores] = normalizar_opciones($p, $it['opciones'] ?? []);
        $variante = $valores ? implode(' · ', array_column($valores, 'nombre')) : ($p['presentacion'] ?? '');
        $imagen = $p['imagen'];
        foreach ($valores as $v) {
            if (!empty($v['imagen'])) {
                $imagen = $v['imagen'];
            }
        }
        $precio = precio_linea($p, $valores);
        $lineas[] = [
            'id' => $p['id'],
            'nombre' => $p['nombre'],
            'variante' => $variante,
            'opciones' => $opciones,
            'imagen' => $imagen,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'total' => $precio * $cantidad,
        ];
    }
    $subtotal = array_sum(array_column($lineas, 'total'));
    $envioCfg = catalogo()['envio'];
    $envio = $subtotal >= (float) $envioCfg['gratisDesde'] ? 0.0 : (float) $envioCfg['costo'];

    return [
        'lineas' => $lineas,
        'subtotal' => $subtotal,
        'envio' => $envio,
        'total' => $subtotal + $envio,
    ];
}

function limpiar_texto($v, int $max): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

function validar_cliente($c): array
{
    if (!is_array($c)) {
        throw new InvalidArgumentException('Faltan tus datos de contacto.');
    }
    $cliente = [
        'nombre' => limpiar_texto($c['nombre'] ?? '', 80),
        'email' => limpiar_texto($c['email'] ?? '', 120),
        'telefono' => preg_replace('/\D+/', '', (string) ($c['telefono'] ?? '')) ?? '',
        'calle' => limpiar_texto($c['calle'] ?? '', 120),
        'colonia' => limpiar_texto($c['colonia'] ?? '', 80),
        'cp' => limpiar_texto($c['cp'] ?? '', 5),
        'municipio' => limpiar_texto($c['municipio'] ?? '', 60),
        'referencias' => limpiar_texto($c['referencias'] ?? '', 160),
        'notas' => limpiar_texto($c['notas'] ?? '', 400),
    ];
    $errores = [];
    if (mb_strlen($cliente['nombre']) < 3) $errores[] = 'nombre';
    if (!filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'correo';
    if (strlen($cliente['telefono']) < 10 || strlen($cliente['telefono']) > 13) $errores[] = 'teléfono';
    if (mb_strlen($cliente['calle']) < 3) $errores[] = 'calle';
    if (mb_strlen($cliente['colonia']) < 2) $errores[] = 'colonia';
    if (!preg_match('/^\d{5}$/', $cliente['cp'])) $errores[] = 'código postal';
    if ($cliente['municipio'] === '') $errores[] = 'municipio';
    if ($errores) {
        throw new InvalidArgumentException('Revisa estos datos: ' . implode(', ', $errores) . '.');
    }
    return $cliente;
}

function nuevo_id_pedido(): string
{
    return 'CC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function ruta_pedido(string $id): string
{
    if (!preg_match('/^CC-\d{6}-[A-F0-9]{6}$/', $id)) {
        throw new InvalidArgumentException('Pedido inválido.');
    }
    return DIR_PEDIDOS . '/' . $id . '.json';
}

function guardar_pedido(array $pedido): void
{
    if (!is_dir(DIR_PEDIDOS)) {
        mkdir(DIR_PEDIDOS, 0750, true);
    }
    $json = json_encode($pedido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents(ruta_pedido($pedido['id']), $json, LOCK_EX);
}

function leer_pedido(string $id): ?array
{
    $ruta = ruta_pedido($id);
    if (!is_file($ruta)) {
        return null;
    }
    return json_decode((string) file_get_contents($ruta), true);
}

/** Llamada HTTP a la API de Mercado Pago. Devuelve [codigo, cuerpo]. */
function mp_request(string $metodo, string $ruta, ?array $cuerpo = null, array $headersExtra = []): array
{
    $ch = curl_init(MP_API . $ruta);
    $headers = array_merge([
        'Authorization: Bearer ' . config()['mp_access_token'],
        'Content-Type: application/json',
    ], $headersExtra);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $resp = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        throw new RuntimeException('Error de conexión con Mercado Pago: ' . $error);
    }
    return [$codigo, json_decode((string) $resp, true) ?? []];
}

function registrar(string $mensaje): void
{
    if (!is_dir(DIR_PEDIDOS)) {
        mkdir(DIR_PEDIDOS, 0750, true);
    }
    file_put_contents(DIR_PEDIDOS . '/eventos.log', '[' . date('c') . '] ' . $mensaje . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function dinero(float $n): string
{
    return '$' . number_format($n, 2, '.', ',');
}
