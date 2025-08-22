<?php
require_once __DIR__ . '/_auth.php';
require_login(['owner']);
require_once __DIR__ . '/_layout.php';

$rows=$pdo->query('SELECT id,username,role,is_active,last_login_at,created_at FROM admin_users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

render_header('ユーザー管理'); ?>
<h1>ユーザー管理</h1>
<p><a class="btn secondary" href="/admin/products_list.php">← 戻る</a> <a class="btn" href="/admin/users_edit.php">新規作成</a></p>
<table>
<tr><th>ID</th><th>ユーザーID</th><th>権限</th><th>有効</th><th>最終ログイン</th><th>操作</th></tr>
<?php foreach($rows as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['username']) ?></td>
  <td><?= htmlspecialchars($r['role']) ?></td>
  <td><?= $r['is_active']?'有効':'無効' ?></td>
  <td><?= htmlspecialchars($r['last_login_at']??'-') ?></td>
  <td class="actions">
    <a class="btn secondary" href="/admin/users_edit.php?id=<?= (int)$r['id'] ?>">編集</a>
    <?php if($r['id']!==$_SESSION['admin']['id']): ?>
      <a class="btn danger" href="/admin/users_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('削除しますか？');">削除</a>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
