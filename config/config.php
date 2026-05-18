<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/La_Paz');

define('APP_NAME', 'Cine SOE');
define('ROOT_PATH', dirname(__DIR__));

$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$rootPath = str_replace('\\', '/', ROOT_PATH);
$basePath = str_replace($docRoot, '', $rootPath);
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
}
define('BASE_PATH', rtrim($basePath, '/'));
// Load .env file if it exists (for local development)
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

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

function clean_full_name(string $value): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', strip_tags(trim($value))) ?? '';
    return preg_replace('/\s+/', ' ', $value) ?? '';
}

function is_full_name(string $value): bool
{
    $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts || count($parts) < 2) {
        return false;
    }

    foreach ($parts as $part) {
        $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
        if ($length < 2) {
            return false;
        }
    }

    return true;
}

function combine_name_parts(string $nombre, string $apellido): string
{
    $nombre = clean_full_name($nombre);
    $apellido = clean_full_name($apellido);

    return trim($nombre . ' ' . $apellido);
}

function split_name_parts(?string $value): array
{
    $parts = preg_split('/\s+/', clean_full_name((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) <= 1) {
        return [$parts[0] ?? '', ''];
    }

    $apellido = array_pop($parts);
    return [implode(' ', $parts), $apellido];
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
