<?php
declare(strict_types=1);

class QRGenerator
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? UPLOAD_PATH . '/qr';
    }

    public function generate(int $id_entrada, string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            throw new InvalidArgumentException('Token de validacion invalido.');
        }

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $file = sprintf('qr_%d_%s.png', $id_entrada, bin2hex(random_bytes(4)));
        $path = $this->dir . '/' . $file;
        $content = rtrim(SITE_URL, '/') . '/validar/' . $token;

        if (class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            $result = \Endroid\QrCode\Builder\Builder::create()
                ->data($content)
                ->size(300)
                ->margin(10)
                ->build();
            $result->saveToFile($path);
        } elseif (class_exists(\chillerlan\QRCode\QRCode::class)) {
            $options = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG, 'scale' => 8]);
            (new \chillerlan\QRCode\QRCode($options))->render($content, $path);
        } else {
            throw new RuntimeException('Instala endroid/qr-code o chillerlan/php-qrcode con Composer.');
        }

        return 'uploads/qr/' . $file;
    }

    public function getQRPath(int $id_entrada): ?string
    {
        $files = glob($this->dir . '/qr_' . $id_entrada . '_*.png') ?: [];
        return $files ? $files[0] : null;
    }

    public function delete(int $id_entrada): bool
    {
        $path = $this->getQRPath($id_entrada);
        return $path ? unlink($path) : false;
    }
}
