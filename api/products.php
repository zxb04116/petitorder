<?php
require_once __DIR__ . '/_bootstrap.php';
send_common_headers();
// GET /api/products : 販売中の商品一覧を返す
header('Content-Type: application/json; charset=utf-8');
$st = $pdo->query("SELECT id, name, price_yen, image_path FROM products WHERE status='selling' ORDER BY display_order, id");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['ok'=>true, 'products'=>$rows], JSON_UNESCAPED_UNICODE);
