<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';
require __DIR__ . '/../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!Auth::check() || !in_array(Auth::user()['rol'], ['admin', 'vendedor'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Database::getConnection();
$vendorJoin = Auth::user()['rol'] === 'vendedor' ? ' AND c.id_usuario_vendedor = ' . (int) Auth::id() : '';
$ventas = $pdo->query(
    "SELECT p.titulo, COALESCE(SUM(CASE WHEN c.estado_pago='aprobado' THEN c.cantidad_entradas ELSE 0 END),0) AS entradas
     FROM peliculas p LEFT JOIN compras c ON c.id_pelicula=p.id AND c.estado='activo' $vendorJoin
     WHERE p.estado!='eliminado'
     GROUP BY p.id ORDER BY p.fecha_funcion"
)->fetchAll();

echo json_encode(['ok' => true, 'ventas' => $ventas]);
