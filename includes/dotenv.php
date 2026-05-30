<?php
function loadDotenv($path = null) {
    if ($path === null) {
        $path = dirname(__DIR__) . '/.env';
    }
    if (!file_exists($path)) {
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
            }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\'');
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
    return true;
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    $lower = strtolower($value);
    if ($lower === 'true' || $lower === '(true)') return true;
    if ($lower === 'false' || $lower === '(false)') return false;
    return $value;
    }
