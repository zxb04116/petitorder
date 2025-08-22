<?php
require_once __DIR__ . '/_auth.php';
send_common_headers();

function jp_status(string $s): string {
  switch($s){
    case 'pending':
    case 'confirmed': return '受付中';
    case 'preparing': return 'ご用意しています';
    case 'ready': return 'お渡しできます';
    case 'delivered': return 'お渡し済';
    case 'picked_up': return 'お渡し済';
    case 'canceled': return 'キャンセル';
    default: return $s;
  }
}

staff_require_login();

// 遷移元（一覧 or 完了）を推定
$originTab = 'list';
if (isset($_GET['from']) && $_GET['from'] === 'done') {
  $originTab = 'done';
} else {
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref && strpos($ref, 'tab=done') !== false) {
    $originTab = 'done';
  }
}
$backUrl = '/staff/index.php' . ($originTab === 'done' ? '?tab=done' : '?tab=list');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- 1) 入力検証 ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/staff/assets/style.css">';
  echo '<main class="container"><div class="notice">不正なIDです。</div><p><a class="button btn-muted" href="/staff/index.php">一覧へ戻る</a></p></main>';
  exit;
}

// --- 2) スキーマに自動追従するための列名解決 ---
$ordersCols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll();
$ocols = array_column($ordersCols, 'Field');

$colCreated = in_array('created_at', $ocols, true) ? 'created_at' : (in_array('order_date', $ocols, true) ? 'order_date' : null);
$colPickup  = in_array('pickup_at', $ocols, true) ? 'pickup_at' : (in_array('pickup_slot', $ocols, true) ? 'pickup_slot' : (in_array('pickup', $ocols, true) ? 'pickup' : null));
$colAmount  = in_array('amount', $ocols, true) ? 'amount' : (in_array('amount_yen', $ocols, true) ? 'amount_yen' : null);
$colTotal   = in_array('total_qty', $ocols, true) ? 'total_qty' : (in_array('items_total', $ocols, true) ? 'items_total' : null);

// --- 3) 注文取得（存在する列だけSELECT） ---
$select = "id, status";
if ($colCreated) $select .= ", {$colCreated} AS created_at";
if ($colPickup)  $select .= ", {$colPickup}  AS pickup_at";
if ($colAmount)  $select .= ", {$colAmount}  AS amount";
if ($colTotal)   $select .= ", {$colTotal}   AS total_qty";

$st = $pdo->prepare("SELECT {$select} FROM orders WHERE id = ?");
$st->execute([$id]);
$order = $st->fetch();

if (!$order) {
  http_response_code(404);
  echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/staff/assets/style.css">';
  echo '<main class="container"><div class="notice">注文が見つかりません (#'.h($id).')</div><p><a class="button btn-muted" href="/staff/index.php">一覧へ戻る</a></p></main>';
  exit;
}

// --- 4) 表示時の自動遷移：pending/confirmed -> preparing ---
try {
  if (in_array($order['status'], ['pending','confirmed'], true)) {
    $pdo->beginTransaction();
    $cur = $order['status'];
    $up = $pdo->prepare("UPDATE orders SET status='preparing' WHERE id=? AND status IN ('pending','confirmed')");
    $up->execute([$id]);
    // ログ（order_status_logs が無い場合はスキップ）
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NOT NULL,
        from_status VARCHAR(32) NOT NULL,
        to_status VARCHAR(32) NOT NULL,
        changed_by VARCHAR(64) NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id)
      )");
      $log = $pdo->prepare("INSERT INTO order_status_logs(order_id, from_status, to_status, changed_by) VALUES(?, ?, 'preparing', 'staff')");
      $log->execute([$id, $cur]);
    } catch (Throwable $e) { /* ログ失敗は致命的ではないため握りつぶす */ }
    $pdo->commit();
    $order['status'] = 'preparing';
  }
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // 失敗しても詳細表示は続行（現状ステータスのまま）
}

// --- 5) 注文明細の列名解決 ---
$oiCols = $pdo->query("SHOW COLUMNS FROM order_items")->fetchAll();
$icols = array_column($oiCols, 'Field');

$colQty   = in_array('qty', $icols, true) ? 'qty' : (in_array('quantity', $icols, true) ? 'quantity' : null);
$colPrice = in_array('unit_price', $icols, true) ? 'unit_price' : (in_array('price_yen', $icols, true) ? 'price_yen' : (in_array('price', $icols, true) ? 'price' : null));
$colProd  = in_array('product_id', $icols, true) ? 'product_id' : null;

// products.name があればJOIN、無ければ product_id のみ
$hasProducts = false;
try {
  $pCols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
  $pnames = array_column($pCols, 'Field');
  $hasProducts = in_array('id', $pnames, true) && in_array('name', $pnames, true);
} catch (Throwable $e) {
  $hasProducts = false;
}

$items = [];
try {
  if ($colProd && $colQty) {
    if ($hasProducts) {
      $sel = "oi.{$colProd} AS product_id, p.name AS name, oi.{$colQty} AS qty";
      if ($colPrice) $sel .= ", oi.{$colPrice} AS unit_price";
      $sql = "SELECT {$sel} FROM order_items oi JOIN products p ON p.id = oi.{$colProd} WHERE oi.order_id = ?";
    } else {
      $sel = "oi.{$colProd} AS product_id, oi.{$colQty} AS qty";
      if ($colPrice) $sel .= ", oi.{$colPrice} AS unit_price";
      $sql = "SELECT {$sel} FROM order_items oi WHERE oi.order_id = ?";
    }
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $items = $st->fetchAll();
  }
} catch (Throwable $e) {
  // 明細取得失敗時は空配列のまま
}

?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/staff/assets/style.css">
<title>注文詳細 #<?= (int)$order['id'] ?></title>

<header class="header">
  <div class="container">
    <div class="brand">注文詳細 #<?= (int)$order['id'] ?></div>
  </div>
</header>

<main class="container">
  <p>受付時刻：<?= h($order['created_at'] ?? '') ?> / 合計：¥<?= isset($order['amount']) ? number_format((int)$order['amount']) : '-' ?> / 点数：<?= isset($order['total_qty']) ? (int)$order['total_qty'] : '-' ?></p>
  <p>ステータス：<strong><?= h(jp_status((string)$order['status'])) ?></strong></p>
  <?php if (!empty($order['pickup_at'])): ?>
    <p>お受け取り：<?= h($order['pickup_at']) ?></p>
  <?php endif; ?>

  <table>
    <thead><tr><th>商品</th><th>数量</th><th>単価</th><th>小計</th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4">明細がありません。</td></tr>
      <?php else: ?>
        <?php foreach ($items as $it): 
          $name = isset($it['name']) ? $it['name'] : ('商品ID:'.h($it['product_id'] ?? ''));
          $qty  = (int)($it['qty'] ?? 0);
          $unit = isset($it['unit_price']) ? (int)$it['unit_price'] : null;
          $sub  = is_null($unit) ? null : $unit * $qty;
        ?>
        <tr>
          <td><?= h($name) ?></td>
          <td><?= $qty ?></td>
          <td><?= is_null($unit) ? '-' : '¥'.number_format($unit) ?></td>
          <td><?= is_null($sub) ? '-' : '¥'.number_format($sub) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

<?php
  // 完了系（画面操作不可とするステータス）
  $doneSet = ['picked_up','delivered','completed','complete','done','finished','closed','canceled'];
  $showActions = ($originTab !== 'done') && !in_array((string)$order['status'], $doneSet, true);
?>
<?php if ($showActions): ?>
  <form method="post" action="../api/status.php" style="margin-top:16px;">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
    <button class="button" name="to" value="ready">完了（お渡しできますへ）</button>
    <button class="button btn-muted" name="to" value="canceled" style="margin-left:8px;">キャンセル</button>
  </form>
<?php endif; ?>

  <p style="margin-top:12px;"><a class="button btn-muted" href="<?= h($backUrl) ?>">一覧へ戻る</a></p>
</main>
