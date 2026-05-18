<?php
declare(strict_types=1);

class Compra
{
    public const MINUTOS_COMPROBANTE = 10;

    public static function crear(array $data): int
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $pelicula = self::peliculaActivaParaVenta((int) $data['id_pelicula']);
            if (!$pelicula) {
                throw new RuntimeException('La pelicula seleccionada no existe.');
            }

            $reservadas = self::entradasReservadas((int) $pelicula['id']);
            $disponibles = max(0, (int) $pelicula['capacidad'] - $reservadas);

            if ((int) $data['cantidad'] > $disponibles) {
                throw new RuntimeException('No hay suficientes entradas disponibles.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO clientes (nombre_completo, correo, telefono)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$data['nombre'], $data['correo'], $data['telefono']]);
            $clienteId = (int) $pdo->lastInsertId();

            $codigo = self::codigoCompra();
            $monto = (float) $pelicula['precio_entrada'] * (int) $data['cantidad'];

            $stmt = $pdo->prepare(
                "INSERT INTO compras
                 (id_cliente, id_pelicula, id_usuario_vendedor, cantidad_entradas, precio_unitario, monto_total, codigo_compra)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $clienteId,
                (int) $pelicula['id'],
                $data['id_vendedor'] ?: null,
                (int) $data['cantidad'],
                $pelicula['precio_entrada'],
                $monto,
                $codigo,
            ]);

            $compraId = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $compraId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function detalle(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT c.*, cl.nombre_completo, cl.correo, cl.telefono, p.titulo, p.fecha_funcion, p.hora_funcion,
                    vendedor.nombre_completo AS vendedor_nombre,
                    vendedor.correo AS vendedor_correo,
                    vendedor.codigo_referencia AS vendedor_codigo,
                    vendedor.whatsapp AS vendedor_whatsapp,
                    vendedor.qr_pago_imagen AS vendedor_qr_pago_imagen
             FROM compras c
             JOIN clientes cl ON cl.id = c.id_cliente
             JOIN peliculas p ON p.id = c.id_pelicula
             LEFT JOIN usuarios vendedor ON vendedor.id = c.id_usuario_vendedor
             WHERE c.id = ? AND c.estado != 'eliminado'"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function buscarVendedorPorRef(?string $codigo): ?array
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $stmt = Database::getConnection()->prepare(
            "SELECT id, nombre_completo, correo, whatsapp, codigo_referencia, qr_pago_imagen
             FROM usuarios
             WHERE codigo_referencia = ? AND rol IN ('vendedor','admin') AND estado = 'activo'
             LIMIT 1"
        );
        $stmt->execute([$codigo]);
        return $stmt->fetch() ?: null;
    }

    public static function vendedorIdPorCodigo(?string $codigo): ?int
    {
        $vendedor = self::buscarVendedorPorRef($codigo);
        return $vendedor ? (int) $vendedor['id'] : null;
    }

    public static function tiempoRestanteSegundos(array $compra): int
    {
        if (!empty($compra['comprobante_nombre']) || $compra['estado'] !== 'activo') {
            return 0;
        }

        $limite = strtotime($compra['fecha_creacion'] . ' +' . self::MINUTOS_COMPROBANTE . ' minutes');
        return max(0, $limite - time());
    }

    public static function cancelarSiExpirada(int $id): bool
    {
        $compra = self::detalle($id);
        if (!$compra || $compra['estado'] !== 'activo' || $compra['estado_pago'] !== 'pendiente' || !empty($compra['comprobante_nombre'])) {
            return false;
        }

        if (self::tiempoRestanteSegundos($compra) > 0) {
            return false;
        }

        $stmt = Database::getConnection()->prepare(
            "UPDATE compras SET estado = 'anulado', motivo_rechazo = 'Tiempo agotado para subir comprobante' WHERE id = ?"
        );
        $stmt->execute([$id]);
        return true;
    }

    private static function peliculaActivaParaVenta(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT *
             FROM peliculas
             WHERE id = ? AND estado = 'activo'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    private static function entradasReservadas(int $peliculaId): int
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COALESCE(SUM(cantidad_entradas), 0)
             FROM compras
             WHERE id_pelicula = ?
               AND estado = 'activo'
               AND (
                    estado_pago = 'aprobado'
                    OR (
                        estado_pago = 'pendiente'
                        AND comprobante_nombre IS NOT NULL
                    )
               )"
        );
        $stmt->execute([$peliculaId]);

        return (int) $stmt->fetchColumn();
    }

    private static function codigoCompra(): string
    {
        return 'CINE-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    }
}
