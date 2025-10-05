<?php
declare(strict_types=1);

/**
 * guardar_edicion_menu.php
 * Procesa los cambios enviados desde editar_menu.php.
 * Permite actualizar, añadir o eliminar elementos del menú seleccionado.
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

// === Validación de entrada ===
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

// === Verificar menú del usuario ===
$stmt = $pdo->prepare('SELECT id FROM menus WHERE user_id=? AND date=? LIMIT 1');
$stmt->execute([$userId, $date]);
$menu = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$menu) {
    http_response_code(404);
    echo 'Menú no encontrado';
    exit;
}
$menuId = (int)$menu['id'];

// === Procesar elementos enviados ===
// Se espera arrays paralelos: item_id[], grams[], action[]
$itemIds = $_POST['item_id'] ?? [];
$gramsArr = $_POST['grams'] ?? [];
$actions  = $_POST['action_item'] ?? [];

if (!is_array($itemIds) || count($itemIds) === 0) {
    redirect('editar_menu.php', ['date' => $date, 'ok' => 0]);
}

$pdo->beginTransaction();
try {
    foreach ($itemIds as $i => $idRaw) {
        $itemId = (int)$idRaw;
        $grams  = isset($gramsArr[$i]) ? (float)$gramsArr[$i] : 0;
        $action = $actions[$i] ?? 'update';

        if ($itemId <= 0) continue;

        // Asegurar pertenencia
        $own = $pdo->prepare('SELECT mi.id FROM menu_items mi INNER JOIN menus m ON m.id=mi.menu_id WHERE mi.id=? AND m.user_id=? AND m.id=? LIMIT 1');
        $own->execute([$itemId, $userId, $menuId]);
        if (!$own->fetchColumn()) continue;

        if ($action === 'delete') {
            $del = $pdo->prepare('DELETE FROM menu_items WHERE id=?');
            $del->execute([$itemId]);
        } elseif ($action === 'update' && $grams > 0 && $grams < 100000) {
            $upd = $pdo->prepare('UPDATE menu_items SET grams=? WHERE id=?');
            $upd->execute([$grams, $itemId]);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    redirect('editar_menu.php', ['date' => $date, 'ok' => 0]);
}

// Rotar CSRF y redirigir con confirmación
$_SESSION['csrf'] = bin2hex(random_bytes(32));
redirect('editar_menu.php', ['date' => $date, 'ok' => 1]);
