<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
session_start(); if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode([]); exit; }
require __DIR__ . '/config/db.php';
$pdo = db();
$q=trim((string)($_GET['q']??'')); if($q!==''){ $st=$pdo->prepare('SELECT id,name,carbs_per_100g,unit FROM foods WHERE name LIKE ? ORDER BY name LIMIT 500'); $st->execute(['%'.$q.'%']); }
else { $st=$pdo->query('SELECT id,name,carbs_per_100g,unit FROM foods ORDER BY name LIMIT 500'); }
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
