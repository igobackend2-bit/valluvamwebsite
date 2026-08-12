<?php
/**
 * Load .env from project root into getenv().
 * Call once early (e.g. from config.php). Safe to call multiple times (skips if already loaded).
 */
if (defined('VALLUVAM_ENV_LOADED')) {
    return;
}
$projectRoot = dirname(__DIR__, 2);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}
define('VALLUVAM_ENV_LOADED', true);
