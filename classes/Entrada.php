<?php
declare(strict_types=1);

class Entrada
{
    private const CODIGO_TICKET_PREFIJO = 'SOE-';
    private const CODIGO_TICKET_INICIO = 210601;
    private const CODIGO_TICKET_FIN = 210700;

    public static function generarParaCompra(array $compra): array
    {
        $pdo = Database::getConnection();
        self::prepararCodigosTicket();

        $existentes = $pdo->prepare("SELECT token_validacion FROM entradas WHERE id_compra = ? AND eliminado = 0 ORDER BY id");
        $existentes->execute([(int) $compra['id']]);
        $tokens = array_column($existentes->fetchAll(), 'token_validacion');

        if ($tokens) {
            self::asignarCodigosFaltantes((int) $compra['id']);
            $pdo->prepare("UPDATE entradas SET estado = 'activa' WHERE id_compra = ? AND eliminado = 0")->execute([(int) $compra['id']]);
            return $tokens;
        }

        for ($i = 1; $i <= (int) $compra['cantidad_entradas']; $i++) {
            $token = bin2hex(random_bytes(32));
            $codigoQr = rtrim(SITE_URL, '/') . '/validar/' . $token;
            $codigoTicket = self::siguienteCodigoTicket();
            $stmt = $pdo->prepare(
                "INSERT INTO entradas (id_compra, id_pelicula, codigo_ticket, codigo_qr, token_validacion, numero_entrada, estado)
                 VALUES (?, ?, ?, ?, ?, ?, 'activa')"
            );
            $stmt->execute([(int) $compra['id'], (int) $compra['id_pelicula'], $codigoTicket, $codigoQr, $token, $i]);
            $tokens[] = $token;
        }

        return $tokens;
    }

    public static function prepararCodigosTicket(): void
    {
        self::asegurarColumnaCodigoTicket();
        self::asignarCodigosFaltantesParaTodas();
    }

    public static function validarToken(string $token, int $validadorId, string $metodo = 'camara'): array
    {
        $pdo = Database::getConnection();
        $where = preg_match('/^SOE-\d{9}$/i', $token) ? 'e.codigo_ticket = ?' : 'e.token_validacion = ?';
        $stmt = $pdo->prepare(
            "SELECT e.*, p.titulo, p.fecha_funcion, p.hora_funcion, cl.nombre_completo, cl.correo, c.codigo_compra
             FROM entradas e
             JOIN peliculas p ON p.id = e.id_pelicula
             JOIN compras c ON c.id = e.id_compra
             JOIN clientes cl ON cl.id = c.id_cliente
             WHERE $where AND e.eliminado = 0
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $entrada = $stmt->fetch();

        if (!$entrada) {
            self::registrar(null, $validadorId, $token, $metodo, 'fallido', 'Entrada no encontrada.');
            return ['ok' => false, 'tipo' => 'invalida', 'mensaje' => 'INVÁLIDA'];
        }

        if ($entrada['estado'] === 'usada') {
            self::registrar((int) $entrada['id'], $validadorId, $token, $metodo, 'ya_usada', 'La entrada ya fue usada.');
            return ['ok' => false, 'tipo' => 'usada', 'mensaje' => 'YA USADA', 'entrada' => $entrada];
        }

        if ($entrada['estado'] !== 'activa') {
            self::registrar((int) $entrada['id'], $validadorId, $token, $metodo, 'fallido', 'Entrada no activa.');
            return ['ok' => false, 'tipo' => 'invalida', 'mensaje' => 'INVÁLIDA', 'entrada' => $entrada];
        }

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                "UPDATE entradas
                 SET estado = 'usada', validada_por = ?, tipo_validacion = ?, fecha_uso = NOW()
                 WHERE id = ? AND estado = 'activa'"
            );
            $update->execute([$validadorId, $metodo, (int) $entrada['id']]);
            self::registrar((int) $entrada['id'], $validadorId, $token, $metodo, 'exitoso', 'Entrada validada correctamente.');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return ['ok' => true, 'tipo' => 'valida', 'mensaje' => 'VÁLIDA', 'entrada' => $entrada];
    }

    public static function totalValidadas(?int $peliculaId = null): int
    {
        if ($peliculaId) {
            $stmt = Database::getConnection()->prepare(
                "SELECT COUNT(*)
                 FROM entradas
                 WHERE estado = 'usada' AND eliminado = 0 AND id_pelicula = ?"
            );
            $stmt->execute([$peliculaId]);

            return (int) $stmt->fetchColumn();
        }

        $stmt = Database::getConnection()->query(
            "SELECT COUNT(*)
             FROM entradas
             WHERE estado = 'usada'
               AND eliminado = 0
               AND id_pelicula = COALESCE(
                   (
                       SELECT id
                       FROM peliculas
                       WHERE estado = 'activo'
                       ORDER BY fecha_funcion, hora_funcion, id
                       LIMIT 1
                   ),
                   id_pelicula
               )"
        );

        return (int) $stmt->fetchColumn();
    }

    public static function peliculaContadorActual(): ?string
    {
        $stmt = Database::getConnection()->query(
            "SELECT titulo
             FROM peliculas
             WHERE estado = 'activo'
             ORDER BY fecha_funcion, hora_funcion, id
             LIMIT 1"
        );
        $titulo = $stmt->fetchColumn();

        return $titulo ? (string) $titulo : null;
    }

    private static function registrar(?int $entradaId, int $usuarioId, string $token, string $metodo, string $resultado, string $detalle): void
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO validacion_entradas
             (id_entrada, id_usuario, token_escaneado, metodo, resultado, detalle, ip_origen, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $entradaId,
            $usuarioId,
            $token,
            $metodo,
            $resultado,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    private static function siguienteCodigoTicket(): string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            "SELECT MAX(CAST(SUBSTRING(codigo_ticket, 5) AS UNSIGNED))
             FROM entradas
             WHERE codigo_ticket LIKE 'SOE-%'
             FOR UPDATE"
        );
        $ultimo = (int) $stmt->fetchColumn();
        $siguiente = max(self::CODIGO_TICKET_INICIO, $ultimo + 1);
        if ($siguiente > self::CODIGO_TICKET_FIN) {
            throw new RuntimeException('Se agotaron los codigos oficiales de tickets.');
        }

        return self::CODIGO_TICKET_PREFIJO . str_pad((string) $siguiente, 9, '0', STR_PAD_LEFT);
    }

    private static function asignarCodigosFaltantes(int $compraId): void
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT id FROM entradas
             WHERE id_compra = ? AND eliminado = 0 AND (codigo_ticket IS NULL OR codigo_ticket = '')
             ORDER BY id"
        );
        $stmt->execute([$compraId]);

        foreach ($stmt->fetchAll() as $entrada) {
            Database::getConnection()
                ->prepare("UPDATE entradas SET codigo_ticket = ? WHERE id = ?")
                ->execute([self::siguienteCodigoTicket(), (int) $entrada['id']]);
        }
    }

    private static function asegurarColumnaCodigoTicket(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SHOW COLUMNS FROM entradas LIKE 'codigo_ticket'");
        if ($stmt->fetch()) {
            return;
        }

        $pdo->exec("ALTER TABLE entradas ADD codigo_ticket CHAR(13) NULL AFTER id_pelicula");
        self::asignarCodigosFaltantesParaTodas();
        $pdo->exec("ALTER TABLE entradas MODIFY codigo_ticket CHAR(13) NOT NULL");
        $pdo->exec("ALTER TABLE entradas ADD UNIQUE KEY uq_entradas_codigo_ticket (codigo_ticket)");
    }

    private static function asignarCodigosFaltantesParaTodas(): void
    {
        $stmt = Database::getConnection()->query(
            "SELECT id FROM entradas
             WHERE codigo_ticket IS NULL OR codigo_ticket = ''
             ORDER BY id"
        );

        foreach ($stmt->fetchAll() as $entrada) {
            Database::getConnection()
                ->prepare("UPDATE entradas SET codigo_ticket = ? WHERE id = ?")
                ->execute([self::siguienteCodigoTicket(), (int) $entrada['id']]);
        }
    }
}

