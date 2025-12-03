<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauth']);
    exit;
}

require __DIR__ . '/../_csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'json']);
    exit;
}

if (!csrf_check($data['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'csrf']);
    exit;
}

$foodId   = (int)($data['food_id']   ?? 0);
$grams    = (float)($data['grams']   ?? 0);
$preBg    = isset($data['pre_bg'])     ? (float)$data['pre_bg']     : null;
$postBg   = isset($data['post_bg'])    ? (float)$data['post_bg']    : null;
$carbs_g  = isset($data['carbs_g'])    ? (float)$data['carbs_g']    : 0.0;
$dose     = array_key_exists('dose_units', $data) && $data['dose_units'] !== null
            ? (float)$data['dose_units']
            : null;

if ($foodId <= 0 || $grams <= 0 || $carbs_g < 0) {
    http_response_code(422);
    echo json_encode(['error' => 'params']);
    exit;
}

require __DIR__ . '/../config/db.php';
$pdo = db();

$st = $pdo->prepare(
    'INSERT INTO intakes
        (user_id, source_type, source_id, grams, carbs_g, dose_units, pre_bg, post_bg, occurred_at)
     VALUES
        (:uid,    :stype,      :sid,     :g,    :carb,   :dose,      :pre,  :post,   NOW())'
);

$st->execute([
    ':uid'   => (int)$_SESSION['user_id'],
    ':stype' => 'food',
    ':sid'   => $foodId,
    ':g'     => $grams,
    ':carb'  => $carbs_g,
    ':dose'  => $dose,
    ':pre'   => $preBg,
    ':post'  => $postBg,
]);

echo json_encode([
    'ok'  => true,
    'id'  => (int)$pdo->lastInsertId(),
]);
