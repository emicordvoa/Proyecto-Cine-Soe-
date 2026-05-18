<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';
require __DIR__ . '/../classes/Entrada.php';

Auth::requireRole(['admin', 'vendedor', 'validador']);
$totalValidadas = Entrada::totalValidadas();
$peliculaContador = Entrada::peliculaContadorActual() ?? 'película actual';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a237e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SOE Cine">
    <link rel="manifest" href="manifest.json">
    <title>Control en puerta - Cine SOE</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/escaner.css?v=20260517-cleancontrols" rel="stylesheet">
</head>
<body class="scanner-page">
<header class="scanner-top">
    <div>
        <strong>Control en puerta</strong>
        <div class="text-white-50">Validador: <?= e(Auth::user()['nombre']) ?></div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-light btn-sm" href="../admin/index.php">Panel</a>
        <span id="sessionCount"><?= (int) $totalValidadas ?></span>
        <span id="statusDot" class="status-dot"></span>
    </div>
</header>

<main>
    <section class="scanner-hero">
        <div>
            <span class="scanner-kicker">Control en puerta</span>
            <h1 id="scannerMovieTitle"><?= e($peliculaContador) ?></h1>
            <p>Escanea cada ticket individual. Verde válido, amarillo usado, rojo inválido.</p>
        </div>
        <div class="scanner-counter">
            <span id="sessionCountHero"><?= (int) $totalValidadas ?></span>
            <small id="counterLabel">validadas</small>
        </div>
    </section>

    <section class="camera-permission" id="cameraPermission">
        <div>
            <strong>Activa la cámara para escanear</strong>
            <p>El navegador pedirá permiso. Acepta el acceso a la cámara para validar los QR.</p>
        </div>
        <button class="btn btn-primary btn-lg" id="startCameraBtn" type="button">Activar cámara</button>
    </section>

    <section class="scanner-frame">
        <div id="reader"></div>
        <div class="scan-line"></div>

        <section id="resultBox" class="scanner-result">
            <div class="last-scan" id="lastScan">Esperando QR...</div>
            <h1 id="resultTitle">LISTO</h1>
            <p id="resultMessage">Escanea el QR del ticket para validar una entrada individual.</p>
            <div class="entry-data" id="entryData"></div>
        </section>
    </section>

    <form class="manual-box" id="manualForm">
        <div class="qty-control">
            <input class="form-control form-control-lg" id="manualToken" value="SOE-000" inputmode="numeric" autocomplete="off" aria-label="Código manual de ticket">
            <button class="btn btn-primary" type="submit">OK</button>
        </div>
    </form>
</main>

<script src="../assets/js/html5-qrcode.min.js?v=20260517-cleancontrols"></script>
<script src="../assets/js/escaner.js?v=20260517-cleancontrols"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js?v=20260517-cleancontrols', {updateViaCache: 'none'});
    navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (!sessionStorage.getItem('scanner_sw_reloaded')) {
            sessionStorage.setItem('scanner_sw_reloaded', '1');
            window.location.reload();
        }
    });
}
</script>
</body>
</html>
