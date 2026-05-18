<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/La_Paz');

define('APP_NAME', 'Cine SOE');
define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', '/' . rawurlencode(basename(ROOT_PATH)));

$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$cloudflareVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || $forwardedProto === 'https'
    || str_contains($cloudflareVisitor, '"scheme":"https"');

define('BASE_URL', ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
define('SITE_URL', BASE_URL);
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('WHATSAPP_NUMBER', '59170000000');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function asset(string $path): string
{
    $cleanPath = ltrim($path, '/');
    $url = BASE_URL . '/' . $cleanPath;
    $filePath = ROOT_PATH . '/' . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);

    if (is_file($filePath)) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'v=' . filemtime($filePath);
    }

    return $url;
}
