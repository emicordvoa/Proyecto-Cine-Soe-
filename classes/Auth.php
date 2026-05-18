<?php
declare(strict_types=1);

class Auth
{
    private const REMEMBER_COOKIE = 'cine_soe_remember';
    private const REMEMBER_EMAIL_COOKIE = 'soe_remember_email';
    private const LEGACY_EMAIL_COOKIE = 'cine_soe_email';
    private const REMEMBER_DAYS = 30;

    public static function user(): ?array
    {
        self::attemptRemember();
        return $_SESSION['usuario'] ?? null;
    }

    public static function id(): ?int
    {
        self::attemptRemember();
        return isset($_SESSION['usuario']['id']) ? (int) $_SESSION['usuario']['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireRole(array $roles): void
    {
        if (!self::check() || !in_array($_SESSION['usuario']['rol'], $roles, true)) {
            redirect('../login.php');
        }
    }

    public static function login(string $correo, string $password, bool $remember = false): bool
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM usuarios WHERE correo = ? AND estado = 'activo' LIMIT 1"
        );
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        self::storeUser($user);

        if ($remember) {
            self::remember($user);
            self::rememberEmail((string) $user['correo']);
        } else {
            self::forgetRememberCookies();
        }

        return true;
    }

    public static function loginValidadorPorCodigo(string $codigo): bool
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM usuarios WHERE codigo_referencia = ? AND rol IN ('admin','vendedor','validador') AND estado = 'activo' LIMIT 1"
        );
        $stmt->execute([$codigo]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        self::storeUser($user);
        return true;
    }

    public static function logout(): void
    {
        self::forgetRememberToken();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function rememberedEmail(): string
    {
        return trim((string) ($_COOKIE[self::REMEMBER_EMAIL_COOKIE] ?? ($_COOKIE[self::LEGACY_EMAIL_COOKIE] ?? '')));
    }

    private static function attemptRemember(): void
    {
        if (!empty($_SESSION['usuario']) || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }

        $parts = explode(':', (string) $_COOKIE[self::REMEMBER_COOKIE], 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            self::forgetRememberCookies();
            return;
        }

        $stmt = Database::getConnection()->prepare("SELECT * FROM usuarios WHERE id = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute([(int) $parts[0]]);
        $user = $stmt->fetch();

        if (!$user || !hash_equals(self::rememberHash($user), $parts[1])) {
            self::forgetRememberToken();
            return;
        }

        self::storeUser($user);
    }

    private static function remember(array $user): void
    {
        $value = $user['id'] . ':' . self::rememberHash($user);
        self::queueCookie(self::REMEMBER_COOKIE, $value, time() + (86400 * self::REMEMBER_DAYS), true);
    }

    private static function rememberEmail(string $correo): void
    {
        $expires = time() + (86400 * self::REMEMBER_DAYS);
        self::queueCookie(self::REMEMBER_EMAIL_COOKIE, $correo, $expires, false);
        self::queueCookie(self::LEGACY_EMAIL_COOKIE, $correo, $expires, false);
    }

    private static function forgetRememberCookies(): void
    {
        self::forgetRememberToken();
        self::forgetRememberEmail();
    }

    private static function forgetRememberToken(): void
    {
        self::queueCookie(self::REMEMBER_COOKIE, '', time() - 3600, true);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    private static function forgetRememberEmail(): void
    {
        self::queueCookie(self::REMEMBER_EMAIL_COOKIE, '', time() - 3600, false);
        self::queueCookie(self::LEGACY_EMAIL_COOKIE, '', time() - 3600, false);
        unset($_COOKIE[self::REMEMBER_EMAIL_COOKIE], $_COOKIE[self::LEGACY_EMAIL_COOKIE]);
    }

    private static function queueCookie(string $name, string $value, int $expires, bool $httpOnly): void
    {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => self::cookiePath(),
            'secure' => self::isSecure(),
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);

        if (self::cookiePath() !== '/') {
            setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => self::isSecure(),
                'httponly' => $httpOnly,
                'samesite' => 'Lax',
            ]);
        }

        if ($expires > time()) {
            $_COOKIE[$name] = $value;
        }
    }

    private static function cookiePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '/';
    }

    private static function rememberHash(array $user): string
    {
        return hash_hmac('sha256', $user['id'] . '|' . $user['password_hash'], APP_NAME);
    }

    private static function isSecure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    private static function storeUser(array $user): void
    {
        $_SESSION['usuario'] = [
            'id' => (int) $user['id'],
            'nombre' => $user['nombre_completo'],
            'correo' => $user['correo'],
            'rol' => $user['rol'],
            'codigo_referencia' => $user['codigo_referencia'],
            'whatsapp' => $user['whatsapp'] ?? null,
        ];
    }
}
