<?php
declare(strict_types=1);

class Notificacion
{
    public static function enviarWhatsAppVendedor($vendedorWhatsapp, $clienteNombre, $pelicula, $cantidad, $monto, $codigoCompra): string
    {
        $mensaje = "Nueva compra! $clienteNombre compró $cantidad entrada(s) para $pelicula por Bs $monto. Código: $codigoCompra";
        return "https://wa.me/" . preg_replace('/\D/', '', (string) $vendedorWhatsapp) . "?text=" . urlencode($mensaje);
    }

    public static function enviarWhatsAppCliente(string $clienteWhatsapp, array $tokens): string
    {
        $links = array_map(fn (string $token): string => BASE_URL . '/ticket.php?token=' . $token, $tokens);
        $mensaje = "Tus entradas Cine SOE:\n" . implode("\n", $links);
        return "https://wa.me/" . preg_replace('/\D/', '', $clienteWhatsapp) . "?text=" . urlencode($mensaje);
    }
}
