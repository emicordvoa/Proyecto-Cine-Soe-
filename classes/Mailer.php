<?php
declare(strict_types=1);

class Mailer
{
    public static function enviar(string $to, string $subject, string $html, array $attachments = []): bool
    {
        $autoload = ROOT_PATH . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        $smtp = self::smtpConfig();
        $from = $smtp['smtp_usuario'] ?: 'emlvpel@gmail.com';
        $attachments = self::normalizarAdjuntos($attachments);

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            if (!empty($smtp['smtp_host']) && !empty($smtp['smtp_usuario']) && !empty($smtp['smtp_password'])) {
                $mail->isSMTP();
                $mail->Host = $smtp['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['smtp_usuario'];
                $mail->Password = $smtp['smtp_password'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int) ($smtp['smtp_puerto'] ?: 587);
            } else {
                $mail->isMail();
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($from, APP_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment['path'], $attachment['filename']);
            }
            return $mail->send();
        }

        $message = self::buildMimeMessage($from, $to, $subject, $html, $attachments);
        if (!empty($smtp['smtp_host']) && !empty($smtp['smtp_usuario']) && !empty($smtp['smtp_password'])) {
            return self::enviarPorSmtp($smtp, $from, $to, $message);
        }

        [$headers, $body] = explode("\r\n\r\n", $message, 2);
        return @mail($to, self::encodeHeader($subject), $body, $headers);
    }

    public static function enviarTickets(array $compra, array $tokens, ?array $ticketPdf = null): bool
    {
        $html = self::ticketEmailHtml($compra, $tokens);

        return self::enviar(
            $compra['correo'],
            '🎟️ Tu compra fue confirmada - ' . $compra['codigo_compra'],
            $html,
            $ticketPdf ? [$ticketPdf] : []
        );
    }

    private static function ticketEmailHtml(array $compra, array $tokens): string
    {
        $cliente = e($compra['nombre_completo'] ?? 'invitado');
        $tituloPelicula = (string) ($compra['titulo'] ?? 'Cine SOE');
        $pelicula = e(function_exists('mb_strtoupper') ? mb_strtoupper($tituloPelicula, 'UTF-8') : strtoupper($tituloPelicula));
        $fecha = self::fechaEvento($compra['fecha_funcion'] ?? null);
        $hora = !empty($compra['hora_funcion']) ? substr((string) $compra['hora_funcion'], 0, 5) . ' hrs' : 'Hora por confirmar';
        $botones = '';

        foreach (array_values($tokens) as $index => $token) {
            $url = e(BASE_URL . '/ticket.php?token=' . $token);
            $numero = $index + 1;
            $botones .= '
                <tr>
                    <td style="padding:8px 0;">
                        <a href="' . $url . '" target="_blank" rel="noopener" style="display:block;background:linear-gradient(135deg,#8b5cf6,#06b6d4);color:#ffffff;text-decoration:none;text-align:center;font-weight:800;font-size:16px;letter-spacing:.2px;padding:15px 20px;border-radius:16px;box-shadow:0 12px 30px rgba(139,92,246,.28);">
                            Ver entrada #' . $numero . '
                        </a>
                    </td>
                </tr>';
        }

        return '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tu compra fue confirmada</title>
</head>
<body style="margin:0;padding:0;background:#070711;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;color:transparent;">Tus entradas Cine SOE ya están listas. Presenta tu QR el día del evento.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#070711;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:28px 14px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#10101d;border:1px solid rgba(255,255,255,.10);border-radius:28px;overflow:hidden;box-shadow:0 28px 80px rgba(0,0,0,.45);">
                    <tr>
                        <td style="padding:34px 28px;background:linear-gradient(135deg,#241044 0%,#111827 48%,#05243a 100%);">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:3px;color:#67e8f9;font-weight:800;margin-bottom:16px;">Art &amp; Market / SOE</div>
                            <h1 style="margin:0;color:#ffffff;font-size:32px;line-height:1.12;font-weight:900;">🎟️ ¡Tu compra fue confirmada!</h1>
                            <p style="margin:16px 0 0;color:#d8d5ff;font-size:16px;line-height:1.7;">Hola <strong style="color:#ffffff;">' . $cliente . '</strong>,</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 28px 10px;">
                            <p style="margin:0 0 16px;color:#e5e7eb;font-size:16px;line-height:1.75;">Gracias por apoyar esta actividad organizada por <strong style="color:#c4b5fd;">Arte &amp; Marketing de la SOE</strong>.</p>
                            <p style="margin:0 0 16px;color:#e5e7eb;font-size:16px;line-height:1.75;">Tu pago fue aprobado correctamente y tus entradas ya están listas.</p>
                            <p style="margin:0;color:#aab3c5;font-size:15px;line-height:1.75;">Cada botón corresponde a una entrada individual con su código QR de acceso. Recuerda presentar tu QR el día del evento.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 28px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#181827;border:1px solid rgba(139,92,246,.35);border-radius:22px;">
                                <tr>
                                    <td style="padding:22px;">
                                        <div style="font-size:12px;text-transform:uppercase;letter-spacing:2.4px;color:#22d3ee;font-weight:900;margin-bottom:8px;">Evento</div>
                                        <div style="color:#ffffff;font-size:21px;line-height:1.3;font-weight:900;">CINE SOE DE FICCIÓN — ' . $pelicula . '</div>
                                        <div style="height:14px;"></div>
                                        <div style="color:#d1d5db;font-size:15px;line-height:1.8;">📍 Auditorio Torre América – Piso 12</div>
                                        <div style="color:#d1d5db;font-size:15px;line-height:1.8;">🕒 ' . e($fecha) . ' • ' . e($hora) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 28px 26px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                ' . $botones . '
                            </table>
                            <p style="margin:18px 0 0;color:#8f9bb3;font-size:13px;line-height:1.7;text-align:center;">También adjuntamos tus entradas en PDF para que puedas guardarlas o compartirlas fácilmente.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 30px;background:#0b0b15;border-top:1px solid rgba(255,255,255,.08);text-align:center;">
                            <p style="margin:0 0 10px;color:#e9d5ff;font-size:15px;line-height:1.7;">Gracias por apoyar el arte, la cultura y las actividades estudiantiles 💜</p>
                            <p style="margin:0;color:#67e8f9;font-size:13px;font-weight:800;letter-spacing:.8px;">— Art &amp; Market / SOE</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    private static function fechaEvento(?string $fecha): string
    {
        if (!$fecha) {
            return 'Fecha por confirmar';
        }

        $meses = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril',
            '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto',
            '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre',
        ];

        $timestamp = strtotime($fecha);
        if (!$timestamp) {
            return 'Fecha por confirmar';
        }

        return date('d', $timestamp) . ' de ' . ($meses[date('m', $timestamp)] ?? date('m', $timestamp));
    }

    private static function normalizarAdjuntos(array $attachments): array
    {
        $valid = [];
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }

            $valid[] = [
                'path' => $path,
                'filename' => basename((string) ($attachment['filename'] ?? $path)),
                'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
            ];
        }

        return $valid;
    }

    private static function buildMimeMessage(string $from, string $to, string $subject, string $html, array $attachments): string
    {
        $boundary = 'cine-soe-' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . self::encodeHeader(APP_NAME) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@cine-soe.local>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n";

        foreach ($attachments as $attachment) {
            $body .= "--{$boundary}\r\n"
                . 'Content-Type: ' . $attachment['mime'] . '; name="' . self::headerParam($attachment['filename']) . '"' . "\r\n"
                . 'Content-Disposition: attachment; filename="' . self::headerParam($attachment['filename']) . '"' . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode((string) file_get_contents($attachment['path']))) . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private static function enviarPorSmtp(array $smtp, string $from, string $to, string $message): bool
    {
        $host = (string) $smtp['smtp_host'];
        $port = (int) ($smtp['smtp_puerto'] ?: 587);
        $transport = $port === 465 ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $error, 20);
        if (!is_resource($socket)) {
            error_log('SMTP connection error: ' . $error);
            return false;
        }

        stream_set_timeout($socket, 20);

        try {
            self::smtpExpect($socket, [220]);
            self::smtpCommand($socket, 'EHLO localhost', [250]);
            if ($port !== 465) {
                self::smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar TLS SMTP.');
                }
                self::smtpCommand($socket, 'EHLO localhost', [250]);
            }

            self::smtpCommand($socket, 'AUTH LOGIN', [334]);
            self::smtpCommand($socket, base64_encode((string) $smtp['smtp_usuario']), [334]);
            self::smtpCommand($socket, base64_encode((string) $smtp['smtp_password']), [235]);
            self::smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::smtpCommand($socket, 'DATA', [354]);
            fwrite($socket, preg_replace('/^\./m', '..', $message) . "\r\n.\r\n");
            self::smtpExpect($socket, [250]);
            self::smtpCommand($socket, 'QUIT', [221]);

            fclose($socket);
            return true;
        } catch (Throwable $exception) {
            error_log('SMTP send error: ' . $exception->getMessage());
            if (is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }

    private static function smtpCommand($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");

        return self::smtpExpect($socket, $expected);
    }

    private static function smtpExpect($socket, array $expected): string
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('SMTP no respondio.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
        }

        return $response;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function headerParam(string $value): string
    {
        return str_replace(['"', "\r", "\n"], '', $value);
    }

    private static function smtpConfig(): array
    {
        $defaults = [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_puerto' => '587',
            'smtp_usuario' => 'emlvpel@gmail.com',
            'smtp_password' => getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '',
        ];

        if (!class_exists('Database')) {
            return $defaults;
        }

        try {
            $rows = Database::getConnection()
                ->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'smtp_%'")
                ->fetchAll();
            return array_merge($defaults, array_column($rows, 'valor', 'clave'));
        } catch (Throwable) {
            return $defaults;
        }
    }
}
