<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'unauth']); exit; }

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) { http_response_code(400); echo json_encode(['error'=>'json']); exit; }

if (empty($in['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$in['csrf'])) {
  http_response_code(400); echo json_encode(['error'=>'csrf']); exit;
}

$food_id   = (int)($in['food_id'] ?? 0);
$grams     = (float)($in['grams'] ?? 0);
$carbs_g   = (float)($in['carbs_g'] ?? 0);
$dose      = (float)($in['dose_units'] ?? 0);
$pre_bg    = isset($in['pre_bg'])  ? (int)$in['pre_bg']  : null;
$post_bg   = isset($in['post_bg']) ? (int)$in['post_bg'] : null;
$uid       = (int)$_SESSION['user_id'];

if ($food_id <= 0 || $grams <= 0 || $carbs_g < 0 || $dose < 0) {
  http_response_code(400); echo json_encode(['error'=>'input']); exit;
}

require __DIR__.'/../config/db.php'; // $pdo (PDO MySQL) con excepciones

$sql = "INSERT INTO intakes
 (user_id, food_id, source_type, source_id, grams, carbs_g, dose_units, pre_bg, post_bg, occurred_at)
 VALUES (:uid, :fid, 'food', :fid, :g, :c, :d, :pre, :post, NOW())";
$st = $pdo->prepare($sql);
$st->execute([
  ':uid'=>$uid, ':fid'=>$food_id, ':g'=>$grams, ':c'=>$carbs_g,
  ':d'=>$dose, ':pre'=>$pre_bg, ':post'=>$post_bg
]);

echo json_encode(['ok'=>1,'id'=>$pdo->lastInsertId()]);
