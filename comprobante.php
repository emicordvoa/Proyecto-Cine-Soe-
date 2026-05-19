<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/Database.php';
require __DIR__ . '/classes/Compra.php';
require __DIR__ . '/classes/FileUploader.php';

$compraId = filter_input(INPUT_GET, 'compra', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'compra', FILTER_VALIDATE_INT);

if ($compraId && isset($_GET['expirar'])) {
    Compra::cancelarSiExpirada($compraId);
    flash('warning', 'Tiempo agotado.');
    redirect('index.php');
}

$compra = $compraId ? Compra::detalle($compraId) : null;
if (!$compra) {
    http_response_code(404);
    exit('Compra no encontrada.');
}

if (Compra::cancelarSiExpirada((int) $compra['id'])) {
    flash('warning', 'Tiempo agotado.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesion expirada.');
        }

        $compra = Compra::detalle((int) $compra['id']);
        if (!$compra || $compra['estado'] !== 'activo') {
            throw new RuntimeException('La compra ya no esta activa.');
        }

        if (Compra::tiempoRestanteSegundos($compra) <= 0) {
            Compra::cancelarSiExpirada((int) $compra['id']);
            throw new RuntimeException('Tiempo agotado.');
        }

        if (empty($_FILES['comprobante']) || ($_FILES['comprobante']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Debes seleccionar un comprobante antes de enviar.');
        }

        $comprobanteAnterior = basename((string) ($compra['comprobante_nombre'] ?? ''));
        $fileName = FileUploader::comprobante($_FILES['comprobante'] ?? [], (int) $compra['id']);
        $stmt = Database::getConnection()->prepare("UPDATE compras SET comprobante_nombre = ?, estado_pago = 'pendiente' WHERE id = ?");
        $stmt->execute([$fileName, (int) $compra['id']]);

        if ($comprobanteAnterior !== '' && $comprobanteAnterior !== '.') {
            $rutaAnterior = UPLOAD_PATH . '/comprobantes/pendientes/' . $comprobanteAnterior;
            if (@is_file($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }

        flash('success', 'Gracias por tu compra. Recibimos tu comprobante y tus cupos quedan reservados. Cuando el pago sea aprobado, enviaremos tus entradas a tu correo.');
        redirect('index.php');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}

$tiempoRestante = Compra::tiempoRestanteSegundos($compra);
$waVendedorCompra = $_SESSION['wa_vendedor_compra_' . (int) $compra['id']] ?? null;
$comprobanteUrl = '';
$fileExists = false;
$safeName = basename((string) ($compra['comprobante_nombre'] ?? ''));
if ($safeName !== '') {
    $dirs = ['pendientes', 'verificados', 'rechazados'];
    foreach ($dirs as $d) {
        $path = UPLOAD_PATH . '/comprobantes/' . $d . '/' . $safeName;
        if (is_file($path)) {
            $comprobanteUrl = 'uploads/comprobantes/' . $d . '/' . rawurlencode($safeName);
            $fileExists = true;
            break;
        }
    }
    if ($comprobanteUrl === '') {
        $comprobanteUrl = 'uploads/comprobantes/pendientes/' . rawurlencode($safeName);
    }
}
$qrPagoGeneral = 'assets/img/qr-bancario.jpeg';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subir comprobante - Cine SOE</title>
    <link href="<?= e(asset('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="site-bg">
<nav class="navbar scrolled">
    <a class="brand" href="index.php"><span class="brand-icon">SOE</span> Cine Universitario</a>
    <div class="nav-links"><a class="btn btn-ghost" href="index.php">← Inicio</a></div>
</nav>

<main class="checkout-page">
<div class="container">
    <div class="payment-layout">
        <div class="glass p-3">
            <p class="section-eyebrow">Pago QR bancario</p>
            <h1 style="font-size:clamp(1.5rem,3vw,2rem);font-weight:900;margin-bottom:.5rem">Compra <?= e($compra['codigo_compra']) ?></h1>
            <p class="text-muted text-sm"><?= e($compra['titulo']) ?> - <?= (int) $compra['cantidad_entradas'] ?> entrada(s)</p>
            <p class="text-muted text-sm mb-3">Al subir el comprobante, tus cupos quedan reservados hasta la validacion.</p>

            <?php if (empty($compra['comprobante_nombre'])): ?>
            <div class="timer-box-new" data-countdown="<?= (int) $tiempoRestante ?>" data-expire-url="comprobante.php?compra=<?= (int) $compra['id'] ?>&expirar=1">
                <div class="timer-circle">
                    <svg viewBox="0 0 100 100"><circle class="timer-track" cx="50" cy="50" r="46"/><circle class="timer-progress" cx="50" cy="50" r="46"/></svg>
                    <div class="timer-text" id="countdownText">10:00</div>
                </div>
                <p class="text-sm" style="color:#fde68a;margin-top:.5rem">Tiempo restante para subir comprobante</p>
            </div>
            <?php endif; ?>

            <div class="bank-qr mt-3">
                <?php if (file_exists(__DIR__ . '/' . $qrPagoGeneral)): ?>
                    <img src="<?= e($qrPagoGeneral) ?>" alt="QR bancario">
                <?php else: ?>
                    <div style="padding:2rem;text-align:center;font-weight:900;color:#111">QR Bancario SOE</div>
                <?php endif; ?>
            </div>

            <ul class="bank-list mt-3">
                <li><strong>Monto:</strong> Bs <?= number_format((float) $compra['monto_total'], 2) ?></li>
                <li><strong>Staff SOE:</strong> <?= e($compra['vendedor_nombre'] ?? 'Venta online') ?> <?= $compra['vendedor_whatsapp'] ? '(' . e($compra['vendedor_whatsapp']) . ')' : '' ?></li>
            </ul>

            <?php if ($waVendedorCompra): ?>
                <a class="btn btn-success btn-block mt-3" target="_blank" rel="noopener" href="<?= e($waVendedorCompra) ?>">Notificar al Staff SOE</a>
            <?php endif; ?>
        </div>

        <div class="glass p-3">
            <?php foreach (consume_flash() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>

            <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:1rem">Comprobante</h2>

            <?php if ($compra['comprobante_nombre']): ?>
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
                        <?php if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre']) && $fileExists): ?>
                            <img class="receipt-preview" src="<?= e($comprobanteUrl) ?>" alt="Comprobante">
                        <?php elseif (preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre']) && !$fileExists): ?>
                            <div class="receipt-pdf">Imagen no disponible</div>
                        <?php else: ?>
                            <div class="receipt-pdf"><?php if ($fileExists) echo 'PDF'; else echo 'Comprobante no disponible'; ?></div>
                        <?php endif; ?>
                </div>
                    <button class="btn btn-ghost btn-block" type="button" data-receipt-open data-receipt-url="<?= e($comprobanteUrl) ?>" data-receipt-kind="<?= preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre']) ? 'image' : 'pdf' ?>" data-receipt-exists="<?= $fileExists ? '1' : '0' ?>">Ver comprobante completo</button>
                <p class="text-muted text-sm mt-2">Puedes reemplazarlo si subiste el archivo incorrecto.</p>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" data-receipt-form>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="compra" value="<?= (int) $compra['id'] ?>">
                <label class="upload-zone" for="receiptInput">
                    <span class="upload-icon">📎</span>
                    <span style="font-weight:700"><?= $compra['comprobante_nombre'] ? 'Reemplazar comprobante' : 'Arrastra tu comprobante aqui' ?></span>
                    <small class="text-muted mt-1">JPG, PNG o PDF - max 5MB</small>
                    <div class="upload-file-card" data-file-card>
                        <div class="upload-file-info">
                            <strong data-file-name>Comprobante seleccionado</strong>
                            <small data-file-meta>Listo para subir</small>
                        </div>
                    </div>
                    <img id="receiptPreview" alt="">
                </label>
                <input class="form-input mt-2" id="receiptInput" type="file" name="comprobante" accept=".jpg,.jpeg,.jfif,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required style="display:none">
                <div class="alert alert-danger mt-2" data-file-error style="display:none">Debes seleccionar un comprobante antes de enviar.</div>
                <button class="btn btn-primary btn-lg btn-block mt-3" type="submit" data-file-submit="receiptInput"><?= $compra['comprobante_nombre'] ? 'Reemplazar Comprobante' : 'Subir Comprobante' ?></button>
            </form>
        </div>
    </div>
</div>
</main>

<div class="receipt-modal" id="receiptModal" aria-hidden="true">
    <div class="receipt-modal-panel">
        <button class="receipt-modal-close" type="button" data-receipt-close>x</button>
        <div id="receiptModalContent"></div>
    </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
