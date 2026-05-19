<?php
require __DIR__ . '/config/config.php';
echo 'config: ' . (function_exists('combine_name_parts') ? 'OK' : 'MISSING');

// Also show the loaded file and script path for debugging
echo "\nLoaded config: " . (defined('ROOT_PATH') ? ROOT_PATH . '/config/config.php' : 'ROOT_PATH not defined');
echo "\nScript filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
