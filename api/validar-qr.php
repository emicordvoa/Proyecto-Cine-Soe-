<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';
require __DIR__ . '/../classes/Entrada.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check() || !in_array(Auth::user()['rol'], ['admin', 'vendedor', 'validador'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'tipo' => 'invalida', 'mensaje' => 'Sesion no autorizada.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$tokenText = trim((string) ($payload['token'] ?? ''));
$metodo = ($payload['metodo'] ?? 'camara') === 'manual' ? 'manual' : 'camara';

$codigoTicket = strtoupper((string) preg_replace('/\s+/', '', $tokenText));
$codigoTicket = preg_replace('/^SOE(?!-)/', 'SOE-', $codigoTicket);
preg_match('/[a-f0-9]{64}/i', $tokenText, $matches);
$token = preg_match('/^SOE-\d{9}$/', $codigoTicket) ? $codigoTicket : ($matches[0] ?? $tokenText);

if (!preg_match('/^[a-f0-9]{64}$/i', $token) && !preg_match('/^SOE-\d{9}$/', $token)) {
    echo json_encode(['ok' => false, 'tipo' => 'invalida', 'mensaje' => 'Codigo no valido. Usa SOE-000210605 o escanea el QR.']);
    exit;
}

try {
    $result = Entrada::validarToken($token, Auth::id(), $metodo);
    if (isset($result['entrada'])) {
        $entrada = $result['entrada'];
        $result['total_validadas'] = Entrada::totalValidadas((int) ($entrada['id_pelicula'] ?? 0));
        $result['contador_pelicula'] = $entrada['titulo'] ?? $entrada['pelicula'] ?? '';
        $result['entrada'] = [
            'cliente' => $entrada['nombre_completo'] ?? $entrada['cliente'] ?? '',
            'pelicula' => $entrada['titulo'] ?? $entrada['pelicula'] ?? '',
            'codigo_compra' => $entrada['codigo_compra'] ?? '',
        ];
    } else {
        $result['total_validadas'] = Entrada::totalValidadas();
    }
    echo json_encode($result);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'tipo' => 'invalida', 'mensaje' => 'Error del servidor.']);
}
