<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/Database.php';
require __DIR__ . '/classes/Pelicula.php';
require __DIR__ . '/classes/Compra.php';

if (isset($_GET['ref'])) {
    $ref = preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['ref']);
    if (Compra::buscarVendedorPorRef($ref)) {
        unset($_SESSION['vendedor_ref']);
        $_SESSION['vendedor_ref_prefill'] = $ref;
    }
    redirect('index.php');
}

$peliculas = Pelicula::activas();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cine SOE - Cartelera</title>
    <meta name="description" content="Compra tus entradas para el cine universitario SOE. Cartelera actualizada con las mejores películas.">
    <link href="<?= e(asset('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="site-bg">

<!-- Navbar -->
<nav class="navbar" id="mainNav">
    <a class="brand" href="index.php">
        <span class="brand-icon">SOE</span>
        Cine Universitario
    </a>
    <div class="nav-links">
        <a class="btn btn-ghost" href="#cartelera">Cartelera</a>
        <a class="btn btn-primary" href="login.php">Iniciar Sesión</a>
    </div>
</nav>

<!-- Hero Section with Parallax -->
<header class="hero">
    <div class="hero-bg" data-parallax-bg></div>
    <div class="hero-particles">
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
        <div class="particle"></div><div class="particle"></div><div class="particle"></div>
    </div>
    <div class="hero-content container">
        <p class="hero-eyebrow">Funciones Mayo 2026</p>
        <h1 class="hero-title">Una noche de cine<br>inolvidable</h1>
        <p class="hero-sub">Compra tus entradas en línea, sube tu comprobante y recibe tickets digitales con QR para el ingreso.</p>
        <div class="hero-cta">
            <a class="btn btn-primary btn-lg" href="#cartelera">Ver Cartelera</a>
        </div>
    </div>
    <div class="hero-scroll">
        <span></span>
        Scroll
    </div>
</header>

<!-- Cartelera -->
<main class="container section" id="cartelera">
    <?php foreach (consume_flash() as $message): ?>
        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>

    <div class="section-header reveal">
        <p class="section-eyebrow">Cartelera</p>
        <h2 class="section-title">Películas Disponibles</h2>
    </div>

    <div class="movies-grid">
        <?php foreach ($peliculas as $pelicula): ?>
            <?php
            $vendidas = (int) $pelicula['entradas_vendidas'];
            $capacidad = (int) $pelicula['capacidad'];
            $disponibles = max(0, $capacidad - $vendidas);
            $porcentaje = $capacidad > 0 ? min(100, ($vendidas / $capacidad) * 100) : 0;
            $poster = $pelicula['imagen'] ? 'assets/img/' . $pelicula['imagen'] : '';
            $fechaFmt = date('d/m/Y', strtotime($pelicula['fecha_funcion']));
            $horaFmt = substr($pelicula['hora_funcion'], 0, 5);
            ?>
            <article class="movie-card reveal"
                     data-movie-card
                     data-movie-id="<?= (int) $pelicula['id'] ?>"
                     data-movie-title="<?= e($pelicula['titulo']) ?>"
                     data-movie-desc="<?= e($pelicula['descripcion'] ?? 'Función especial de cine universitario.') ?>"
                     data-movie-price="<?= number_format((float) $pelicula['precio_entrada'], 2) ?>"
                     data-movie-date="<?= e($fechaFmt) ?> - <?= e($horaFmt) ?>"
                     data-movie-avail="<?= $disponibles ?>">
                <div class="movie-poster">
                    <?php if ($poster && file_exists(__DIR__ . '/' . $poster)): ?>
                        <img src="<?= e($poster) ?>" alt="<?= e($pelicula['titulo']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="poster-fallback"><?= e($pelicula['titulo']) ?></div>
                    <?php endif; ?>
                    <span class="movie-badge badge badge-success"><?= $disponibles ?> disp.</span>
                </div>
                <div class="movie-info">
                    <div class="movie-date-tag"><?= e($fechaFmt) ?> — <?= e($horaFmt) ?></div>
                    <h3><?= e($pelicula['titulo']) ?></h3>
                    <p><?= e($pelicula['descripcion'] ?? 'Función especial de cine universitario.') ?></p>
                    <div class="movie-meta">
                        <span class="movie-price">Bs <?= number_format((float) $pelicula['precio_entrada'], 2) ?></span>
                        <span class="movie-avail"><?= $disponibles ?>/<?= $capacidad ?></span>
                    </div>
                    <div class="movie-progress">
                        <div class="movie-progress-bar" style="width: <?= e((string) $porcentaje) ?>%"></div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<!-- Movie Modal -->
<div class="modal-overlay" id="movieModal">
    <div class="modal-content">
        <button class="modal-close" type="button">&times;</button>
        <div id="movieModalBody"></div>
    </div>
</div>

<footer class="site-footer">
    <div class="container">
        <span>Sociedad de Estudiantes SOE</span>
        <span>Contacto: cine@soe.edu.bo — WhatsApp +591 70000000</span>
    </div>
</footer>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
