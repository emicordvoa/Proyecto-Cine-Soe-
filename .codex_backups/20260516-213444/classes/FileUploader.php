<?php
declare(strict_types=1);

class FileUploader
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public static function comprobante(array $file, int $compraId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el comprobante.');
        }

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('El archivo supera el maximo de 5MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('Formato no permitido. Usa JPG, PNG o PDF.');
        }

        $name = sprintf('compra_%d_%s.%s', $compraId, bin2hex(random_bytes(8)), self::ALLOWED_MIME[$mime]);
        $destination = UPLOAD_PATH . '/comprobantes/pendientes/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        return $name;
    }
}
