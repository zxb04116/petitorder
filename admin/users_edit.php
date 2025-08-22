<?php
require_once __DIR__ . '/_auth.php';
require_login(['owner']);
require_once __DIR__ . '/_layout.php';

$id=(int)($_GET['id']??0);
$row=['id'=>0,'username'=>'','role'=>'staff','is_active'=>1];
if($id){
  $st=$pdo->prepare('SELECT id,username,role,is_active FROM admin_users WHERE id=?');
  $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); exit('Not Found'); }
}

render_header($id?'ユーザー編集':'ユーザー新規'); ?>
<p><a class="btn secondary" href="/admin/users_list.php">← 一覧へ</a></p>
<form method="post" action="/admin/users_update.php" style="max-width:520px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
  <label>ユーザーID</label>
  <input name="username" value="<?= htmlspecialchars($row['username']) ?>" <?= $id?'readonly':'' ?> required>
  <label>権限</label>
  <select name="role">
    <option value="owner" <?= $row['role']==='owner'?'selected':'' ?>>owner</option>
    <option value="staff" <?= $row['role']==='staff'?'selected':'' ?>>staff</option>
  </select>
  <label>有効</label>
  <select name="is_active">
    <option value="1" <?= $row['is_active']?'selected':'' ?>>有効</option>
    <option value="0" <?= !$row['is_active']?'selected':'' ?>>無効</option>
  </select>
  <hr>
  <label>新パスワード（任意・10文字以上）</label>
  <input type="password" name="new_password">
  <div class="actions" style="margin-top:12px;"><button type="submit"><?= $id?'更新':'作成' ?></button></div>
</form>
<?php render_footer(); ?>
