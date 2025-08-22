<?php
require_once __DIR__ . '/_auth.php';
require_login();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $cur=$_POST['current']??''; $new=$_POST['new']??''; $re=$_POST['re']??'';
  if ($new!==$re) { $msg='新しいパスワードが一致しません'; }
  elseif (strlen($new)<10) { $msg='パスワードは10文字以上にしてください'; }
  else {
    $st=$pdo->prepare('SELECT * FROM admin_users WHERE id=?');
    $st->execute([$_SESSION['admin']['id']]); $me=$st->fetch(PDO::FETCH_ASSOC);
    if(!$me || !password_verify($cur,$me['password_hash'])){ $msg='現在のパスワードが違います'; }
    else{
      $hash=password_hash($new,PASSWORD_DEFAULT);
      $upd=$pdo->prepare('UPDATE admin_users SET password_hash=? WHERE id=?');
      $upd->execute([$hash,$me['id']]); $msg='更新しました';
    }
  }
}

render_header('パスワード変更'); ?>
<h1>パスワード変更</h1>
<p><a class="btn secondary" href="/admin/products_list.php">← 戻る</a></p>
<?php if(!empty($msg)) echo '<div class="alert ok">'.htmlspecialchars($msg).'</div>'; ?>
<form method="post" style="max-width:420px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <label>現在のパスワード</label>
  <input type="password" name="current" required>
  <label>新しいパスワード</label>
  <input type="password" name="new" required>
  <label>新パスワード（確認）</label>
  <input type="password" name="re" required>
  <div class="actions" style="margin-top:12px;"><button>変更する</button></div>
</form>
<?php render_footer(); ?>
