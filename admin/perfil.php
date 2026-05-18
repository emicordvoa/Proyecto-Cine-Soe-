<?php
require __DIR__ . '/_bootstrap.php';
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([Auth::id()]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sesión expirada.');
        $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
        $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);
        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva = (string) ($_POST['nueva'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');
        if ($nombre === '' || !$correo) throw new RuntimeException('Nombre y correo obligatorios.');
        $params = [$nombre, $correo, $whatsapp, Auth::id()];
        $sql = "UPDATE usuarios SET nombre_completo = ?, correo = ?, whatsapp = ?";
        if ($nueva !== '' || $confirmar !== '' || $actual !== '') {
            if (strlen($nueva) < 6 || $nueva !== $confirmar) throw new RuntimeException('La nueva clave debe tener mínimo 6 caracteres y coincidir.');
            if (!password_verify($actual, (string) $usuario['password_hash'])) throw new RuntimeException('Clave actual incorrecta.');
            $sql .= ", password_hash = ?";
            $params = [$nombre, $correo, $whatsapp, password_hash($nueva, PASSWORD_BCRYPT), Auth::id()];
        }
        $sql .= " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['correo'] = $correo;
        $_SESSION['usuario']['whatsapp'] = $whatsapp;
        flash('success', 'Perfil actualizado.');
        redirect('perfil.php');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}
admin_header('Mi perfil');
?>
<div class="glass admin-panel">
    <h2>Información personal</h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-row form-row-2 mb-2">
            <div class="form-group"><label class="form-label">Nombre completo</label><input class="form-input" name="nombre_completo" value="<?= e($usuario['nombre_completo'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label">Correo</label><input class="form-input" type="email" name="correo" value="<?= e($usuario['correo'] ?? '') ?>" required></div>
        </div>
        <div class="form-group mb-3"><label class="form-label">WhatsApp</label><input class="form-input" name="whatsapp" value="<?= e($usuario['whatsapp'] ?? '') ?>" placeholder="59170000000"></div>

        <hr style="border-color:var(--line);margin:1.5rem 0">
        <h3 style="font-size:1.1rem;margin-bottom:1rem">Cambiar contraseña</h3>
        <div class="form-row form-row-3 mb-3">
            <div class="form-group"><label class="form-label">Clave actual</label><input class="form-input" type="password" name="actual"></div>
            <div class="form-group"><label class="form-label">Nueva clave</label><input class="form-input" type="password" name="nueva" minlength="6"></div>
            <div class="form-group"><label class="form-label">Confirmar</label><input class="form-input" type="password" name="confirmar" minlength="6"></div>
        </div>
        <button class="btn btn-primary btn-lg">Guardar perfil</button>
    </form>
</div>
<?php admin_footer(); ?>
