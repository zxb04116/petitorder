<?php
require_once __DIR__ . '/../env.php';
// POST /api/orders_create.php : 注文確定（在庫なし版）
// 期待するJSON: { "items":[{"product_id":1,"qty":2}, ...], "pickup_slot":"12:30" }
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']); exit;
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['items']) || !is_array($data['items'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid payload']); exit;
}
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
$orderDate = date('Y-m-d');
$pickup = isset($data['pickup_slot']) ? $data['pickup_slot'] : null;

$pdo->beginTransaction();
try {
  // 4桁連番
  $stmt = $pdo->prepare("
    SELECT LPAD(COALESCE(MAX(CAST(SUBSTRING(order_no, 8) AS UNSIGNED)), 0) + 1, 4, '0') AS seq
    FROM orders WHERE order_date = ?
  ");
  $stmt->execute([$orderDate]);
  $seq = $stmt->fetchColumn() ?: '0001';
  $orderNo = date('ymd', strtotime($orderDate)) . '-' . $seq;

  // 金額計算
  $amount = 0; $qtyTotal = 0; $itemsDetail = [];
  $p = $pdo->prepare("SELECT price_yen FROM products WHERE id=? AND status='selling'");
  foreach ($data['items'] as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($pid<=0 || $qty<=0) { throw new Exception('不正な商品または数量'); }
    $p->execute([$pid]);
    $price = $p->fetchColumn();
    if ($price === false) throw new Exception('販売中でない商品が含まれています');
    $amount += $price * $qty; $qtyTotal += $qty;
    $itemsDetail[] = ['product_id'=>$pid, 'qty'=>$qty, 'unit_price'=>(int)$price];
  }

  // 注文登録
  $ins = $pdo->prepare("INSERT INTO orders (order_no, order_date, pickup_slot, status, items_total, amount_yen)
                        VALUES (?, ?, ?, 'confirmed', ?, ?)");
  $ins->execute([$orderNo, $orderDate, $pickup, $qtyTotal, $amount]);
  $orderId = $pdo->lastInsertId();

  $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)");
  foreach ($itemsDetail as $row) {
    $insItem->execute([$orderId, $row['product_id'], $row['qty'], $row['unit_price']]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'order_no'=>$orderNo,'amount'=>$amount], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
