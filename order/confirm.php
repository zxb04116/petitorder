<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$qty = $_POST['qty'] ?? [];
// 数量1以上の product_id を抽出
$ids = [];
foreach ($qty as $pid => $q) {
  $q = (int)$q;
  if ($q >= 1) $ids[(int)$pid] = $q;
}

if (!$ids) {
  header('Location: /order/index.php'); exit;
}

// DBから対象商品を取得（価格はサーバー側で確定）
$in  = implode(',', array_fill(0, count($ids), '?'));
$st  = $pdo->prepare("SELECT id,name,price_yen,image_path FROM products WHERE id IN ($in) AND status='selling' ORDER BY display_order,id");
$st->execute(array_keys($ids));
$items = $st->fetchAll();

// 再紐付け
$lines = [];
$total_qty = 0;
$total_amount = 0;
foreach ($items as $p) {
  $q = $ids[(int)$p['id']] ?? 0;
  if ($q <= 0) continue;
  $line = [
    'id'    => (int)$p['id'],
    'name'  => $p['name'],
    'price' => (int)$p['price_yen'],
    'qty'   => (int)$q,
    'image' => $p['image_path'],
    'sub'   => (int)$p['price_yen'] * (int)$q,
  ];
  $total_qty += $line['qty'];
  $total_amount += $line['sub'];
  $lines[] = $line;
}

if (!$lines) { header('Location: /order/index.php'); exit; }

// セッションに一時保存（完了画面で使用）
$_SESSION['order_preview'] = [
  'lines' => $lines,
  'total_qty' => $total_qty,
  'total_amount' => $total_amount,
];

// 表示
?>
<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/order/assets/styles.css">
<title>ご注文内容の確認</title>

<header class="header"><div class="container"><div class="brand">ご注文内容の確認</div></div></header>
<main class="container">
  <table class="table">
    <tr><th>商品</th><th>数量</th><th>単価</th><th>小計</th></tr>
    <?php foreach($lines as $l): ?>
      <tr>
        <td>
          <?php if ($l['image']): ?><img src="<?= htmlspecialchars($l['image']) ?>" alt="" style="height:50px;vertical-align:middle;border-radius:8px;margin-right:8px;"><?php endif; ?>
          <?= htmlspecialchars($l['name']) ?>
        </td>
        <td><?= (int)$l['qty'] ?></td>
        <td>¥<?= number_format((int)$l['price']) ?></td>
        <td>¥<?= number_format((int)$l['sub']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="total">合計：¥<?= number_format($total_amount) ?>（合計数量：<?= (int)$total_qty ?>）</div>

  <form method="post" action="/order/complete.php" style="margin-top:16px;">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <label>受取時間（任意）：</label>
    <input type="time" name="pickup_slot" style="padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;">
    <div class="row-actions" style="margin-top:12px;">
      <a class="button btn-secondary" href="/order/index.php">← 戻る</a>
      <button type="submit">この内容で注文する</button>
    </div>
  </form>
</main>
