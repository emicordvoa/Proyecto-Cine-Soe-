<?php
require __DIR__ . '/_bootstrap.php';
$compraId = filter_input(INPUT_GET, 'compra', FILTER_VALIDATE_INT);
if (!$compraId) { http_response_code(404); exit('Compra no encontrada.'); }
$compra = Compra::detalle($compraId);
if (!$compra) { http_response_code(404); exit('Compra no encontrada.'); }
if (admin_is_vendor() && (int) $compra['id_usuario_vendedor'] !== (int) Auth::id()) { http_response_code(403); exit('No autorizado.'); }

Entrada::prepararCodigosTicket();
$stmt = Database::getConnection()->prepare("SELECT e.*, cl.nombre_completo AS cliente, p.titulo, p.fecha_funcion, p.hora_funcion, p.imagen, c.codigo_compra FROM entradas e JOIN compras c ON c.id=e.id_compra JOIN clientes cl ON cl.id=c.id_cliente JOIN peliculas p ON p.id=e.id_pelicula WHERE e.id_compra=? AND e.eliminado=0 ORDER BY e.id");
$stmt->execute([$compraId]); $entradas = $stmt->fetchAll();

$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$pdfFileName = 'entradas-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $compra['codigo_compra']) . '.pdf';
$waTel = preg_replace('/\D+/', '', (string) ($compra['telefono'] ?? ''));
$waText = 'Hola ' . ($compra['nombre_completo'] ?? 'cliente') . ', te envío tus entradas Cine SOE en PDF.';
$waUrl = $waTel ? 'https://wa.me/' . $waTel . '?text=' . urlencode($waText) : '';
$autoEmail = ($_GET['autoemail'] ?? '') === '1';
admin_header('Entradas generadas');
?>
<div class="glass admin-panel ticket-print-actions">
    <a class="btn btn-ghost" href="validar.php">← Volver</a>
    <button class="btn btn-primary" type="button" data-download-ticket="#ticketsPDF" data-filename="<?= e($pdfFileName) ?>">Descargar PDF</button>
    <button class="btn btn-ghost" type="button" data-email-ticket="#ticketsPDF" data-email-url="../api/enviar-ticket-pdf.php" data-compra="<?= (int) $compra['id'] ?>" data-csrf="<?= e(csrf_token()) ?>" data-filename="<?= e($pdfFileName) ?>" data-auto-email-ticket="<?= $autoEmail ? '1' : '0' ?>">Enviar por correo</button>
    <?php if ($waUrl): ?><button class="btn btn-success" type="button" data-share-ticket="#ticketsPDF" data-filename="<?= e($pdfFileName) ?>" data-whatsapp-url="<?= e($waUrl) ?>">WhatsApp</button><?php endif; ?>
</div>
<div class="alert alert-info" data-email-ticket-status role="status" style="display:none"></div>

<section class="tickets-print-grid" id="ticketsPDF">
<?php foreach ($entradas as $ticket): ?>
    <?php
    $fecha = date('d', strtotime($ticket['fecha_funcion'])) . ' ' . $meses[date('m', strtotime($ticket['fecha_funcion']))] . ', ' . date('Y', strtotime($ticket['fecha_funcion']));
    $hora = substr($ticket['hora_funcion'], 0, 5);
    $qrContent = rtrim(SITE_URL, '/') . '/validar/' . $ticket['token_validacion'];
    $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($qrContent);
    $posterUrl = !empty($ticket['imagen']) ? asset('assets/img/' . $ticket['imagen']) : '';
    $posterStyle = $posterUrl ? "--ticket-bg: url('" . e($posterUrl) . "');" : '';
    ?>
    <article class="ticket-card admin-ticket-card">
        <div class="ticket-head" style="<?= $posterStyle ?>">
            <div class="ticket-label">ID TICKET</div>
            <div class="ticket-id"><?= e($ticket['codigo_ticket']) ?></div>
            <h2 class="ticket-movie"><?= e($ticket['titulo']) ?></h2>
            <div class="ticket-hall">Auditorio Torre América - Piso 12</div>
        </div>
        <div class="ticket-body">
            <div class="ticket-grid">
                <div><small>Fecha</small><strong><?= e($fecha) ?></strong></div>
                <div><small>Hora</small><strong><?= e($hora) ?></strong></div>
                <div><small>Entrada</small><strong>#<?= (int) $ticket['numero_entrada'] ?></strong></div>
                <div><small>Cliente</small><strong><?= e($ticket['cliente']) ?></strong></div>
            </div>
            <img class="ticket-qr" src="<?= e($qrSrc) ?>" alt="QR de entrada">
            <div class="ticket-footer-text">Arte - Marketing</div>
        </div>
    </article>
<?php endforeach; ?>
</section>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<?php admin_footer(); ?>
