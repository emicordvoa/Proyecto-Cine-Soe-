<?php
// Try to reset OPcache (useful if PHP-FPM/Apache has OPcache enabled)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset\n";
} else {
    echo "opcache_reset() not available\n";
}
echo 'Script filename: ' . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown') . "\n";
