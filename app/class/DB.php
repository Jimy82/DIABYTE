<?php
declare(strict_types=1);

/**
 * Clase DB
 * Proporciona conexión PDO singleton basada en variables de entorno (.env)
 * Requiere: vlucas/phpdotenv
 */

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Dotenv\Dotenv;

class DB
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function get(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        // Carga de variables .env (solo una vez)
        $root = dirname(__DIR__, 1);
        $env = Dotenv::createImmutable($root);
        $env->safeLoad();

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $db   = $_ENV['DB_NAME'] ?? 'dwes';
        $user = $_ENV['DB_USER'] ?? 'dwes';
        $pass = $_ENV['DB_PASS'] ?? 'abc123.';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Error de conexión a la base de datos: ' . $e->getMessage());
        }

        self::$instance = $pdo;
        return $pdo;
    }

    /** Cierra la conexión */
    public static function close(): void
    {
        self::$instance = null;
    }
}
