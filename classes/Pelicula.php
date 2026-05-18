<?php
declare(strict_types=1);

class Pelicula
{
    private static function selectConDisponibles(): string
    {
        return "SELECT p.*, COALESCE(r.reservadas, 0) AS entradas_reservadas,
                       GREATEST(0, p.capacidad - COALESCE(r.reservadas, 0)) AS disponibles
                FROM peliculas p
                LEFT JOIN (
                    SELECT id_pelicula, SUM(cantidad_entradas) AS reservadas
                    FROM compras
                    WHERE estado = 'activo'
                      AND (
                        estado_pago = 'aprobado'
                        OR (
                        estado_pago = 'pendiente'
                            AND comprobante_nombre IS NOT NULL
                        )
                      )
                    GROUP BY id_pelicula
                ) r ON r.id_pelicula = p.id";
    }

    public static function activas(): array
    {
        $stmt = Database::getConnection()->query(
            self::selectConDisponibles() . "
             WHERE p.estado = 'activo'
             ORDER BY fecha_funcion, hora_funcion"
        );
        return $stmt->fetchAll();
    }

    public static function encontrar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            self::selectConDisponibles() . "
             WHERE p.id = ? AND p.estado != 'eliminado' LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function disponiblesParaVenta(): array
    {
        $stmt = Database::getConnection()->query(
            self::selectConDisponibles() . "
             WHERE p.estado = 'activo' AND GREATEST(0, p.capacidad - COALESCE(r.reservadas, 0)) > 0
             ORDER BY fecha_funcion, hora_funcion"
        );
        return $stmt->fetchAll();
    }
}
