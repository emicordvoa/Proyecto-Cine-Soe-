<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/Database.php';
require __DIR__ . '/classes/Entrada.php';

$token = trim((string) ($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) { http_response_code(404); exit('Ticket no encontrado.'); }

Entrada::prepararCodigosTicket();
$stmt = Database::getConnection()->prepare(
    "SELECT e.*, cl.nombre_completo AS cliente, p.titulo, p.fecha_funcion, p.hora_funcion, p.imagen, c.codigo_compra
     FROM entradas e JOIN compras c ON c.id = e.id_compra JOIN clientes cl ON cl.id = c.id_cliente JOIN peliculas p ON p.id = e.id_pelicula
     WHERE e.token_validacion = ? AND e.eliminado = 0 LIMIT 1"
);
$stmt->execute([$token]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); exit('Ticket no encontrado.'); }

$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$fecha = date('d', strtotime($ticket['fecha_funcion'])) . ' ' . $meses[date('m', strtotime($ticket['fecha_funcion']))] . ', ' . date('Y', strtotime($ticket['fecha_funcion']));
$hora = substr($ticket['hora_funcion'], 0, 5);
$qrContent = rtrim(SITE_URL, '/') . '/validar/' . $ticket['token_validacion'];
$qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($qrContent);
$posterUrl = !empty($ticket['imagen']) ? asset('assets/img/' . $ticket['imagen']) : '';
$posterStyle = $posterUrl ? "--ticket-bg: url('" . e($posterUrl) . "');" : '';
$fileName = 'ticket-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $ticket['codigo_compra']) . '-' . substr((string) $ticket['token_validacion'], 0, 8) . '.pdf';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu Ticket — <?= e($ticket['codigo_compra']) ?></title>
    <link href="<?= e(asset('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="ticket-page">
<main class="ticket-shell">
    <div class="ticket-title">
        <a href="index.php" aria-label="Volver">&times;</a>
        <h1>Tu Ticket</h1>
    </div>

    <div id="ticketPDF">
        <article class="ticket-card">
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
    </div>

    <div class="ticket-actions">
        <button class="btn btn-primary btn-lg btn-block" type="button" data-download-ticket="#ticketPDF" data-filename="<?= e($fileName) ?>">Descargar PDF</button>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
