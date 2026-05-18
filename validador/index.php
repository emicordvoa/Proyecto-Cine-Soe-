<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';

if (Auth::check() && in_array(Auth::user()['rol'], ['admin', 'vendedor', 'validador'], true)) {
    redirect('escaner.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        flash('danger', 'Sesion expirada.');
    } elseif (Auth::loginValidadorPorCodigo(strtoupper(trim((string) $_POST['codigo'])))) {
        redirect('escaner.php');
    } else {
        flash('danger', 'Codigo validador invalido.');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a237e">
    <link rel="manifest" href="manifest.json">
    <title>Validador SOE</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/escaner.css" rel="stylesheet">
</head>
<body class="scanner-page auth-page">
<main class="auth-card">
    <h1>Validador</h1>
    <?php foreach (consume_flash() as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="form-label">Codigo de referencia SOE</label>
        <input class="form-control form-control-lg mb-4" name="codigo" autocomplete="one-time-code" required>
        <button class="btn btn-primary btn-lg w-100">Entrar al escaner</button>
    </form>
</main>
</body>
</html>
