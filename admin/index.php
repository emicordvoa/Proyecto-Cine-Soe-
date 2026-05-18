<?php
require __DIR__ . '/_bootstrap.php';
$pdo = Database::getConnection();

$viewVendorId = admin_view_vendor_id();
$vendorFilter = $viewVendorId ? ' AND id_usuario_vendedor = ' . (int) $viewVendorId : '';
$detalleFilter = $viewVendorId ? ' AND c.id_usuario_vendedor = ' . (int) $viewVendorId : '';

$stats = [
    'vendidas' => (int) $pdo->query("SELECT COALESCE(SUM(cantidad_entradas),0) FROM compras WHERE estado_pago='aprobado' AND estado='activo' $vendorFilter")->fetchColumn(),
    'ingresos' => (float) $pdo->query("SELECT COALESCE(SUM(monto_total),0) FROM compras WHERE estado_pago='aprobado' AND estado='activo' $vendorFilter")->fetchColumn(),
    'pendientes' => (int) $pdo->query("SELECT COUNT(*) FROM compras WHERE estado_pago='pendiente' AND comprobante_nombre IS NOT NULL AND estado='activo' $vendorFilter")->fetchColumn(),
    'disponibles' => (int) $pdo->query("SELECT COALESCE(SUM(capacidad-entradas_vendidas),0) FROM peliculas WHERE estado='activo'")->fetchColumn(),
];

$ultimas = $pdo->query(
    "SELECT c.codigo_compra, cl.nombre_completo AS cliente, p.titulo AS pelicula, c.monto_total, c.estado_pago, c.fecha_creacion
     FROM compras c JOIN clientes cl ON cl.id = c.id_cliente JOIN peliculas p ON p.id = c.id_pelicula
     WHERE c.estado = 'activo' AND (c.comprobante_nombre IS NOT NULL OR c.estado_pago = 'aprobado') $detalleFilter
     ORDER BY c.fecha_creacion DESC LIMIT 8"
)->fetchAll();

$top = $pdo->query(
    "SELECT COALESCE(u.nombre_completo,'Online') vendedor, COALESCE(SUM(c.cantidad_entradas),0) entradas
     FROM compras c LEFT JOIN usuarios u ON u.id=c.id_usuario_vendedor
     WHERE c.estado='activo' AND c.estado_pago='aprobado' GROUP BY c.id_usuario_vendedor ORDER BY entradas DESC LIMIT 5"
)->fetchAll();

$referido = BASE_URL . '/index.php?ref=' . Auth::user()['codigo_referencia'];
admin_header($viewVendorId ? 'Ventas del Staff SOE' : 'Dashboard');
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Vendidas
        </div>
        <div class="stat-card-value" data-count="<?= $stats['vendidas'] ?>"><?= $stats['vendidas'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Ingresos
        </div>
        <div class="stat-card-value" data-count="<?= $stats['ingresos'] ?>" data-prefix="Bs ">Bs <?= number_format($stats['ingresos'], 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Pendientes
        </div>
        <div class="stat-card-value" data-count="<?= $stats['pendientes'] ?>"><?= $stats['pendientes'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 0 0-8 0v2"/></svg>
            Disponibles
        </div>
        <div class="stat-card-value" data-count="<?= $stats['disponibles'] ?>"><?= $stats['disponibles'] ?></div>
    </div>
</div>

<?php if (admin_can_sell()): ?>
<div class="glass admin-panel">
    <label class="ref-label">Mi enlace de referido</label>
    <p class="text-muted text-sm mb-2">Comparte este enlace para registrar ventas a tu usuario.</p>
    <div class="copy-box" style="position:relative">
        <code><?= e($referido) ?></code>
        <button class="copy-btn" type="button" data-copy="<?= e($referido) ?>">Copiar</button>
        <span class="copy-toast">¡Copiado!</span>
    </div>
</div>
<?php endif; ?>

<div class="glass admin-panel">
    <h2>Últimas compras</h2>
    <div class="table-wrap">
        <table class="table"><thead><tr><th>Código</th><th>Cliente</th><th>Película</th><th>Monto</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($ultimas as $compra): ?>
            <tr>
                <td><strong><?= e($compra['codigo_compra']) ?></strong></td>
                <td><?= e($compra['cliente']) ?></td>
                <td><?= e($compra['pelicula']) ?></td>
                <td>Bs <?= number_format((float) $compra['monto_total'], 2) ?></td>
                <td><span class="badge badge-<?= $compra['estado_pago']==='aprobado'?'success':($compra['estado_pago']==='pendiente'?'warning':'danger') ?>"><?= e($compra['estado_pago']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<div class="glass admin-panel">
    <h2>Ventas por película</h2>
    <canvas id="salesChart" height="120"></canvas>
</div>

<?php if (admin_is_admin() && !$viewVendorId): ?>
<div class="glass admin-panel">
    <h2>Top Staff SOE</h2>
    <?php foreach ($top as $item): ?>
        <div class="flex items-center justify-between" style="padding:.5rem 0;border-bottom:1px solid var(--line)">
            <span><?= e($item['vendedor']) ?></span>
            <strong class="text-cyan"><?= (int) $item['entradas'] ?> entradas</strong>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
fetch('../api/estadisticas.php').then(r=>r.json()).then(data=>{
  if(!window.Chart||!data.ok)return;
  new Chart(document.getElementById('salesChart'),{
    type:'bar',
    data:{labels:data.ventas.map(v=>v.titulo),datasets:[{label:'Entradas',data:data.ventas.map(v=>v.entradas),backgroundColor:'rgba(53,215,210,.6)',borderRadius:8,borderSkipped:false}]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'rgba(255,255,255,.06)'},ticks:{color:'rgba(255,255,255,.5)'}},x:{grid:{display:false},ticks:{color:'rgba(255,255,255,.5)'}}}}
  });
});
</script>
<?php admin_footer(); ?>
