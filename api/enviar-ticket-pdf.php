<?php
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';
require __DIR__ . '/../classes/Compra.php';
require __DIR__ . '/../classes/Entrada.php';
require __DIR__ . '/../classes/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(bool $ok, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::check() || !in_array(Auth::user()['rol'], ['admin', 'vendedor'], true)) {
    json_response(false, 'Sesion no autorizada.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metodo no permitido.', 405);
}

if (!verify_csrf($_POST['csrf'] ?? null)) {
    json_response(false, 'Sesion expirada.', 419);
}

$compraId = filter_input(INPUT_POST, 'compra', FILTER_VALIDATE_INT);
$compra = $compraId ? Compra::detalle($compraId) : null;
if (!$compra) {
    json_response(false, 'Compra no encontrada.', 404);
}

if (Auth::user()['rol'] === 'vendedor' && (int) $compra['id_usuario_vendedor'] !== (int) Auth::id()) {
    json_response(false, 'No autorizado para esta compra.', 403);
}

if (($compra['estado_pago'] ?? '') !== 'aprobado') {
    json_response(false, 'La compra aun no esta aprobada.', 422);
}

$file = $_FILES['pdf'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_response(false, 'No se recibio el PDF.', 422);
}

if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 15 * 1024 * 1024) {
    json_response(false, 'El PDF esta vacio o supera 15MB.', 422);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName) || !is_readable($tmpName)) {
    json_response(false, 'No se pudo leer el PDF.', 422);
}

$pdfData = file_get_contents($tmpName);
if ($pdfData === false || !preg_match('/\A%PDF-\d\.\d/', $pdfData) || !str_contains(substr($pdfData, -2048), '%%EOF')) {
    json_response(false, 'El archivo generado no parece un PDF valido.', 422);
}

$stmt = Database::getConnection()->prepare(
    "SELECT token_validacion
     FROM entradas
     WHERE id_compra = ? AND eliminado = 0
     ORDER BY id"
);
$stmt->execute([(int) $compra['id']]);
$tokens = array_column($stmt->fetchAll(), 'token_validacion');
if (!$tokens) {
    json_response(false, 'No hay entradas generadas para esta compra.', 422);
}

$safeCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $compra['codigo_compra']);
$fileName = 'entradas-' . ($safeCode ?: (string) $compra['id']) . '.pdf';
$directory = UPLOAD_PATH . '/tickets';
if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
    json_response(false, 'No se pudo preparar la carpeta de tickets.', 500);
}

$destination = $directory . '/' . $fileName;
if (!move_uploaded_file($tmpName, $destination)) {
    json_response(false, 'No se pudo guardar el PDF generado.', 500);
}

$sent = Mailer::enviarTickets($compra, $tokens, [
    'path' => $destination,
    'filename' => $fileName,
    'mime' => 'application/pdf',
]);

json_response(
    $sent,
    $sent ? 'Correo enviado con el PDF de entradas.' : 'No se pudo enviar el correo. Revisa SMTP.',
    $sent ? 200 : 500
);
