<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');
$token = trim((string) ($_GET['token'] ?? ''));

if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Token invalido.']);
    exit;
}

$stmt = Database::getConnection()->prepare(
    "SELECT e.estado, e.fecha_uso, cl.nombre_completo AS cliente, p.titulo AS pelicula, p.fecha_funcion, p.hora_funcion
     FROM entradas e
     JOIN compras c ON c.id = e.id_compra
     JOIN clientes cl ON cl.id = c.id_cliente
     JOIN peliculas p ON p.id = e.id_pelicula
     WHERE e.token_validacion = ? AND e.eliminado = 0"
);
$stmt->execute([$token]);
$entrada = $stmt->fetch();
echo json_encode(['ok' => (bool) $entrada, 'entrada' => $entrada]);
