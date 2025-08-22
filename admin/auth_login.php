<?php
require_once __DIR__ . '/_layout.php';
render_header('管理ログイン'); ?>
<h1>管理ログイン</h1>
<?php if (!empty($_GET['e'])): ?>
  <div class="alert err"><?= htmlspecialchars($_GET['e']) ?></div>
<?php endif; ?>
<form method="post" action="/admin/auth_do_login.php" style="max-width:420px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <label>ユーザーID</label>
  <input name="username" required>
  <label>パスワード</label>
  <input type="password" name="password" required>
  <div class="actions" style="margin-top:12px;">
    <button type="submit">ログイン</button>
  </div>
</form>
<?php render_footer(); ?>
