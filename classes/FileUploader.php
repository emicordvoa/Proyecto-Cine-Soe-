<?php
declare(strict_types=1);

class FileUploader
{
    private const MAX_IMAGE_SIDE = 12000;
    private const MAX_IMAGE_PIXELS = 50000000;

    private const COMPROBANTE_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
    ];

    private const IMAGE_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function comprobante(array $file, int $compraId): string
    {
        self::validarUploadBasico($file, 'comprobante');

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('El archivo supera el maximo de 5MB.');
        }

        $tmpName = self::tmpName($file);
        $extension = self::extensionOriginal($file);
        if (!in_array($extension, ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'pdf'], true)) {
            throw new RuntimeException('Extension de archivo no permitida.');
        }

        $mime = self::mime($tmpName);
        $finalExtension = self::extensionComprobante($tmpName, $extension, $mime);

        if ($finalExtension === 'pdf') {
            self::validarPdfNormal($tmpName);
        } else {
            self::validarDimensionesImagen($tmpName);
        }

        $name = sprintf('compra_%d_%s.%s', $compraId, bin2hex(random_bytes(8)), $finalExtension);
        $destination = UPLOAD_PATH . '/comprobantes/pendientes/' . $name;
        self::guardarSubido($tmpName, $destination);

        return $name;
    }

    public static function qrPago(array $file, int $usuarioId): string
    {
        self::validarUploadBasico($file, 'QR de pago');

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('El QR supera el maximo de 5MB.');
        }

        $tmpName = self::tmpName($file);
        $mime = self::mime($tmpName);
        if (!isset(self::IMAGE_MIME[$mime])) {
            throw new RuntimeException('Formato no permitido. Sube el QR en JPG, PNG o WEBP.');
        }

        $extension = self::extensionOriginal($file);
        if (!in_array($extension, ['jpg', 'jpeg', 'jfif', 'png', 'webp'], true)) {
            throw new RuntimeException('Extension de archivo no permitida.');
        }

        self::validarDimensionesImagen($tmpName);

        $name = sprintf('vendedor_%d_%s.%s', $usuarioId, bin2hex(random_bytes(8)), self::IMAGE_MIME[$mime]);
        $destination = UPLOAD_PATH . '/qr-pagos/' . $name;
        self::guardarSubido($tmpName, $destination);

        return $name;
    }

    public static function imagenPelicula(array $file, string $titulo): string
    {
        self::validarUploadBasico($file, 'imagen de cartelera');

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('La imagen supera el maximo de 5MB.');
        }

        $tmpName = self::tmpName($file);
        $mime = self::mime($tmpName);
        if (!isset(self::IMAGE_MIME[$mime])) {
            throw new RuntimeException('Formato no permitido. Sube la imagen en JPG, PNG o WEBP.');
        }

        $extension = self::extensionOriginal($file);
        if (!in_array($extension, ['jpg', 'jpeg', 'jfif', 'png', 'webp'], true)) {
            throw new RuntimeException('Extension de archivo no permitida.');
        }

        self::validarDimensionesImagen($tmpName);

        $name = sprintf('%s-%s.%s', self::slug($titulo), bin2hex(random_bytes(4)), self::IMAGE_MIME[$mime]);
        $destination = ROOT_PATH . '/assets/img/' . $name;
        self::guardarSubido($tmpName, $destination);

        return $name;
    }

    private static function validarUploadBasico(array $file, string $label): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new RuntimeException('El archivo supera el tamano permitido.');
            }

            if ($error === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Selecciona un archivo para subir.');
            }

            throw new RuntimeException('No se pudo subir el ' . $label . '.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('El archivo esta vacio.');
        }
    }

    private static function tmpName(array $file): string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        return $tmpName;
    }

    private static function mime(string $tmpName): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($tmpName);
    }

    private static function extensionOriginal(array $file): string
    {
        return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    }

    private static function extensionComprobante(string $tmpName, string $extension, string $mime): string
    {
        if (isset(self::COMPROBANTE_MIME[$mime])) {
            return self::COMPROBANTE_MIME[$mime];
        }

        if ($extension === 'pdf') {
            self::validarPdfNormal($tmpName);
            return 'pdf';
        }

        if (in_array($extension, ['jpg', 'jpeg', 'jfif', 'png', 'webp'], true) && getimagesize($tmpName) !== false) {
            return in_array($extension, ['jpeg', 'jfif'], true) ? 'jpg' : $extension;
        }

        throw new RuntimeException('Formato no permitido. Usa JPG, PNG, WEBP o PDF.');
    }

    private static function guardarSubido(string $tmpName, string $destination): void
    {
        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de archivos.');
        }

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }
    }

    private static function validarDimensionesImagen(string $source): void
    {
        $imageInfo = getimagesize($source);
        if ($imageInfo === false) {
            throw new RuntimeException('La imagen no es valida.');
        }

        [$width, $height] = $imageInfo;
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('La imagen tiene dimensiones invalidas.');
        }

        if ($width > self::MAX_IMAGE_SIDE || $height > self::MAX_IMAGE_SIDE || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('La imagen es demasiado grande.');
        }
    }

    private static function validarPdfNormal(string $source): void
    {
        $handle = fopen($source, 'rb');
        if (!$handle) {
            throw new RuntimeException('No se pudo leer el PDF.');
        }

        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false || !str_starts_with($header, '%PDF-')) {
            throw new RuntimeException('El PDF no es valido.');
        }
    }

    private static function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 60) : 'pelicula';
    }
}
