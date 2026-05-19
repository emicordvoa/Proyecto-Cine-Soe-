<?php
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';
require __DIR__ . '/../classes/Compra.php';
require __DIR__ . '/../classes/Entrada.php';
require __DIR__ . '/../classes/Mailer.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ob_start();
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
register_shutdown_function(function (): void {
    $lastError = error_get_last();
    if ($lastError !== null && in_array($lastError['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        error_log(sprintf(
            'Fatal error en api/enviar-ticket-pdf.php: %s in %s on line %s',
            $lastError['message'],
            $lastError['file'] ?? '<unknown>',
            $lastError['line'] ?? 0
        ));
        if (ob_get_length() !== false) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Ocurrió un error interno al procesar el correo.'], JSON_UNESCAPED_UNICODE);
    }
});

header('Content-Type: application/json; charset=utf-8');

function json_response(bool $ok, string $message, int $status = 200): never
{
    if (ob_get_length() !== false) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
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

if (empty($compra['correo']) || !filter_var((string) $compra['correo'], FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'El correo del cliente no esta configurado o es invalido.', 422);
}

if (!Mailer::hasSmtpConfig()) {
    json_response(false, 'No se configuró el correo SMTP.', 500);
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
} catch (Throwable $exception) {
    error_log('Error en api/enviar-ticket-pdf.php: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    json_response(false, 'No se pudo enviar el correo. Revisa la configuración SMTP.', 500);
}
