<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';
require __DIR__ . '/../classes/Compra.php';
require __DIR__ . '/../classes/QRGenerator.php';
require __DIR__ . '/../classes/Mailer.php';
require __DIR__ . '/../classes/Entrada.php';
require __DIR__ . '/../classes/Notificacion.php';

Auth::requireRole(['admin', 'vendedor', 'validador']);

if (Auth::user()['rol'] === 'validador') {
    redirect('../validador/escaner.php');
}

function admin_is_admin(): bool { return Auth::user()['rol'] === 'admin'; }
function admin_is_vendor(): bool { return Auth::user()['rol'] === 'vendedor'; }
function admin_can_sell(): bool { return in_array(Auth::user()['rol'], ['admin', 'vendedor'], true); }

function admin_view_vendor_id(): ?int {
    if (admin_is_admin() && isset($_SESSION['view_vendor_id'])) return (int) $_SESSION['view_vendor_id'];
    return admin_is_vendor() ? Auth::id() : null;
}

function admin_view_vendor_label(): string {
    if (admin_is_vendor()) return Auth::user()['nombre'];
    if (!empty($_SESSION['view_vendor_name'])) return $_SESSION['view_vendor_name'];
    return 'Todos';
}

function admin_nav_active(string $page): string {
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $page ? 'active' : '';
}

function admin_header(string $title): void {
    $user = Auth::user();
    $initial = strtoupper(substr($user['nombre'] ?? 'U', 0, 1));
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> — Cine SOE Admin</title>
        <link href="<?= e(asset('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
        <link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body class="site-bg">
    <div class="admin-layout">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="brand-icon">SOE</span>
                <span>Cine Admin</span>
            </div>
            <nav class="sidebar-nav">
                <div class="sidebar-section">Principal</div>
                <a class="sidebar-link <?= admin_nav_active('index.php') ?>" href="index.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a class="sidebar-link <?= admin_nav_active('validar.php') ?>" href="validar.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
                    <span class="sidebar-label">Validar pagos</span>
                </a>
                <a class="sidebar-link <?= admin_nav_active('reportes.php') ?>" href="reportes.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                    <span class="sidebar-label">Reportes</span>
                </a>
                <?php if (admin_is_admin()): ?>
                <div class="sidebar-section">Administración</div>
                <a class="sidebar-link <?= admin_nav_active('peliculas.php') ?>" href="peliculas.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M7 2v20M17 2v20M2 12h20M2 7h5M2 17h5M17 17h5M17 7h5"/></svg>
                    <span class="sidebar-label">Películas</span>
                </a>
                <a class="sidebar-link <?= admin_nav_active('usuarios.php') ?>" href="usuarios.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="sidebar-label">Usuarios SOE</span>
                </a>
                <a class="sidebar-link <?= admin_nav_active('modo.php') ?>" href="modo.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="sidebar-label">Cambiar vista</span>
                </a>
                <?php endif; ?>
                <div class="sidebar-section">Accesos</div>
                <a class="sidebar-link" href="../index.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <span class="sidebar-label">Ir al inicio</span>
                </a>
                <a class="sidebar-link" href="../validador/escaner.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7z"/></svg>
                    <span class="sidebar-label">Escáner QR</span>
                </a>
            </nav>
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?= e($initial) ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= e($user['nombre'] ?? 'Usuario') ?></div>
                    <div class="sidebar-user-role"><?= e(ucfirst($user['rol'] ?? '')) ?></div>
                </div>
                <a href="../login.php?salir=1" title="Salir" style="margin-left:auto;color:var(--muted)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-area">
            <header class="admin-header">
                <div>
                    <button class="sidebar-toggle" type="button" aria-label="Menu">☰</button>
                    <h1><?= e($title) ?></h1>
                    <span class="text-muted text-sm">Vista: <?= e(admin_view_vendor_label()) ?></span>
                </div>
                <div class="flex gap-1">
                    <a class="btn btn-ghost btn-sm" href="perfil.php">Mi perfil</a>
                </div>
            </header>
            <?php foreach (consume_flash() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function admin_footer(): void {
    echo '</div></div>';
    echo '<script src="' . e(asset('assets/js/bootstrap.bundle.min.js')) . '"></script>';
    echo '<script src="' . e(asset('assets/js/main.js')) . '"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '</body></html>';
}
