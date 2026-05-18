<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/FileUploader.php';

if (!admin_is_admin()) {
    http_response_code(403);
    exit('Acceso solo para administradores.');
}

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada.');
        }

        $accion = $_POST['accion'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($accion === 'eliminar' && $id) {
            $pdo->prepare("UPDATE peliculas SET estado='eliminado' WHERE id=?")->execute([$id]);
            flash('success', 'Película eliminada.');
            redirect('peliculas.php');
        }

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $fecha = $_POST['fecha_funcion'] ?? '';
        $hora = $_POST['hora_funcion'] ?? '';
        $precio = filter_input(INPUT_POST, 'precio_entrada', FILTER_VALIDATE_FLOAT);
        $imagen = trim((string) ($_POST['imagen_actual'] ?? ''));
        $estado = in_array($_POST['estado'] ?? 'activo', ['activo', 'inactivo'], true) ? $_POST['estado'] : 'activo';

        if ($titulo === '' || !$fecha || !$hora || !$precio || $precio <= 0) {
            throw new RuntimeException('Completa título, fecha, hora y precio.');
        }

        if (($_FILES['imagen_archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagen = FileUploader::imagenPelicula($_FILES['imagen_archivo'], $titulo);
        }

        if ($estado === 'activo') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM peliculas WHERE estado='activo' AND fecha_funcion=? AND id<>?");
            $stmt->execute([$fecha, $id ?: 0]);
            if ((int) $stmt->fetchColumn() >= 2) {
                throw new RuntimeException('Solo 2 películas activas por día.');
            }
        }

        if ($id) {
            $pdo->prepare(
                "UPDATE peliculas SET titulo=?, descripcion=?, fecha_funcion=?, hora_funcion=?, precio_entrada=?, imagen=?, estado=? WHERE id=?"
            )->execute([$titulo, $descripcion, $fecha, $hora, $precio, $imagen, $estado, $id]);
            flash('success', 'Película actualizada.');
        } else {
            $pdo->prepare(
                "INSERT INTO peliculas (titulo, descripcion, fecha_funcion, hora_funcion, precio_entrada, imagen, capacidad, estado) VALUES (?,?,?,?,?,?,100,?)"
            )->execute([$titulo, $descripcion, $fecha, $hora, $precio, $imagen, $estado]);
            flash('success', 'Película creada.');
        }
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }

    redirect('peliculas.php');
}

$editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$editar = null;
if ($editarId) {
    $stmt = $pdo->prepare("SELECT * FROM peliculas WHERE id=? AND estado!='eliminado'");
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch();
}

$peliculas = $pdo->query("SELECT * FROM peliculas WHERE estado!='eliminado' ORDER BY fecha_funcion,hora_funcion")->fetchAll();
admin_header('Películas');
?>
<div class="glass admin-panel">
    <h2><?= $editar ? 'Editar película' : 'Nueva película' ?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">
        <input type="hidden" name="accion" value="guardar">
        <div class="form-row form-row-2 mb-2">
            <div class="form-group">
                <label class="form-label">Título</label>
                <input class="form-input" name="titulo" required minlength="2" value="<?= e($editar['titulo'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Imagen de cartelera</label>
                <input type="hidden" name="imagen_actual" value="<?= e($editar['imagen'] ?? '') ?>">
                <input class="form-input" type="file" name="imagen_archivo" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                <small class="text-muted text-sm">JPG o PNG hasta 5MB. Se guardará en assets/img.</small>
                <?php if (!empty($editar['imagen']) && is_file(ROOT_PATH . '/assets/img/' . $editar['imagen'])): ?>
                    <img class="poster-upload-preview" src="../assets/img/<?= e(rawurlencode($editar['imagen'])) ?>" alt="Imagen actual">
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group mb-2">
            <label class="form-label">Descripción</label>
            <input class="form-input" name="descripcion" value="<?= e($editar['descripcion'] ?? '') ?>">
        </div>
        <div class="form-row form-row-3 mb-2">
            <div class="form-group">
                <label class="form-label">Fecha</label>
                <input class="form-input" type="date" name="fecha_funcion" required value="<?= e($editar['fecha_funcion'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Hora</label>
                <input class="form-input" type="time" name="hora_funcion" required value="<?= e(isset($editar['hora_funcion']) ? substr($editar['hora_funcion'], 0, 5) : '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Precio</label>
                <input class="form-input" type="text" inputmode="decimal" data-decimal-only="true" name="precio_entrada" required value="<?= e((string) ($editar['precio_entrada'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-group mb-3">
                <label class="form-label">Estado</label>
                <select class="form-input form-select" name="estado">
                    <option value="activo">Activo</option>
                    <option value="inactivo" <?= ($editar['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
        </div>
        <button class="btn btn-primary btn-lg">Guardar</button>
    </form>
</div>
<div class="glass admin-panel">
    <h2>Listado</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Título</th><th>Función</th><th>Precio</th><th>Vendidas</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($peliculas as $p): ?>
                <tr>
                    <td><strong><?= e($p['titulo']) ?></strong></td>
                    <td><?= e($p['fecha_funcion']) ?> <?= e(substr($p['hora_funcion'], 0, 5)) ?></td>
                    <td>Bs <?= number_format((float) $p['precio_entrada'], 2) ?></td>
                    <td><?= (int) $p['entradas_vendidas'] ?>/<?= (int) $p['capacidad'] ?></td>
                    <td><span class="badge badge-<?= $p['estado'] === 'activo' ? 'success' : 'warning' ?>"><?= e($p['estado']) ?></span></td>
                    <td class="flex gap-1">
                        <a class="btn btn-ghost btn-sm" href="peliculas.php?editar=<?= (int) $p['id'] ?>">Editar</a>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-danger btn-sm" name="accion" value="eliminar">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php admin_footer(); ?>
