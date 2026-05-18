<?php
require __DIR__ . '/_bootstrap.php';
if (!admin_is_admin()) { http_response_code(403); exit('Solo administradores.'); }
$pdo = Database::getConnection();

if (isset($_GET['limpiar'])) {
    unset($_SESSION['view_vendor_id'], $_SESSION['view_vendor_name']);
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) { flash('danger', 'Sesión expirada.'); redirect('modo.php'); }
    $vendorId = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);
    if (!$vendorId) { unset($_SESSION['view_vendor_id'], $_SESSION['view_vendor_name']); redirect('index.php'); }
    $stmt = $pdo->prepare("SELECT id, nombre_completo FROM usuarios WHERE id=? AND rol IN ('admin','vendedor') AND estado='activo'");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
    if ($vendor) {
        $_SESSION['view_vendor_id'] = (int) $vendor['id'];
        $_SESSION['view_vendor_name'] = 'Vendedor: ' . $vendor['nombre_completo'];
        redirect('index.php');
    }
    flash('danger', 'Vendedor no encontrado.');
    redirect('modo.php');
}

$vendedores = $pdo->query("SELECT id, nombre_completo, codigo_referencia, rol FROM usuarios WHERE rol IN ('admin','vendedor') AND estado='activo' ORDER BY rol,nombre_completo")->fetchAll();
admin_header('Cambiar vista');
?>
<div class="glass admin-panel">
    <p class="text-muted mb-3">Como admin puedes ver todo o filtrar como un vendedor específico, sin cerrar sesión.</p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group mb-3">
            <label class="form-label">Ver como vendedor</label>
            <select class="form-input form-select" name="vendor_id">
                <option value="">Ver todo como admin</option>
                <?php foreach ($vendedores as $v): ?>
                    <option value="<?= (int) $v['id'] ?>"><?= e($v['nombre_completo']) ?> (<?= e($v['rol']) ?>) — <?= e($v['codigo_referencia']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary btn-lg">Aplicar vista</button>
            <a class="btn btn-ghost btn-lg" href="modo.php?limpiar=1">Ver todo</a>
        </div>
    </form>
</div>
<?php admin_footer(); ?>
