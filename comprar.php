<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/Database.php';
require __DIR__ . '/classes/Pelicula.php';
require __DIR__ . '/classes/Compra.php';
require __DIR__ . '/classes/Notificacion.php';

$phoneCountries = [
    '591' => 'Bolivia +591','54' => 'Argentina +54','55' => 'Brasil +55',
    '56' => 'Chile +56','57' => 'Colombia +57','593' => 'Ecuador +593',
    '595' => 'Paraguay +595','51' => 'Peru +51','598' => 'Uruguay +598',
    '58' => 'Venezuela +58','52' => 'Mexico +52','1' => 'EE.UU./Canada +1','34' => 'España +34',
];
$peliculas = Pelicula::disponiblesParaVenta();
$peliculaId = filter_input(INPUT_GET, 'pelicula', FILTER_VALIDATE_INT) ?: null;
$seleccionada = $peliculaId ? Pelicula::encontrar($peliculaId) : ($peliculas[0] ?? null);
$codigoSugerido = (string) ($_SESSION['vendedor_ref_prefill'] ?? '');
unset($_SESSION['vendedor_ref'], $_SESSION['vendedor_ref_prefill']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Sesión expirada. Intenta nuevamente.');
        }
        $idPelicula = filter_input(INPUT_POST, 'id_pelicula', FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $correo = filter_var($_POST['correo'] ?? '', FILTER_VALIDATE_EMAIL);
        $telefonoPais = preg_replace('/\D+/', '', (string) ($_POST['telefono_pais'] ?? '591'));
        $telefonoLocal = preg_replace('/\D+/', '', (string) ($_POST['telefono'] ?? ''));
        $telefono = $telefonoPais . $telefonoLocal;
        $codigoVendedor = trim((string) ($_POST['codigo_vendedor'] ?? ''));

        if (!$idPelicula || !$cantidad || $nombre === '' || !$correo || !isset($phoneCountries[$telefonoPais]) || strlen($telefonoLocal) < 5 || strlen($telefonoLocal) > 15) {
            throw new RuntimeException('Completa todos los datos con formatos válidos.');
        }
        $vendedor = null;
        if ($codigoVendedor !== '') {
            $vendedor = Compra::buscarVendedorPorRef($codigoVendedor);
            if (!$vendedor) throw new RuntimeException('El código de vendedor no existe.');
        }
        $compraId = Compra::crear([
            'id_pelicula' => $idPelicula,'cantidad' => $cantidad,'nombre' => $nombre,
            'correo' => $correo,'telefono' => $telefono,'id_vendedor' => $vendedor['id'] ?? null,
        ]);
        $compra = Compra::detalle($compraId);
        if ($vendedor && $compra) {
            $_SESSION['wa_vendedor_compra_' . $compraId] = Notificacion::enviarWhatsAppVendedor(
                $vendedor['whatsapp'] ?? '',$compra['nombre_completo'],$compra['titulo'],
                (int) $compra['cantidad_entradas'],number_format((float) $compra['monto_total'], 2),$compra['codigo_compra']
            );
        }
        redirect('comprobante.php?compra=' . $compraId);
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprar entradas — Cine SOE</title>
    <link href="<?= e(asset('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="site-bg">
<nav class="navbar" id="mainNav">
    <a class="brand" href="index.php"><span class="brand-icon">SOE</span> Cine Universitario</a>
    <div class="nav-links"><a class="btn btn-ghost" href="index.php">← Cartelera</a></div>
</nav>

<main class="checkout-page">
<div class="container">
    <a href="index.php" class="checkout-back">← Volver a cartelera</a>

    <div class="glass checkout-panel">
        <p class="section-eyebrow">Compra segura</p>
        <h1>Reserva tus entradas</h1>
        <p class="text-muted text-sm mb-3">Tienes 10 minutos para subir el comprobante después de reservar.</p>

        <?php foreach (consume_flash() as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>

        <!-- Stepper -->
        <ol class="stepper">
            <li class="stepper-item active"><span class="stepper-num">1</span><span class="stepper-label">Película</span></li>
            <div class="stepper-line"></div>
            <li class="stepper-item"><span class="stepper-num">2</span><span class="stepper-label">Datos</span></li>
            <div class="stepper-line"></div>
            <li class="stepper-item"><span class="stepper-num">3</span><span class="stepper-label">Confirmar</span></li>
        </ol>

        <form method="post" data-stepper-form>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <!-- Step 1: Película y cantidad -->
            <div class="step-panel active">
                <div class="form-row form-row-2 mb-3">
                    <div class="form-group">
                        <label class="form-label">Película</label>
                        <select class="form-input form-select" name="id_pelicula" id="movieSelect" required>
                            <?php foreach ($peliculas as $pelicula): ?>
                                <option value="<?= (int) $pelicula['id'] ?>"
                                        data-price="<?= e($pelicula['precio_entrada']) ?>"
                                        data-available="<?= (int) $pelicula['disponibles'] ?>"
                                        <?= $seleccionada && (int) $seleccionada['id'] === (int) $pelicula['id'] ? 'selected' : '' ?>>
                                    <?= e($pelicula['titulo']) ?> — <?= e(date('d/m', strtotime($pelicula['fecha_funcion']))) ?> <?= e(substr($pelicula['hora_funcion'], 0, 5)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cantidad</label>
                        <div class="qty-control">
                            <button type="button" class="qty-btn" data-qty="-1">−</button>
                            <input class="form-input" style="text-align:center" id="quantity" type="text" name="cantidad" value="1" min="1" max="10" inputmode="numeric" pattern="[0-9]+" data-digits-only="true" required>
                            <button type="button" class="qty-btn" data-qty="1">+</button>
                        </div>
                        <small class="text-muted text-sm mt-1" id="availableText" style="display:block"></small>
                    </div>
                </div>
                <div class="total-box mb-3">
                    <span>Total:</span>
                    <span class="total-amount" id="totalPrice">Bs 0.00</span>
                </div>
                <button type="button" class="btn btn-primary btn-lg btn-block" data-step-next>Siguiente →</button>
            </div>

            <!-- Step 2: Datos personales -->
            <div class="step-panel">
                <div class="form-row form-row-2 mb-3">
                    <div class="form-group">
                        <label class="form-label">Nombre completo</label>
                        <input class="form-input" name="nombre" required minlength="3" placeholder="Tu nombre completo">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo electrónico</label>
                        <input class="form-input" type="email" name="correo" required placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div class="form-row form-row-2 mb-3">
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <div class="phone-field">
                            <select class="form-input form-select" name="telefono_pais" data-country-select>
                                <?php foreach ($phoneCountries as $code => $label): ?>
                                    <option value="<?= e($code) ?>" data-short="+<?= e($code) ?>" data-label="<?= e($label) ?>" <?= $code === '591' ? 'selected' : '' ?>>+<?= e($code) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="form-input" type="tel" name="telefono" required inputmode="numeric" maxlength="15" pattern="[0-9]{5,15}" data-phone-local="true" placeholder="70000000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Código vendedor (opcional)</label>
                        <input class="form-input" name="codigo_vendedor" value="<?= e($codigoSugerido) ?>" placeholder="Ej: A7F3E8D9">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-lg" data-step-prev>← Atrás</button>
                    <button type="button" class="btn btn-primary btn-lg" style="flex:1" data-step-next>Siguiente →</button>
                </div>
            </div>

            <!-- Step 3: Confirmación -->
            <div class="step-panel">
                <div class="glass p-3 mb-3" style="background:rgba(53,215,210,.06)">
                    <h3 style="font-size:1.1rem;margin-bottom:.8rem">Resumen de tu compra</h3>
                    <p class="text-muted text-sm">Verifica tus datos y presiona "Confirmar" para continuar al pago.</p>
                </div>
                <div class="total-box mb-3">
                    <span>Total a pagar:</span>
                    <span class="total-amount" id="totalPrice2">Bs 0.00</span>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-lg" data-step-prev>← Atrás</button>
                    <button class="btn btn-primary btn-lg" style="flex:1" type="submit">✓ Confirmar Compra</button>
                </div>
            </div>
        </form>
    </div>
</div>
</main>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
// Sync total to step 3
const tp=document.getElementById('totalPrice'),tp2=document.getElementById('totalPrice2');
if(tp&&tp2){new MutationObserver(()=>tp2.textContent=tp.textContent).observe(tp,{childList:true,characterData:true,subtree:true});}
</script>
</body>
</html>
