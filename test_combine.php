<?php
// Test whether combine_name_parts is available when requiring config
require __DIR__ . '/config/config.php';
echo 'combine_name_parts in config: ' . (function_exists('combine_name_parts') ? 'OK' : 'MISSING') . "\n";
echo 'function defined in file: ' . (new ReflectionFunction('combine_name_parts'))->getFileName() . "\n";
echo 'Script filename: ' . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown') . "\n";
