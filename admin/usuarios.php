<?php
require __DIR__ . '/_bootstrap.php';
if (!admin_is_admin()) { http_response_code(403); exit('Solo administradores.'); }
$pdo = Database::getConnection();
$phoneCountries = ['591'=>'Bolivia +591','54'=>'Argentina +54','55'=>'Brasil +55','56'=>'Chile +56','57'=>'Colombia +57','593'=>'Ecuador +593','595'=>'Paraguay +595','51'=>'Peru +51','598'=>'Uruguay +598','58'=>'Venezuela +58','52'=>'Mexico +52','1'=>'EE.UU./Canada +1','34'=>'España +34'];

function codigo_ref(string $rol): string { return strtoupper(substr(bin2hex(random_bytes(6)), 0, 12)); }
function separar_telefono(?string $telefono, array $paises): array {
    $digits = preg_replace('/\D+/', '', (string) $telefono);
    $codes = array_keys($paises); usort($codes, fn($a,$b)=>strlen($b)<=>strlen($a));
    foreach ($codes as $code) { if (substr($digits,0,strlen($code))===$code && strlen($digits)>strlen($code)) return [$code, substr($digits,strlen($code))]; }
    return ['591', $digits];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('Sesión expirada.');
        $accion = $_POST['accion'] ?? 'guardar'; $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($accion === 'eliminar' && $id) { $pdo->prepare("UPDATE usuarios SET estado='eliminado' WHERE id=?")->execute([$id]); flash('success','Usuario eliminado.'); redirect('usuarios.php'); }
        $nombre = trim((string)($_POST['nombre_completo']??'')); $correo = filter_var($_POST['correo']??'', FILTER_VALIDATE_EMAIL);
        $rol = in_array($_POST['rol']??'', ['admin','vendedor','validador'],true)?$_POST['rol']:'vendedor';
        $waPais = preg_replace('/\D+/','',(string)($_POST['whatsapp_pais']??'591'));
        $waLocal = preg_replace('/\D+/','',(string)($_POST['whatsapp']??''));
        $whatsapp = $waLocal===''?'':$waPais.$waLocal;
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['codigo_referencia']??'')));
        $estado = in_array($_POST['estado']??'activo',['activo','inactivo'],true)?$_POST['estado']:'activo';
        $password = (string)($_POST['password']??'');
        if ($nombre===''||!$correo) throw new RuntimeException('Nombre y correo obligatorios.');
        if ($id && (strlen($codigo)<6||strlen($codigo)>12)) throw new RuntimeException('Código: 6-12 caracteres.');
        if (!$id && strlen($password)<6) throw new RuntimeException('Contraseña mínimo 6 caracteres.');
        if ($waLocal!=='' && (!isset($phoneCountries[$waPais])||strlen($waLocal)<5||strlen($waLocal)>15)) throw new RuntimeException('WhatsApp inválido.');
        if ($id) {
            if ($codigo==='') $codigo=codigo_ref($rol);
            $params=[$nombre,$correo,$rol,$codigo,$whatsapp,$estado,$id]; $sql="UPDATE usuarios SET nombre_completo=?,correo=?,rol=?,codigo_referencia=?,whatsapp=?,estado=?";
            if ($password!==''){$sql.=",password_hash=?";$params=[$nombre,$correo,$rol,$codigo,$whatsapp,$estado,password_hash($password,PASSWORD_BCRYPT),$id];}
            $sql.=" WHERE id=?"; $pdo->prepare($sql)->execute($params); flash('success','Usuario actualizado.');
        } else {
            $codigo=codigo_ref($rol);
            $pdo->prepare("INSERT INTO usuarios (nombre_completo,correo,password_hash,rol,codigo_referencia,whatsapp,estado) VALUES (?,?,?,?,?,?,?)")->execute([$nombre,$correo,password_hash($password,PASSWORD_BCRYPT),$rol,$codigo,$whatsapp,$estado]);
            flash('success','Usuario creado. Código: '.$codigo);
        }
    } catch (Throwable $e) { flash('danger',$e->getMessage()); }
    redirect('usuarios.php');
}

$editarId = filter_input(INPUT_GET,'editar',FILTER_VALIDATE_INT); $editar=null;
if ($editarId){$stmt=$pdo->prepare("SELECT * FROM usuarios WHERE id=? AND estado!='eliminado'");$stmt->execute([$editarId]);$editar=$stmt->fetch();}
[$waPaisActual,$waLocalActual]=separar_telefono($editar['whatsapp']??'',$phoneCountries);
$usuarios=$pdo->query("SELECT * FROM usuarios WHERE estado!='eliminado' ORDER BY rol,nombre_completo")->fetchAll();
admin_header('Usuarios SOE');
?>
<div class="glass admin-panel">
    <h2><?= $editar?'Editar usuario':'Crear usuario' ?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)($editar['id']??0) ?>">
        <div class="form-row form-row-2 mb-2">
            <div class="form-group"><label class="form-label">Nombre completo</label><input class="form-input" name="nombre_completo" required minlength="3" value="<?= e($editar['nombre_completo']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Correo</label><input class="form-input" type="email" name="correo" required value="<?= e($editar['correo']??'') ?>"></div>
        </div>
        <div class="form-row form-row-3 mb-2">
            <div class="form-group"><label class="form-label">Rol</label><select class="form-input form-select" name="rol"><option value="vendedor">Vendedor</option><option value="validador" <?=($editar['rol']??'')==='validador'?'selected':''?>>Validador</option><option value="admin" <?=($editar['rol']??'')==='admin'?'selected':''?>>Admin</option></select></div>
            <div class="form-group"><label class="form-label">Código ref.</label><input class="form-input" name="codigo_referencia" value="<?= e($editar['codigo_referencia']??'') ?>" placeholder="Auto" <?= $editar?'':'readonly' ?>></div>
            <div class="form-group"><label class="form-label">WhatsApp</label>
                <div class="phone-field">
                    <select class="form-input form-select" name="whatsapp_pais" data-country-select>
                        <?php foreach ($phoneCountries as $code=>$label): ?><option value="<?= e($code) ?>" data-short="+<?= e($code) ?>" data-label="<?= e($label) ?>" <?= $code===$waPaisActual?'selected':'' ?>>+<?= e($code) ?></option><?php endforeach; ?>
                    </select>
                    <input class="form-input" type="tel" name="whatsapp" value="<?= e($waLocalActual) ?>" placeholder="70000000" maxlength="15" data-phone-local="true">
                </div>
            </div>
        </div>
        <div class="form-row form-row-2 mb-3">
            <div class="form-group"><label class="form-label">Password <?= $editar?'(vacío = sin cambio)':'' ?></label><input class="form-input" type="password" name="password" minlength="6" <?= $editar?'':'required' ?>></div>
            <div class="form-group"><label class="form-label">Estado</label><select class="form-input form-select" name="estado"><option value="activo">Activo</option><option value="inactivo" <?=($editar['estado']??'')==='inactivo'?'selected':''?>>Inactivo</option></select></div>
        </div>
        <button class="btn btn-primary btn-lg">Guardar usuario</button>
    </form>
</div>
<div class="glass admin-panel">
    <h2>Códigos y links</h2>
    <div class="table-wrap">
        <table class="table"><thead><tr><th>Nombre</th><th>Rol</th><th>Código</th><th>WhatsApp</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><strong><?= e($u['nombre_completo']) ?></strong><br><span class="text-muted text-sm"><?= e($u['correo']) ?></span></td>
                <td><span class="badge badge-<?= $u['rol']==='admin'?'purple':($u['rol']==='vendedor'?'info':'warning') ?>"><?= e($u['rol']) ?></span></td>
                <td><code><?= e($u['codigo_referencia']) ?></code></td>
                <td class="text-sm"><?= e($u['whatsapp']??'') ?></td>
                <td class="flex gap-1">
                    <a class="btn btn-ghost btn-sm" href="usuarios.php?editar=<?= (int)$u['id'] ?>">Editar</a>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-danger btn-sm" name="accion" value="eliminar">Eliminar</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php admin_footer(); ?>
