# Configuración de Carpetas de Upload - Cine SOE

## Estructura de carpetas requerida

El sistema requiere que existan las siguientes carpetas con permisos de escritura:

```
uploads/
├── comprobantes/
│   ├── pendientes/        (comprobantes en espera de validación)
│   ├── verificados/       (comprobantes aprobados)
│   └── rechazados/        (comprobantes rechazados)
├── qr/                    (0755 o 0775)
├── qr-pagos/              (0755 o 0775)
└── tickets/               (0755 o 0775)
```

## Instrucciones para EasyPanel / Servidor Linux

### 1. Crear estructura de carpetas

Accede al panel SSH/Terminal de EasyPanel y ejecuta:

```bash
cd /app

# Crear carpeta uploads si no existe
mkdir -p uploads/comprobantes/{pendientes,verificados,rechazados}
mkdir -p uploads/qr
mkdir -p uploads/qr-pagos
mkdir -p uploads/tickets

# Establecer permisos seguros
chmod -R 0775 uploads/
chmod -R 0775 uploads/comprobantes/
chmod -R 0775 uploads/comprobantes/pendientes/
chmod -R 0775 uploads/comprobantes/verificados/
chmod -R 0775 uploads/comprobantes/rechazados/

# Si es necesario, cambiar propietario (opcional, si da permisos denied)
# sudo chown -R www-data:www-data uploads/
```

### 2. Verificar permisos

```bash
ls -la /app/uploads/comprobantes/
```

Debe mostrar `drwxrwxr-x` (0755) o `drwxrwxrwx` (0777) para las carpetas.

### 3. Configurar volumen persistente (importante)

En EasyPanel, asegúrate de que `uploads/` esté configurado como **volumen persistente**, de lo contrario los archivos se perderán al reiniciar el contenedor.

- Ve a Settings de tu aplicación
- Busca "Volumes" o "Persistent Volumes"
- Agrega: `/app/uploads` → `/app/uploads` (persistente)

### 4. Verificar que PHP pueda escribir

Crea un test en `/app/test_upload.php`:

```php
<?php
$dirs = [
    '/app/uploads',
    '/app/uploads/comprobantes',
    '/app/uploads/comprobantes/pendientes',
    '/app/uploads/comprobantes/aprobados',
    '/app/uploads/comprobantes/rechazados',
];

foreach ($dirs as $dir) {
    $exists = is_dir($dir);
    $writable = is_writable($dir);
    echo "$dir: " . ($exists ? 'EXISTS' : 'MISSING') . " | " . ($writable ? 'WRITABLE' : 'NO WRITE') . "\n";
}
?>
```

Accede a `http://tudominio.com/test_upload.php` y verifica que todas las carpetas existan y sean escribibles.

## Solución de problemas

### Error: "No such file or directory" al mover comprobante

**Causas:**
1. La carpeta `aprobados/` no existe
2. El archivo nunca se guardó en `pendientes/`
3. Permisos insuficientes

**Solución:**
- Verifica que existan todas las carpetas (paso 1)
- Revisa permisos (paso 2)
- Revisa logs de PHP/Apache en EasyPanel

### Archivos desaparecen después de reiniciar

**Causa:** `uploads/` no está configurado como volumen persistente

**Solución:**
- Configura volúmenes persistentes en EasyPanel (paso 3)

### El archivo se sube pero no aparece

**Causa:** `uploads/` está en un contenedor Docker sin persistencia

**Solución:**
- Configura volúmenes persistentes (paso 3)
- O cambia la ruta en `config.php` a una carpeta compartida/persistente

## Rutas en el código

El código usa estas constantes (definidas en `config/config.php`):

```php
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
```

Donde `ROOT_PATH` es el directorio raíz del proyecto. En EasyPanel, típicamente es `/app`.

**Rutas finales:**
- Comprobantes pendientes: `/app/uploads/comprobantes/pendientes/`
- Comprobantes verificados (aprobados): `/app/uploads/comprobantes/verificados/`
- Comprobantes rechazados: `/app/uploads/comprobantes/rechazados/`

## Permisos recomendados

Para máxima seguridad:
- Carpetas: `0755` (rwxr-xr-x) - solo propietario escribe, otros pueden leer
- Archivos: `0644` (rw-r--r--) - solo propietario escribe

Para desarrollo o si tienes problemas:
- Carpetas: `0775` (rwxrwxr-x) - grupo también puede escribir
- Archivos: `0664` (rw-rw-r--) - grupo también puede escribir

## Logs y debug

Para verificar que el sistema está funcionando correctamente:

1. **Revisar logs de PHP:**
   - En EasyPanel: Logs > Aplicación > PHP
   - Busca warnings o errores de `rename()`, `mkdir()`, etc.

2. **Habilitar debug en desarrollo:**
   - Agregar en `config/config.php`:
     ```php
     error_reporting(E_ALL);
     ini_set('display_errors', '1');
     ```

3. **Revisar permisos del archivo web:**
   - El usuario que ejecuta PHP (normalmente `www-data`) debe ser propietario o parte del grupo de `uploads/`

---

**Nota:** El código ya incluye manejo automático de creación de carpetas, pero es recomendable crear manualmente la estructura inicial y verificar permisos.
