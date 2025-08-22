<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../staff/_bootstrap.php';

try {
  global $pdo;
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('DB not initialized');
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Set timezone to JST
  $pdo->exec("SET time_zone = '+09:00'");

  // 列の存在確認（created_at / order_date の有無）
  $cols = array_column($pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_ASSOC), 'Field');
  $createdCol   = in_array('created_at', $cols, true) ? 'created_at' : null;
  $hasOrderDate = in_array('order_date', $cols, true);

  // 表示対象ステータス（スキーマのENUMに合わせる）
  $statuses = ['confirmed', 'preparing', 'ready'];
  $placeholders = implode(',', array_fill(0, count($statuses), '?'));

  // 並び順は「受付時刻の昇順」。created_at があればそれを使用
  $orderBy = $createdCol ? "$createdCol ASC, id ASC" : "id ASC";

  // 取得カラムはスキーマ準拠＋フロント互換のエイリアス
  $selectCreated = $createdCol ? $createdCol : "NULL AS created_at";
  $sql = "SELECT id,
                 order_no,
                 status,
                 {$selectCreated},
                 COALESCE(items_total, 0) AS total_qty,
                 COALESCE(amount_yen, 0) AS amount
          FROM orders
          WHERE status IN ($placeholders)";

  // 日付フィルタ：order_date があればそれを使用。無ければ created_at の当日範囲、どちらも無ければスキップ
  if ($hasOrderDate) {
    $sql .= " AND order_date = CURDATE()"; // JST は上で SET time_zone 済み
  } elseif ($createdCol) {
    $sql .= " AND {$createdCol} >= CURDATE() AND {$createdCol} < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
  }

  $sql .= " ORDER BY {$orderBy} LIMIT 500";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($statuses);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}