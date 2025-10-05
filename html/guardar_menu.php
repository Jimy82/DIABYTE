<?php
declare(strict_types=1);

/**
 * guardar_menu.php
 * Crea un menú nuevo con sus items (alimentos o recetas).
 * Se usa desde editar_menu.php o formularios iniciales.
 */

session_start();

// Bloqueo si no autenticado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Cabeceras seguras
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require __DIR__ . '/config/db.php';
$pdo = db();
$userId = (int)$_SESSION['user_id'];

// === Utilidades ===
function redirect(string $path, array $qs = [], int $code = 303): never {
    $url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . ltrim($path, '/');
    if ($qs) { $url .= '?' . http_build_query($qs); }
    header('Location: ' . $url, true, $code);
    exit;
}
function clean(string $s): string { return trim(preg_replace('/\s+/u', ' ', $s)); }
function valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y,$m,$day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

// CSRF
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }

// === Validación ===
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    exit;
}

$token = $_POST['csrf'] ?? '';
if (!csrf_check($token)) {
    http_response_code(400);
    echo 'Token CSRF inválido';
    exit;
}

$date = clean($_POST['date'] ?? '');
if (!valid_date($date)) {
    http_response_code(400);
    echo 'Fecha inválida';
    exit;
}

// === Crear menú (si no existe) ===
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM menus WHERE user_id=? AND date=? LIMIT 1');
    $stmt->execute([$userId, $date]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($menu) {
        $menuId = (int)$menu['id'];
    } else {
        $ins = $pdo->prepare('INSERT INTO menus (user_id, date) VALUES (?, ?)');
        $ins->execute([$userId, $date]);
        $menuId = (int)$pdo->lastInsertId();
    }

    // === Procesar items recibidos ===
    $types       = $_POST['type'] ?? [];
    $sourceTypes = $_POST['source_type'] ?? [];
    $sourceIds   = $_POST['source_id'] ?? [];
    $gramsArr    = $_POST['grams'] ?? [];

    $validTypes = ['desayuno','comida','cena','snack'];
    $validSource = ['food','recipe'];

    foreach ($sourceIds as $i => $sid) {
        $type       = $types[$i] ?? '';
        $sourceType = $sourceTypes[$i] ?? '';
        $grams      = isset($gramsArr[$i]) ? (float)$gramsArr[$i] : 0;
        $sid        = (int)$sid;

        if (!in_array($type, $validTypes, true) || !in_array($sourceType, $validSource, true)) continue;
        if ($sid <= 0 || $grams <= 0 || $grams > 100000) continue;

        // Verificación de existencia
        if ($sourceType === 'food') {
            $check = $pdo->prepare('SELECT id FROM foods WHERE id=? LIMIT 1');
            $check->execute([$sid]);
        } else {
            $check = $pdo->prepare('SELECT id FROM recipes WHERE id=? AND user_id=? LIMIT 1');
            $check->execute([$sid, $userId]);
        }
        if (!$check->fetchColumn()) continue;

        $insItem = $pdo->prepare('INSERT INTO menu_items (menu_id, type, source_type, source_id, grams) VALUES (?, ?, ?, ?, ?)');
        $insItem->execute([$menuId, $type, $sourceType, $sid, $grams]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    redirect('editar_menu.php', ['date' => $date, 'ok' => 0]);
}

// Rotar CSRF y redirigir
$_SESSION['csrf'] = bin2hex(random_bytes(32));
redirect('editar_menu.php', ['date' => $date, 'ok' => 1]);
