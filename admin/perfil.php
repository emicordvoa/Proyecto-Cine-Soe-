<?php
require __DIR__ . '/_bootstrap.php';

$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([Auth::id()]);
$usuario = $stmt->fetch();
$phoneCountries = [
    '591' => 'Bolivia +591','54' => 'Argentina +54','55' => 'Brasil +55',
    '56' => 'Chile +56','57' => 'Colombia +57','593' => 'Ecuador +593',
    '595' => 'Paraguay +595','51' => 'Peru +51','598' => 'Uruguay +598',
    '58' => 'Venezuela +58','52' => 'Mexico +52','1' => 'EE.UU./Canada +1','34' => 'España +34',
];

function separar_telefono_perfil(?string $telefono, array $paises): array {
    $digits = preg_replace('/\D+/', '', (string) $telefono);
    $codes = array_keys($paises);
    usort($codes, fn($a, $b) => strlen($b) <=> strlen($a));
    foreach ($codes as $code) {
        if (substr($digits, 0, strlen($code)) === $code && strlen($digits) > strlen($code)) {
            return [$code, substr($digits, strlen($code))];
        }
    }
    return ['591', $digits];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }

        $nombre = combine_name_parts((string) ($_POST['nombre'] ?? ''), (string) ($_POST['apellido'] ?? ''));
        $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);
        $telefonoPais = preg_replace('/\D+/', '', (string) ($_POST['telefono_pais'] ?? '591'));
        $telefonoLocal = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? ''));
        $whatsapp = $telefonoLocal === '' ? '' : $telefonoPais . $telefonoLocal;
        $actual = (string) ($_POST['actual'] ?? '');
        $nueva = (string) ($_POST['nueva'] ?? '');
        $confirmar = (string) ($_POST['confirmar'] ?? '');

        if (!is_full_name($nombre) || !$correo) {
            throw new RuntimeException('Nombre, apellido y correo son obligatorios.');
        }
        if ($telefonoLocal !== '' && (!isset($phoneCountries[$telefonoPais]) || strlen($telefonoLocal) < 5 || strlen($telefonoLocal) > 15)) {
            throw new RuntimeException('Teléfono inválido.');
        }

        $params = [$nombre, $correo, $whatsapp, Auth::id()];
        $sql = "UPDATE usuarios SET nombre_completo = ?, correo = ?, whatsapp = ?";

        if ($nueva !== '' || $confirmar !== '' || $actual !== '') {
            if (strlen($nueva) < 6 || $nueva !== $confirmar) {
                throw new RuntimeException('La nueva clave debe tener mínimo 6 caracteres y coincidir.');
            }

            if (!password_verify($actual, (string) $usuario['password_hash'])) {
                throw new RuntimeException('Clave actual incorrecta.');
            }

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

[$nombreActual, $apellidoActual] = split_name_parts($usuario['nombre_completo'] ?? '');
[$telefonoPaisActual, $telefonoLocalActual] = separar_telefono_perfil($usuario['whatsapp'] ?? '', $phoneCountries);
admin_header('Mi perfil');
?>
<div class="glass admin-panel">
    <h2>Información personal</h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-row form-row-2 mb-2">
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input class="form-input" name="nombre" value="<?= e($nombreActual) ?>" required minlength="2">
            </div>
            <div class="form-group">
                <label class="form-label">Apellido</label>
                <input class="form-input" name="apellido" value="<?= e($apellidoActual) ?>" required minlength="2">
            </div>
        </div>
        <div class="form-row form-row-2 mb-2">
            <div class="form-group">
                <label class="form-label">Correo</label>
                <input class="form-input" type="email" name="correo" value="<?= e($usuario['correo'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Teléfono</label>
            <div class="phone-field">
                <select class="form-input form-select" name="telefono_pais" data-country-select>
                    <?php foreach ($phoneCountries as $code => $label): ?>
                        <option value="<?= e($code) ?>" data-short="+<?= e($code) ?>" data-label="<?= e($label) ?>" <?= $code === $telefonoPaisActual ? 'selected' : '' ?>>+<?= e($code) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-input" type="tel" name="telefono" inputmode="numeric" maxlength="15" pattern="[0-9]{5,15}" data-phone-local="true" value="<?= e($telefonoLocalActual) ?>" placeholder="70000000">
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
