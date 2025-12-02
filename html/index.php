<?php
declare(strict_types=1);

$autoload = __DIR__ . '/app/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    die("Falta autoload: $autoload");
}



session_start();

$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
if ($uri === '/' || $uri === '/index.php') {
    if (!empty($_SESSION['user_id'])) {
        header('Location: /escritorio.php', true, 303);
        exit;
    }
    header('Location: /registro.php', true, 303);
    exit;
}

http_response_code(404);
echo '404';
