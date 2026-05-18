<?php
require __DIR__ . '/_bootstrap.php';
$pdo = Database::getConnection();
$viewVendorId = admin_view_vendor_id();

if (admin_is_admin() && !$viewVendorId) {
    $vendedores = $pdo->query("SELECT COALESCE(u.nombre_completo,'Online') vendedor, COALESCE(SUM(c.cantidad_entradas),0) entradas, COALESCE(SUM(c.monto_total),0) ingresos FROM compras c LEFT JOIN usuarios u ON u.id=c.id_usuario_vendedor WHERE c.estado='activo' AND c.estado_pago='aprobado' GROUP BY c.id_usuario_vendedor ORDER BY ingresos DESC")->fetchAll();
    $peliculas = $pdo->query("SELECT * FROM v_ventas_por_pelicula ORDER BY fecha_funcion, hora_funcion")->fetchAll();
    $validaciones = $pdo->query("SELECT u.nombre_completo, v.metodo, COUNT(*) total FROM validacion_entradas v JOIN usuarios u ON u.id=v.id_usuario GROUP BY u.id, v.metodo ORDER BY total DESC")->fetchAll();
    $compradores = [];
} else {
    $tid = $viewVendorId ?: Auth::id();
    $stmt = $pdo->prepare("SELECT AuthNombre.nombre_completo AS vendedor, COALESCE(SUM(c.cantidad_entradas),0) entradas, COALESCE(SUM(c.monto_total),0) ingresos FROM usuarios AuthNombre LEFT JOIN compras c ON c.id_usuario_vendedor=AuthNombre.id AND c.estado='activo' AND c.estado_pago='aprobado' WHERE AuthNombre.id=? GROUP BY AuthNombre.id");
    $stmt->execute([$tid]); $vendedores = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT p.titulo, p.capacidad, COALESCE(SUM(CASE WHEN c.estado_pago='aprobado' THEN c.cantidad_entradas ELSE 0 END),0) AS entradas_vendidas, ROUND((COALESCE(SUM(CASE WHEN c.estado_pago='aprobado' THEN c.cantidad_entradas ELSE 0 END),0)/p.capacidad)*100,2) AS porcentaje_ocupacion, COALESCE(SUM(CASE WHEN c.estado_pago='aprobado' THEN c.monto_total ELSE 0 END),0) AS ingresos_aprobados FROM peliculas p LEFT JOIN compras c ON c.id_pelicula=p.id AND c.estado='activo' AND c.id_usuario_vendedor=? WHERE p.estado!='eliminado' GROUP BY p.id ORDER BY p.fecha_funcion");
    $stmt->execute([$tid]); $peliculas = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT c.codigo_compra, cl.nombre_completo, cl.correo, cl.telefono, p.titulo, c.cantidad_entradas, c.monto_total, c.estado_pago, c.fecha_creacion FROM compras c JOIN clientes cl ON cl.id=c.id_cliente JOIN peliculas p ON p.id=c.id_pelicula WHERE c.id_usuario_vendedor=? AND c.estado='activo' AND (c.comprobante_nombre IS NOT NULL OR c.estado_pago='aprobado') ORDER BY c.fecha_creacion DESC");
    $stmt->execute([$tid]); $compradores = $stmt->fetchAll();
    $validaciones = [];
}
admin_header($viewVendorId ? 'Reporte filtrado' : (admin_is_vendor() ? 'Mis ventas' : 'Reportes'));
?>
<div class="glass admin-panel">
    <h2><?= admin_is_admin()&&!$viewVendorId?'Ranking vendedores':'Resumen' ?></h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Vendedor</th><th>Entradas</th><th>Ingresos</th></tr></thead><tbody>
    <?php foreach ($vendedores as $v): ?><tr><td><?= e($v['vendedor']) ?></td><td><strong><?= (int)$v['entradas'] ?></strong></td><td>Bs <?= number_format((float)$v['ingresos'],2) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php if ($viewVendorId): ?>
<div class="glass admin-panel">
    <h2>Compradores por mi enlace</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Cliente</th><th>Película</th><th>Entradas</th><th>Monto</th><th>Estado</th></tr></thead><tbody>
    <?php foreach ($compradores as $c): ?><tr><td><strong><?= e($c['nombre_completo']) ?></strong><br><span class="text-muted text-sm"><?= e($c['codigo_compra']) ?></span></td><td><?= e($c['titulo']) ?></td><td><?= (int)$c['cantidad_entradas'] ?></td><td>Bs <?= number_format((float)$c['monto_total'],2) ?></td><td><span class="badge badge-<?= $c['estado_pago']==='aprobado'?'success':($c['estado_pago']==='pendiente'?'warning':'danger') ?>"><?= e($c['estado_pago']) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<div class="glass admin-panel">
    <h2>Ventas por película</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Película</th><th>Vendidas</th><th>Capacidad</th><th>%</th><th>Ingresos</th></tr></thead><tbody>
    <?php foreach ($peliculas as $p): ?><tr><td><?= e($p['titulo']) ?></td><td><strong><?= (int)$p['entradas_vendidas'] ?></strong></td><td><?= (int)$p['capacidad'] ?></td><td><?= e((string)$p['porcentaje_ocupacion']) ?>%</td><td>Bs <?= number_format((float)$p['ingresos_aprobados'],2) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php if (admin_is_admin()): ?>
<div class="glass admin-panel">
    <h2>Validaciones</h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Validador</th><th>Método</th><th>Total</th></tr></thead><tbody>
    <?php foreach ($validaciones as $v): ?><tr><td><?= e($v['nombre_completo']) ?></td><td><span class="badge badge-info"><?= e($v['metodo']) ?></span></td><td><strong><?= (int)$v['total'] ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
