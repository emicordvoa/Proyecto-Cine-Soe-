<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/FileUploader.php';

function borrar_qr_pago_anterior(?string $archivo): void
{
    $archivo = basename((string) $archivo);
    if ($archivo === '') {
        return;
    }

    $ruta = UPLOAD_PATH . '/qr-pagos/' . $archivo;
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([Auth::id()]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }

        $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
        $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);
        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva = (string) ($_POST['nueva'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');
        $qrPago = $usuario['qr_pago_imagen'] ?? null;

        if ($nombre === '' || !$correo) {
            throw new RuntimeException('Nombre y correo obligatorios.');
        }

        if (isset($_POST['quitar_qr_pago'])) {
            borrar_qr_pago_anterior($qrPago);
            $qrPago = null;
        }

        if (($_FILES['qr_pago']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $nuevoQr = FileUploader::qrPago($_FILES['qr_pago'], (int) Auth::id());
            borrar_qr_pago_anterior($qrPago);
            $qrPago = $nuevoQr;
        }

        $params = [$nombre, $correo, $whatsapp, $qrPago, Auth::id()];
        $sql = "UPDATE usuarios SET nombre_completo = ?, correo = ?, whatsapp = ?, qr_pago_imagen = ?";

        if ($nueva !== '' || $confirmar !== '' || $actual !== '') {
            if (strlen($nueva) < 6 || $nueva !== $confirmar) {
                throw new RuntimeException('La nueva clave debe tener mínimo 6 caracteres y coincidir.');
            }

            if (!password_verify($actual, (string) $usuario['password_hash'])) {
                throw new RuntimeException('Clave actual incorrecta.');
            }

            $sql .= ", password_hash = ?";
            $params = [$nombre, $correo, $whatsapp, $qrPago, password_hash($nueva, PASSWORD_BCRYPT), Auth::id()];
        }

        $sql .= " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['correo'] = $correo;
        $_SESSION['usuario']['whatsapp'] = $whatsapp;
        $_SESSION['usuario']['qr_pago_imagen'] = $qrPago;

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
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-row form-row-2 mb-2">
            <div class="form-group">
                <label class="form-label">Nombre completo</label>
                <input class="form-input" name="nombre_completo" value="<?= e($usuario['nombre_completo'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" name="correo" value="<?= e($usuario['correo'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">WhatsApp</label>
            <input class="form-input" name="whatsapp" value="<?= e($usuario['whatsapp'] ?? '') ?>" placeholder="59170000000">
        </div>

        <hr style="border-color:var(--line);margin:1.5rem 0">
        <h3 style="font-size:1.1rem;margin-bottom:.5rem">QR de pago del vendedor</h3>
        <p class="text-white-50">Si una compra entra con tu enlace o código de vendedor, se mostrará este QR para pagar.</p>
        <div class="form-row form-row-2 mb-3">
            <div class="form-group">
                <label class="form-label">Subir o reemplazar QR</label>
                <input class="form-input" type="file" name="qr_pago" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                <small class="text-white-50">Solo JPG o PNG hasta 5MB. Se guarda sin metadatos.</small>
            </div>
            <div class="form-group">
                <label class="form-label">QR actual</label>
                <?php if (!empty($usuario['qr_pago_imagen']) && is_file(UPLOAD_PATH . '/qr-pagos/' . $usuario['qr_pago_imagen'])): ?>
                    <img class="vendor-qr-preview" src="../uploads/qr-pagos/<?= e(rawurlencode($usuario['qr_pago_imagen'])) ?>" alt="QR de pago actual">
                    <button class="btn btn-danger btn-sm mt-2" type="submit" name="quitar_qr_pago" value="1">Quitar QR</button>
                <?php else: ?>
                    <div class="empty-state">Sin QR propio. Se usará el QR general de SOE.</div>
                <?php endif; ?>
            </div>
        </div>

        <hr style="border-color:var(--line);margin:1.5rem 0">
        <h3 style="font-size:1.1rem;margin-bottom:1rem">Cambiar contraseña</h3>
        <div class="form-row form-row-3 mb-3">
            <div class="form-group">
                <label class="form-label">Clave actual</label>
                <input class="form-input" type="password" name="actual">
            </div>
            <div class="form-group">
                <label class="form-label">Nueva clave</label>
                <input class="form-input" type="password" name="nueva" minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">Confirmar</label>
                <input class="form-input" type="password" name="confirmar" minlength="6">
            </div>
        </div>
        <button class="btn btn-primary btn-lg">Guardar perfil</button>
    </form>
</div>
<?php admin_footer(); ?>
