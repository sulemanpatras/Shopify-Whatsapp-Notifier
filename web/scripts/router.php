<?php

$public = dirname(__DIR__).DIRECTORY_SEPARATOR.'public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Serve real files (css, js, images) straight from public/.
if ($uri !== '/' && is_file($public.str_replace('/', DIRECTORY_SEPARATOR, $uri))) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $public.DIRECTORY_SEPARATOR.'index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $public.DIRECTORY_SEPARATOR.'index.php';