<?php
require_once __DIR__ . '/_auth.php';
require_login();
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$rows=$pdo->query("SELECT id,name,price_yen,image_path,status,display_order,created_at FROM products ORDER BY display_order,id")->fetchAll(PDO::FETCH_ASSOC);

render_header('商品一覧'); ?>
<div class="actions" style="margin-bottom:12px;">
  <a class="btn" href="/admin/products_edit.php">＋ 新規商品</a>
</div>
<table>
  <tr><th>ID</th><th>画像</th><th>商品名</th><th>価格</th><th>状態</th><th>表示順</th><th>操作</th></tr>
  <?php foreach($rows as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><?php if($r['image_path']): ?><img class="thumb" src="<?= htmlspecialchars($r['image_path']) ?>"><?php endif; ?></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td>¥<?= number_format((int)$r['price_yen']) ?></td>
    <td><?php
    // 状態を日本語に変換して表示
    if ($r['status'] === 'selling') {
        echo '販売中';
    } elseif ($r['status'] === 'ended') {
        echo '販売終了';
    } else {
        echo '不明';
    }
    ?></td>
    <td><?= (int)$r['display_order'] ?></td>
    <td class="actions">
      <a class="btn secondary" href="/admin/products_edit.php?id=<?= (int)$r['id'] ?>">編集</a>
      <a class="btn danger" href="/admin/products_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('削除しますか？画像も削除されます');">削除</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php render_footer(); ?>
