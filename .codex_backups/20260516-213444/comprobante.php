<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/Database.php';
require __DIR__ . '/classes/Compra.php';
require __DIR__ . '/classes/FileUploader.php';
require __DIR__ . '/classes/Mailer.php';

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
            throw new RuntimeException('Sesion expirada. Intenta nuevamente.');
        }

        $compra = Compra::detalle((int) $compra['id']);
        if (!$compra || $compra['estado'] !== 'activo') {
            throw new RuntimeException('La compra ya no esta activa.');
        }

        if (!empty($compra['comprobante_nombre'])) {
            throw new RuntimeException('Esta compra ya tiene un comprobante registrado.');
        }

        if (Compra::tiempoRestanteSegundos($compra) <= 0) {
            Compra::cancelarSiExpirada((int) $compra['id']);
            throw new RuntimeException('Tiempo agotado.');
        }

        $fileName = FileUploader::comprobante($_FILES['comprobante'] ?? [], (int) $compra['id']);
        $stmt = Database::getConnection()->prepare("UPDATE compras SET comprobante_nombre = ?, estado_pago = 'pendiente' WHERE id = ?");
        $stmt->execute([$fileName, (int) $compra['id']]);

        Mailer::enviar(
            $compra['correo'],
            'Comprobante recibido - ' . $compra['codigo_compra'],
            'Hola ' . e($compra['nombre_completo']) . ', recibimos tu comprobante. Espera la validacion del equipo SOE.'
        );

        flash('success', 'Comprobante enviado. Esperando validacion.');
        redirect('comprobante.php?compra=' . (int) $compra['id']);
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}

$tiempoRestante = Compra::tiempoRestanteSegundos($compra);
$waVendedorCompra = $_SESSION['wa_vendedor_compra_' . (int) $compra['id']] ?? null;
$comprobanteUrl = $compra['comprobante_nombre'] ? 'uploads/comprobantes/pendientes/' . rawurlencode($compra['comprobante_nombre']) : '';
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
<main class="container py-4 py-md-5">
    <div class="payment-grid">
        <section class="checkout-shell">
            <p class="eyebrow">Pago QR bancario</p>
            <h1>Compra <?= e($compra['codigo_compra']) ?></h1>
            <p class="text-white-50"><?= e($compra['titulo']) ?> - <?= (int) $compra['cantidad_entradas'] ?> entrada(s)</p>

            <?php if (empty($compra['comprobante_nombre'])): ?>
                <div class="timer-box" data-countdown="<?= (int) $tiempoRestante ?>" data-expire-url="comprobante.php?compra=<?= (int) $compra['id'] ?>&expirar=1">
                    Tiempo restante: <strong id="countdownText">10:00</strong>
                </div>
            <?php endif; ?>

            <div class="bank-qr">
                <?php if (file_exists(__DIR__ . '/assets/img/qr-bancario.png')): ?>
                    <img src="assets/img/qr-bancario.png" alt="QR bancario">
                <?php else: ?>
                    <span>QR bancario SOE</span>
                <?php endif; ?>
            </div>
            <ul class="bank-data">
                <li><strong>Monto:</strong> Bs <?= number_format((float) $compra['monto_total'], 2) ?></li>
                <li><strong>Cuenta:</strong> Banco Union - 1234567890</li>
                <li><strong>Titular:</strong> SOE Universidad</li>
                <li><strong>Vendedor:</strong> <?= e($compra['vendedor_nombre'] ?? 'Venta online') ?> <?= $compra['vendedor_whatsapp'] ? '(' . e($compra['vendedor_whatsapp']) . ')' : '' ?></li>
            </ul>
            <?php if ($waVendedorCompra): ?>
                <a class="btn btn-success btn-lg w-100" target="_blank" rel="noopener" href="<?= e($waVendedorCompra) ?>">Notificar al vendedor</a>
            <?php endif; ?>
        </section>

        <section class="checkout-shell">
            <?php foreach (consume_flash() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
            <h2>Comprobante</h2>

            <?php if ($compra['comprobante_nombre']): ?>
                <div class="receipt-thumb-wrap">
                    <?php if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre'])): ?>
                        <img class="receipt-thumb" src="<?= e($comprobanteUrl) ?>" alt="Comprobante">
                    <?php else: ?>
                        <div class="receipt-thumb receipt-pdf">PDF</div>
                    <?php endif; ?>
                </div>
                <button class="btn btn-outline-light w-100 mt-3" type="button" data-receipt-open data-receipt-url="<?= e($comprobanteUrl) ?>" data-receipt-kind="<?= preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre']) ? 'image' : 'pdf' ?>">Ver comprobante completo</button>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="compra" value="<?= (int) $compra['id'] ?>">
                    <label class="upload-preview" for="receiptInput">
                        <span>Seleccionar JPG, PNG o PDF hasta 5MB</span>
                        <img id="receiptPreview" alt="">
                    </label>
                    <input class="form-control form-control-lg mt-3" id="receiptInput" type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>
                    <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Subir comprobante</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>
<div class="receipt-modal" id="receiptModal" aria-hidden="true">
    <div class="receipt-modal-panel">
        <button class="receipt-modal-close" type="button" data-receipt-close>Cerrar</button>
        <div id="receiptModalContent"></div>
    </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
