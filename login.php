<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (isset($_GET['salir'])) { Auth::logout(); redirect('login.php'); }

$mensaje = '';
$correoRecordado = Auth::rememberedEmail();

if (Auth::check()) { redirect('admin/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $mensaje = 'Token de seguridad inválido.';
    } else {
        $correo = trim((string) ($_POST['correo'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $recordarme = isset($_POST['recordarme']);
        if (Auth::login($correo, $password, $recordarme)) { redirect('admin/index.php'); }
        $mensaje = 'Correo o contraseña incorrectos.';
        $correoRecordado = $correo;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Cine SOE</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body class="site-bg login-page">
    <div class="glass login-card p-4">
        <div class="text-center mb-3">
            <div class="brand-icon" style="margin:0 auto .8rem;width:52px;height:36px;font-size:.9rem">SOE</div>
            <h1 style="font-size:1.6rem;font-weight:900;letter-spacing:-.03em">Iniciar sesión</h1>
            <p class="text-muted text-sm">Acceso para administradores.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-danger"><?= e($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label class="form-label" for="correo">Correo electrónico</label>
                <input class="form-input" id="correo" type="email" name="correo" value="<?= e($correoRecordado) ?>" placeholder="Tu Correo" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="passwordInput">Contraseña</label>
                <div class="password-wrap">
                    <input class="form-input" id="passwordInput" type="password" name="password" placeholder="Tu contraseña" required>
                    <button class="password-toggle" type="button" data-toggle-password="passwordInput" aria-label="Mostrar contraseña">
                        <span class="eye-shape" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <label class="check-row">
                <input type="checkbox" name="recordarme" value="1" <?= $correoRecordado ? 'checked' : '' ?>>
                <span>Recuérdame</span>
            </label>
            <button class="btn btn-primary btn-lg btn-block" type="submit">INICIAR SESIÓN</button>
        </form>
        <a class="btn btn-ghost btn-block mt-2 text-center" href="index.php" style="font-size:.9rem">← Volver al inicio</a>
    </div>
<script src="assets/js/main.js"></script>
</body>
</html>
