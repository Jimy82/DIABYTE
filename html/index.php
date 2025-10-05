<?php
declare(strict_types=1);

$autoloads = [
  __DIR__ . '/vendor/autoload.php',
  dirname(__DIR__) . '/vendor/autoload.php',
];
foreach ($autoloads as $a) { if (file_exists($a)) { require $a; break; } }

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
