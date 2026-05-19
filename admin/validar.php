<?php
require_once __DIR__ . '/_bootstrap.php';
$pdo = Database::getConnection();

function mover_comprobante(string $archivo, string $destino): bool {
    // Validar que el nombre del archivo no esté vacío
    $nombre = basename($archivo);
    if (empty($nombre) || $nombre === '.' || $nombre === '..') {
        return false;
    }

    // Construir rutas seguras
    $origen = UPLOAD_PATH . '/comprobantes/pendientes/' . $nombre;
    $directorioDestino = UPLOAD_PATH . '/comprobantes/' . trim($destino, '/');
    $destino_final = $directorioDestino . '/' . $nombre;

    // Si el archivo ya está en destino, considerarlo como éxito
    if (@is_file($destino_final)) {
        return true;
    }

    // Si el archivo origen no existe, considerarlo "ya movido" o "no disponible"
    if (!@is_file($origen)) {
        return false;
    }

    // Verificar que el directorio destino exista, si no, crearlo con permisos seguros
    if (!@is_dir($directorioDestino)) {
        if (!@mkdir($directorioDestino, 0775, true)) {
            // Si falla, retornar false pero sin warning
            return false;
        }
    }

    // Intentar mover el archivo sin mostrar warnings
    if (!@rename($origen, $destino_final)) {
        // Si falla rename, intentar copy + unlink como fallback (a veces funciona en algunos servidores)
        if (@copy($origen, $destino_final)) {
            @unlink($origen);
            return true;
        }
        return false;
    }

    return true;
}

$filtroVendedorId = admin_view_vendor_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sesión expirada.');
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $accion = $_POST['accion'] ?? '';
        $compra = $id ? Compra::detalle($id) : null;
        if (!$compra) throw new RuntimeException('Compra no encontrada.');
        if ($filtroVendedorId && (int) $compra['id_usuario_vendedor'] !== $filtroVendedorId) throw new RuntimeException('Solo puedes validar compras de este Staff SOE.');

        if ($accion === 'aprobar') {
            if (empty($compra['comprobante_nombre'])) throw new RuntimeException('Sin comprobante.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE compras SET estado_pago='aprobado', id_usuario_validador=?, fecha_validacion=NOW() WHERE id=?")->execute([Auth::id(), $id]);
            $existian = $pdo->prepare("SELECT COUNT(*) FROM entradas WHERE id_compra=? AND eliminado=0");
            $existian->execute([$id]);
            $entradasAntes = (int) $existian->fetchColumn();
            $tokens = Entrada::generarParaCompra($compra);
            if ($entradasAntes === 0) {
                $pdo->prepare("UPDATE peliculas SET entradas_vendidas = entradas_vendidas + ? WHERE id = ? AND entradas_vendidas + ? <= capacidad")
                    ->execute([(int) $compra['cantidad_entradas'], (int) $compra['id_pelicula'], (int) $compra['cantidad_entradas']]);
            }
            $pdo->commit();
            mover_comprobante($compra['comprobante_nombre'], 'verificados');
            $_SESSION['wa_cliente_tickets'] = Notificacion::enviarWhatsAppCliente($compra['telefono'] ?? '', $tokens);
            $_SESSION['tickets_generados_compra'] = (int) $compra['id'];
            flash('success', 'Pago aprobado. Entradas generadas.');
            redirect('tickets.php?compra=' . (int) $compra['id'] . '&autoemail=1');
        } elseif ($accion === 'rechazar') {
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            $pdo->prepare("UPDATE compras SET estado_pago='rechazado', id_usuario_validador=?, fecha_validacion=NOW(), motivo_rechazo=? WHERE id=?")->execute([Auth::id(), $motivo, $id]);
            if ($compra['comprobante_nombre']) mover_comprobante($compra['comprobante_nombre'], 'rechazados');
            Mailer::enviar($compra['correo'], 'Pago rechazado - ' . $compra['codigo_compra'], 'Tu comprobante fue rechazado. Motivo: ' . e($motivo ?: 'No especificado'));
            flash('warning', 'Pago rechazado.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', $exception->getMessage());
    }
    redirect('validar.php');
}

$wherePend = "WHERE estado_pago='pendiente' AND comprobante_nombre IS NOT NULL";
$paramsPend = [];
if ($filtroVendedorId) { $wherePend .= ' AND id_usuario_vendedor = ?'; $paramsPend[] = $filtroVendedorId; }
$stmtPend = $pdo->prepare("SELECT * FROM v_compras_detalle $wherePend ORDER BY fecha_creacion DESC");
$stmtPend->execute($paramsPend);
$pendientes = $stmtPend->fetchAll();

$waCliente = $_SESSION['wa_cliente_tickets'] ?? null;
$ticketsCompra = $_SESSION['tickets_generados_compra'] ?? null;
unset($_SESSION['wa_cliente_tickets'], $_SESSION['tickets_generados_compra']);

$whereApr = "WHERE estado_pago='aprobado'"; $paramsApr = [];
if ($filtroVendedorId) { $whereApr .= ' AND id_usuario_vendedor = ?'; $paramsApr[] = $filtroVendedorId; }
$stmtApr = $pdo->prepare("SELECT * FROM v_compras_detalle $whereApr ORDER BY fecha_validacion DESC LIMIT 12");
$stmtApr->execute($paramsApr);
$aprobadas = $stmtApr->fetchAll();

admin_header(admin_is_vendor() ? 'Validar mis pagos' : 'Validar pagos');
?>

<div class="glass admin-panel">
    <h2>Comprobantes pendientes</h2>
    <?php if ($ticketsCompra): ?>
        <a class="btn btn-primary mb-3" href="tickets.php?compra=<?= (int) $ticketsCompra ?>">Ver entradas generadas / PDF</a>
    <?php endif; ?>
    <?php if ($waCliente): ?>
        <a class="btn btn-success mb-3" target="_blank" rel="noopener" href="<?= e($waCliente) ?>">Enviar tickets por WhatsApp</a>
    <?php endif; ?>

    <?php foreach ($pendientes as $compra): ?>
    <div class="payment-card">
        <div class="payment-card-header">
            <div>
                <strong style="font-size:1.05rem"><?= e($compra['cliente']) ?></strong>
                <p class="text-muted text-sm" style="margin:.2rem 0"><?= e($compra['pelicula']) ?> — <?= (int) $compra['cantidad_entradas'] ?> entrada(s)</p>
                <p class="text-muted text-sm">Bs <?= number_format((float) $compra['monto_total'], 2) ?> · <?= e($compra['vendedor'] ?? 'Online') ?> · <?= e($compra['fecha_creacion']) ?></p>
            </div>
            <form method="post" class="payment-card-actions flex-col gap-1">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $compra['id'] ?>">
                <input class="form-input" name="motivo" placeholder="Motivo rechazo" style="min-height:38px;font-size:.85rem">
                <div class="flex gap-1">
                    <button class="btn btn-primary btn-sm" name="accion" value="aprobar" style="flex:1">✓ Aprobar</button>
                    <button class="btn btn-danger btn-sm" name="accion" value="rechazar" style="flex:1">✗ Rechazar</button>
                </div>
            </form>
        </div>
        <?php
        $safeName = basename((string) ($compra['comprobante_nombre'] ?? ''));
        $receiptUrl = '';
        $fileExists = false;
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
        if ($safeName !== '') {
            $dirs = ['pendientes', 'verificados', 'rechazados'];
            foreach ($dirs as $d) {
                $path = UPLOAD_PATH . '/comprobantes/' . $d . '/' . $safeName;
                if (is_file($path)) {
                    $receiptUrl = '../uploads/comprobantes/' . $d . '/' . rawurlencode($safeName);
                    $fileExists = true;
                    break;
                }
            }
            // fallback: point to pendientes URL even if file missing (will be handled in modal)
            if ($receiptUrl === '') {
                $receiptUrl = '../uploads/comprobantes/pendientes/' . rawurlencode($safeName);
            }
        }
        ?>
        <div class="flex items-center gap-2 mt-2">
            <?php if ($isImg && $fileExists): ?>
                <img class="receipt-preview" src="<?= e($receiptUrl) ?>" alt="Comprobante">
            <?php elseif ($isImg && !$fileExists): ?>
                <div class="receipt-pdf">Imagen no disponible</div>
            <?php else: ?>
                <div class="receipt-pdf"><?php if ($fileExists) echo 'PDF'; else echo 'Comprobante no disponible'; ?></div>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" type="button" data-receipt-open data-receipt-url="<?= e($receiptUrl) ?>" data-receipt-kind="<?= $isImg?'image':'pdf' ?>" data-receipt-exists="<?= $fileExists ? '1' : '0' ?>">Ver completo</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($pendientes)): ?><p class="text-muted">No hay comprobantes pendientes.</p><?php endif; ?>
</div>

<div class="glass admin-panel">
    <h2>Compras aprobadas</h2>
    <div class="table-wrap">
        <table class="table"><thead><tr><th>Código</th><th>Cliente</th><th>Película</th><th>Entradas</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($aprobadas as $a): ?>
            <?php
            $tel = preg_replace('/\D+/', '', (string) ($a['telefono'] ?? ''));
            $msg = sprintf('Hola %s, soy %s. Te envío tus entradas Cine SOE para %s.', $a['cliente'], Auth::user()['nombre'], $a['pelicula']);
            $waUrl = $tel ? 'https://wa.me/' . $tel . '?text=' . urlencode($msg) : '';
            ?>
            <tr>
                <td><strong><?= e($a['codigo_compra']) ?></strong></td>
                <td><?= e($a['cliente']) ?></td>
                <td><?= e($a['pelicula']) ?></td>
                <td><?= (int) $a['cantidad_entradas'] ?></td>
                <td class="flex gap-1 flex-wrap">
                    <a class="btn btn-primary btn-sm" href="tickets.php?compra=<?= (int) $a['id'] ?>">Ver / PDF</a>
                    <?php if ($waUrl): ?><a class="btn btn-success btn-sm" target="_blank" href="<?= e($waUrl) ?>">WhatsApp</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<div class="receipt-modal" id="receiptModal" aria-hidden="true">
    <div class="receipt-modal-panel"><button class="receipt-modal-close" type="button" data-receipt-close>×</button><div id="receiptModalContent"></div></div>
</div>
<?php admin_footer(); ?>
