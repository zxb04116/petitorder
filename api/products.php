<?php
// GET /api/products : 販売中の商品一覧を返す
header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO('mysql:host=localhost;dbname=cake_shop;charset=utf8mb4','dbuser','dbpass',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$st = $pdo->query("SELECT id, name, price_yen, image_path FROM products WHERE status='selling' ORDER BY display_order, id");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['ok'=>true, 'products'=>$rows], JSON_UNESCAPED_UNICODE);
