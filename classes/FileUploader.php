<?php
declare(strict_types=1);

class FileUploader
{
    private const MAX_IMAGE_SIDE = 12000;
    private const MAX_IMAGE_PIXELS = 40000000;

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

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('El archivo esta vacio.');
        }

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('El archivo supera el maximo de 5MB.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('Formato no permitido. Usa JPG, PNG o PDF.');
        }

        $name = sprintf('compra_%d_%s.%s', $compraId, bin2hex(random_bytes(8)), self::ALLOWED_MIME[$mime]);
        $destination = UPLOAD_PATH . '/comprobantes/pendientes/' . $name;

        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de comprobantes.');
        }

        if ($mime === 'image/jpeg') {
            self::validarDimensionesImagen($tmpName);
            self::guardarJpegSinMetadata($tmpName, $destination);
        } elseif ($mime === 'image/png') {
            self::validarDimensionesImagen($tmpName);
            self::guardarPngSinMetadata($tmpName, $destination);
        } else {
            self::validarPdfNormal($tmpName);
            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('No se pudo guardar el archivo.');
            }
        }

        return $name;
    }

    public static function qrPago(array $file, int $usuarioId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el QR de pago.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('El QR esta vacio.');
        }

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('El QR supera el maximo de 5MB.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new RuntimeException('No se pudo leer el QR.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Formato no permitido. Sube el QR en JPG o PNG.');
        }

        self::validarDimensionesImagen($tmpName);

        $extension = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $name = sprintf('vendedor_%d_%s.%s', $usuarioId, bin2hex(random_bytes(8)), $extension);
        $destination = UPLOAD_PATH . '/qr-pagos/' . $name;

        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de QR de pago.');
        }

        if ($mime === 'image/jpeg') {
            self::guardarJpegSinMetadata($tmpName, $destination);
        } else {
            self::guardarPngSinMetadata($tmpName, $destination);
        }

        return $name;
    }

    public static function imagenPelicula(array $file, string $titulo): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir la imagen de cartelera.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            throw new RuntimeException('La imagen de cartelera esta vacia.');
        }

        if ((int) $file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('La imagen supera el maximo de 5MB.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Formato no permitido. Sube la imagen en JPG o PNG.');
        }

        self::validarDimensionesImagen($tmpName);

        $slug = self::slug($titulo);
        $extension = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $name = sprintf('%s-%s.%s', $slug, bin2hex(random_bytes(4)), $extension);
        $destination = ROOT_PATH . '/assets/img/' . $name;

        if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de imagenes.');
        }

        if ($mime === 'image/jpeg') {
            self::guardarJpegSinMetadata($tmpName, $destination);
        } else {
            self::guardarPngSinMetadata($tmpName, $destination);
        }

        return $name;
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
            throw new RuntimeException('La imagen es demasiado grande o no parece un comprobante normal.');
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

    private static function guardarJpegSinMetadata(string $source, string $destination): void
    {
        $data = file_get_contents($source);
        if ($data === false || substr($data, 0, 2) !== "\xFF\xD8") {
            throw new RuntimeException('La imagen JPG no es valida.');
        }

        $length = strlen($data);
        $offset = 2;
        $output = "\xFF\xD8";
        $foundScan = false;
        $allowedMarkers = [
            0xC0 => true, 0xC1 => true, 0xC2 => true, 0xC3 => true,
            0xC5 => true, 0xC6 => true, 0xC7 => true, 0xC9 => true,
            0xCA => true, 0xCB => true, 0xCD => true, 0xCE => true,
            0xCF => true, 0xC4 => true, 0xDB => true, 0xDD => true,
        ];

        while ($offset < $length) {
            $markerStart = $offset;
            if ($data[$offset] !== "\xFF") {
                throw new RuntimeException('La imagen JPG contiene datos invalidos.');
            }

            while ($offset < $length && $data[$offset] === "\xFF") {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $marker = ord($data[$offset]);
            $offset++;

            if ($marker === 0xD9) {
                $output .= "\xFF\xD9";
                break;
            }

            if ($marker === 0xDA) {
                $scanData = substr($data, $markerStart);
                if (!str_contains($scanData, "\xFF\xD9")) {
                    throw new RuntimeException('La imagen JPG no tiene cierre valido.');
                }
                $output .= $scanData;
                $foundScan = true;
                break;
            }

            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                continue;
            }

            if ($offset + 2 > $length) {
                throw new RuntimeException('La imagen JPG esta incompleta.');
            }

            $segmentLength = unpack('n', substr($data, $offset, 2))[1];
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                throw new RuntimeException('La imagen JPG tiene segmentos invalidos.');
            }

            if (isset($allowedMarkers[$marker])) {
                $output .= "\xFF" . chr($marker) . substr($data, $offset, $segmentLength);
            }

            $offset += $segmentLength;
        }

        if (!$foundScan || file_put_contents($destination, $output, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo limpiar y guardar la imagen JPG.');
        }
    }

    private static function guardarPngSinMetadata(string $source, string $destination): void
    {
        $data = file_get_contents($source);
        $signature = "\x89PNG\x0D\x0A\x1A\x0A";
        if ($data === false || substr($data, 0, 8) !== $signature) {
            throw new RuntimeException('La imagen PNG no es valida.');
        }

        $length = strlen($data);
        $offset = 8;
        $output = $signature;
        $foundIhdr = false;
        $foundIend = false;

        while ($offset + 12 <= $length) {
            $chunkStart = $offset;
            $chunkLength = unpack('N', substr($data, $offset, 4))[1];
            $offset += 4;
            $type = substr($data, $offset, 4);
            $offset += 4;

            if (!preg_match('/^[A-Za-z]{4}$/', $type) || $offset + $chunkLength + 4 > $length) {
                throw new RuntimeException('La imagen PNG contiene bloques invalidos.');
            }

            $chunkData = substr($data, $offset, $chunkLength);
            $offset += $chunkLength;
            $crc = substr($data, $offset, 4);
            $offset += 4;

            $storedCrc = unpack('N', $crc)[1];
            $calculatedCrc = crc32($type . $chunkData);
            if ($calculatedCrc < 0) {
                $calculatedCrc += 4294967296;
            }
            if ($storedCrc !== $calculatedCrc) {
                throw new RuntimeException('La imagen PNG no paso la verificacion de integridad.');
            }

            if ($type === 'IHDR') {
                $foundIhdr = true;
            }

            $isCritical = ctype_upper($type[0]);
            if ($isCritical) {
                $output .= substr($data, $chunkStart, 12 + $chunkLength);
            }

            if ($type === 'IEND') {
                $foundIend = true;
                break;
            }
        }

        if (!$foundIhdr || !$foundIend || file_put_contents($destination, $output, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo limpiar y guardar la imagen PNG.');
        }
    }

    private static function validarPdfNormal(string $source): void
    {
        $data = file_get_contents($source);
        if ($data === false || !preg_match('/\A%PDF-\d\.\d/', $data)) {
            throw new RuntimeException('El PDF no es valido.');
        }

        if (!str_contains(substr($data, -2048), '%%EOF')) {
            throw new RuntimeException('El PDF esta incompleto o danado.');
        }

        $forbidden = [
            '/JavaScript', '/JS', '/AA', '/OpenAction', '/Launch', '/EmbeddedFile',
            '/Filespec', '/RichMedia', '/Encrypt', '/AcroForm', '/XFA',
        ];

        foreach ($forbidden as $needle) {
            if (stripos($data, $needle) !== false) {
                throw new RuntimeException('El PDF contiene elementos no permitidos. Sube un PDF plano o una imagen del comprobante.');
            }
        }
    }
}
