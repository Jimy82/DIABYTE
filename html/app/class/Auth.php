<?php
declare(strict_types=1);

/**
 * Clase Auth
 * Maneja registro, login y validación de sesiones de usuario.
 * Requiere tabla `users` con columnas:
 *   id INT AUTO_INCREMENT,
 *   email VARCHAR(190) UNIQUE,
 *   password_hash VARCHAR(255),
 *   full_name VARCHAR(120),
 *   is_active TINYINT(1) DEFAULT 1,
 *   role ENUM('user','admin') DEFAULT 'user'
 */

namespace App\Core;

use PDO;
use RuntimeException;

class Auth
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Registro de usuario */
    public function register(string $email, string $password, string $name): bool
    {
        $email = strtolower(trim($email));
        $name = trim($name);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email no válido');
        }
        if (strlen($password) < 6) {
            throw new RuntimeException('Contraseña demasiado corta');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('INSERT INTO users (email, password_hash, full_name) VALUES (?, ?, ?)');
        return $stmt->execute([$email, $hash, $name]);
    }

    /** Autenticación */
    public function login(string $email, string $password): bool
    {
        $email = strtolower(trim($email));

        $stmt = $this->db->prepare('SELECT id, password_hash, is_active, full_name, role FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int)$user['is_active'] !== 1) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Autenticación correcta
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        return true;
    }

    /** Logout seguro */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Verifica si hay sesión válida */
    public function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Devuelve datos básicos del usuario */
    public function user(): ?array
    {
        if (!$this->check()) return null;
        return [
            'id'    => (int)($_SESSION['user_id'] ?? 0),
            'email' => $_SESSION['user_email'] ?? '',
            'name'  => $_SESSION['user_name'] ?? '',
            'role'  => $_SESSION['user_role'] ?? 'user'
        ];
    }

    /** Middleware: requiere login */
    public function requireLogin(): void
    {
        if (!$this->check()) {
            header('Location: login.php', true, 303);
            exit;
        }
    }

    /** Middleware: requiere rol administrador */
    public function requireAdmin(): void
    {
        if (!$this->check() || ($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo 'Acceso restringido';
            exit;
        }
    }
}
