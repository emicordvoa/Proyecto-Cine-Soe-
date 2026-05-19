<?php
require __DIR__ . '/admin/_bootstrap.php';
echo 'bootstrap: ' . (function_exists('combine_name_parts') ? 'OK' : 'MISSING');
echo "\nScript filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
