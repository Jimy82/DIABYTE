<?php
declare(strict_types=1);

if (!function_exists("db")) {
  function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn  = getenv("DB_DSN")  ?: "mysql:host=diabyte_db;dbname=diabyte;charset=utf8mb4";
    $user = getenv("DB_USER") ?: "root";
    $pass = getenv("DB_PASS") ?: "root";

    $opt = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opt);
    return $pdo;
  }
}
