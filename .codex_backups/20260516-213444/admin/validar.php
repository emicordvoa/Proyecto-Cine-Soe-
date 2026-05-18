<?php
require __DIR__ . '/_bootstrap.php';
$pdo = Database::getConnection();

function mover_comprobante(string $archivo, string $destino): void
{
    $origen = UPLOAD_PATH . '/comprobantes/pendientes/' . basename($archivo);
    $final = UPLOAD_PATH . '/comprobantes/' . $destino . '/' . basename($archivo);
    if (is_file($origen)) {
        rename($origen, $final);
    }
}

function comprobante_preview(string $archivo): string
{
    $safe = basename($archivo);
    $url = '../uploads/comprobantes/pendientes/' . rawurlencode($safe);
    $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return '<img class="receipt-preview" src="' . e($url) . '" alt="Comprobante">';
    }

    if ($ext === 'pdf') {
        return '<div class="receipt-thumb receipt-pdf">PDF</div>';
    }

    return '<div class="receipt-thumb receipt-pdf">ARCHIVO</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $accion = $_POST['accion'] ?? '';
        $compra = $id ? Compra::detalle($id) : null;

        if (!$compra) {
            throw new RuntimeException('Compra no encontrada.');
        }

        if (admin_is_vendor() && (int) $compra['id_usuario_vendedor'] !== (int) Auth::id()) {
            throw new RuntimeException('Solo puedes validar compras hechas con tu enlace.');
        }

        if ($accion === 'aprobar') {
            if (empty($compra['comprobante_nombre'])) {
                throw new RuntimeException('La compra no tiene comprobante.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE compras SET estado_pago='aprobado', id_usuario_validador=?, fecha_validacion=NOW() WHERE id=?")
                ->execute([Auth::id(), $id]);
            $existian = $pdo->prepare("SELECT COUNT(*) FROM entradas WHERE id_compra=? AND eliminado=0");
            $existian->execute([$id]);
            $entradasAntes = (int) $existian->fetchColumn();
            $tokens = Entrada::generarParaCompra($compra);

            if ($entradasAntes === 0) {
                $pdo->prepare("UPDATE peliculas SET entradas_vendidas = entradas_vendidas + ? WHERE id = ? AND entradas_vendidas + ? <= capacidad")
                    ->execute([(int) $compra['cantidad_entradas'], (int) $compra['id_pelicula'], (int) $compra['cantidad_entradas']]);
            }
            $pdo->commit();

            mover_comprobante($compra['comprobante_nombre'], 'aprobados');
            Mailer::enviarTickets($compra, $tokens);

            $_SESSION['wa_cliente_tickets'] = Notificacion::enviarWhatsAppCliente($compra['telefono'] ?? '', $tokens);
            $_SESSION['tickets_generados_compra'] = (int) $compra['id'];
            flash('success', 'Pago aprobado. Entradas generadas.');
        } elseif ($accion === 'rechazar') {
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            $pdo->prepare("UPDATE compras SET estado_pago='rechazado', id_usuario_validador=?, fecha_validacion=NOW(), motivo_rechazo=? WHERE id=?")
                ->execute([Auth::id(), $motivo, $id]);

            if ($compra['comprobante_nombre']) {
                mover_comprobante($compra['comprobante_nombre'], 'rechazados');
            }

            Mailer::enviar(
                $compra['correo'],
                'Pago rechazado - ' . $compra['codigo_compra'],
                'Tu comprobante fue rechazado. Motivo: ' . e($motivo ?: 'No especificado')
            );
            flash('warning', 'Pago rechazado.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', $exception->getMessage());
    }
    redirect('validar.php');
}

$wherePendientes = "WHERE estado_pago='pendiente' AND comprobante_nombre IS NOT NULL";
if (admin_is_vendor()) {
    $wherePendientes .= ' AND id_usuario_vendedor = ' . (int) Auth::id();
}
$pendientes = $pdo->query("SELECT * FROM v_compras_detalle $wherePendientes ORDER BY fecha_creacion DESC")->fetchAll();
$waCliente = $_SESSION['wa_cliente_tickets'] ?? null;
$ticketsCompra = $_SESSION['tickets_generados_compra'] ?? null;
unset($_SESSION['wa_cliente_tickets'], $_SESSION['tickets_generados_compra']);

$whereAprobadas = "WHERE estado_pago='aprobado'";
if (admin_is_vendor()) {
    $whereAprobadas .= ' AND id_usuario_vendedor = ' . (int) Auth::id();
}
$aprobadas = $pdo->query("SELECT * FROM v_compras_detalle $whereAprobadas ORDER BY fecha_validacion DESC, fecha_creacion DESC LIMIT 12")->fetchAll();
admin_header(admin_is_vendor() ? 'Validar mis pagos' : 'Validar pagos');
?>
<section class="checkout-shell qr-admin-panel mb-4">
    <div class="qr-admin-heading">
        <div>
            <span class="eyebrow">Control en puerta</span>
            <h2>Validar QR con cámara</h2>
            <p class="text-white-50 mb-0">Escanea cada ticket individual. Verde válido, amarillo usado, rojo inválido.</p>
        </div>
        <div class="qr-counter"><span id="sessionCount">0</span><small>validadas</small></div>
    </div>

    <div class="qr-admin-grid">
        <section class="scanner-frame admin-scanner-frame">
            <div id="reader"></div>
            <div class="scan-line"></div>
        </section>

        <section id="resultBox" class="scanner-result admin-scanner-result">
            <div class="last-scan" id="lastScan">Esperando QR...</div>
            <span id="statusDot" class="status-dot"></span>
            <h1 id="resultTitle">LISTO</h1>
            <p id="resultMessage">Apunta la cámara al QR del ticket para validar la entrada.</p>
            <div class="entry-data" id="entryData"></div>
            <div class="scanner-actions">
                <button class="btn btn-outline-light" id="torchBtn" type="button">Linterna</button>
                <button class="btn btn-outline-light" id="zoomBtn" type="button">Zoom</button>
                <button class="btn btn-primary wide" id="restartBtn" type="button">ESCANEAR SIGUIENTE</button>
            </div>
            <form class="manual-box" id="manualForm">
                <div class="qty-control">
                    <input class="form-control" id="manualToken" placeholder="Token manual">
                    <button class="btn btn-primary" type="submit">OK</button>
                </div>
            </form>
        </section>
    </div>
</section>

<section class="checkout-shell">
    <h2>Comprobantes pendientes</h2>
    <?php if ($ticketsCompra): ?>
        <a class="btn btn-primary btn-lg mb-4" href="tickets.php?compra=<?= (int) $ticketsCompra ?>">Ver entradas generadas / Descargar PDF</a>
    <?php endif; ?>
    <?php if ($waCliente): ?>
        <a class="btn btn-success btn-lg mb-4" target="_blank" rel="noopener" href="<?= e($waCliente) ?>">Enviar tickets por WhatsApp al cliente</a>
    <?php endif; ?>

    <?php foreach ($pendientes as $compra): ?>
        <article class="stat-card mt-3 payment-card">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <strong><?= e($compra['cliente']) ?></strong><br>
                    <?= e($compra['pelicula']) ?> - <?= (int) $compra['cantidad_entradas'] ?> entrada(s)<br>
                    Bs <?= number_format((float) $compra['monto_total'], 2) ?> - Vendedor: <?= e($compra['vendedor'] ?? 'Online') ?><br>
                    <span class="text-white-50"><?= e($compra['fecha_creacion']) ?></span>
                </div>
                <form method="post" class="d-flex flex-column gap-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $compra['id'] ?>">
                    <input class="form-control" name="motivo" placeholder="Motivo rechazo opcional">
                    <button class="btn btn-primary" name="accion" value="aprobar">APROBAR</button>
                    <button class="btn btn-danger" name="accion" value="rechazar">RECHAZAR</button>
                </form>
            </div>
            <?php $receiptUrl = '../uploads/comprobantes/pendientes/' . rawurlencode($compra['comprobante_nombre']); ?>
            <div class="mt-3"><?= comprobante_preview($compra['comprobante_nombre']) ?></div>
            <button class="btn btn-outline-light mt-3" type="button" data-receipt-open data-receipt-url="<?= e($receiptUrl) ?>" data-receipt-kind="<?= preg_match('/\.(jpg|jpeg|png|webp)$/i', $compra['comprobante_nombre']) ? 'image' : 'pdf' ?>">Ver comprobante completo</button>
        </article>
    <?php endforeach; ?>
</section>
<section class="checkout-shell mt-4">
    <h2>Compras aprobadas con entradas</h2>
    <table class="table"><thead><tr><th>Código</th><th>Cliente</th><th>Película</th><th>Entradas</th><th>Acción</th></tr></thead><tbody>
    <?php foreach ($aprobadas as $aprobada): ?>
        <?php
        $telefonoCliente = preg_replace('/\D+/', '', (string) ($aprobada['telefono'] ?? ''));
        $mensajeWhatsapp = sprintf(
            'Hola %s, soy %s. Te envio tus entradas Cine SOE para %s. Abre el PDF y guardalo para presentar tus QR.',
            $aprobada['cliente'],
            Auth::user()['nombre'],
            $aprobada['pelicula']
        );
        $whatsappUrl = $telefonoCliente ? 'https://wa.me/' . $telefonoCliente . '?text=' . urlencode($mensajeWhatsapp) : '';
        ?>
        <tr>
            <td><?= e($aprobada['codigo_compra']) ?></td>
            <td><?= e($aprobada['cliente']) ?></td>
            <td><?= e($aprobada['pelicula']) ?></td>
            <td><?= (int) $aprobada['cantidad_entradas'] ?></td>
            <td class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary btn-sm" href="tickets.php?compra=<?= (int) $aprobada['id'] ?>">Ver / PDF</a>
                <?php if ($whatsappUrl): ?>
                    <a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="<?= e($whatsappUrl) ?>">WhatsApp</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
</section>
<div class="receipt-modal" id="receiptModal" aria-hidden="true">
    <div class="receipt-modal-panel" role="dialog" aria-modal="true" aria-label="Comprobante completo">
        <button class="receipt-modal-close" type="button" data-receipt-close aria-label="Cerrar comprobante">×</button>
        <div id="receiptModalContent"></div>
    </div>
</div>
<script src="../assets/js/html5-qrcode.min.js"></script>
<script src="../assets/js/escaner.js"></script>
<?php admin_footer(); ?>
