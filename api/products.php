<?php
require_once __DIR__ . '/../env.php';
// GET /api/products : 販売中の商品一覧を返す
header('Content-Type: application/json; charset=utf-8');
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'cake_shop';
$dbUser = getenv('DB_USER') ?: 'cake_user';
$dbPass = getenv('DB_PASS') ?: 'wor]eaSy';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName),
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$st = $pdo->query("SELECT id, name, price_yen, image_path FROM products WHERE status='selling' ORDER BY display_order, id");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['ok'=>true, 'products'=>$rows], JSON_UNESCAPED_UNICODE);
