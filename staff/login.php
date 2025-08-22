<?php
require_once __DIR__ . '/_bootstrap.php';
send_common_headers();

$err = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $user = trim((string)($_POST['user'] ?? ''));
  $pass = (string)($_POST['pass'] ?? '');

  try {
    $st = $pdo->prepare('SELECT id, username, password_hash, role, is_active, login_attempts, locked_until FROM admin_users WHERE username = ?');
    $st->execute([$user]);
    $u = $st->fetch();

    // ロック確認
    $now = new DateTimeImmutable('now');
    $lockedUntil = null;
    if ($u && !empty($u['locked_until'])) {
      $lockedUntil = new DateTimeImmutable($u['locked_until']);
    }
    if ($u && $lockedUntil && $lockedUntil > $now) {
      $err = 'アカウントが一時ロックされています。しばらくしてからお試しください。';
    } elseif ($u && (int)$u['is_active'] === 1 && password_verify($pass, $u['password_hash'])) {
      // 成功：試行回数クリア・最終ログイン更新
      $pdo->beginTransaction();
      $up = $pdo->prepare('UPDATE admin_users SET login_attempts=0, locked_until=NULL, last_login_at=NOW() WHERE id=?');
      $up->execute([(int)$u['id']]);
      $pdo->commit();

      $_SESSION['staff_logged_in'] = true;
      $_SESSION['staff_user_id']   = (int)$u['id'];
      $_SESSION['staff_username']  = $u['username'];
      $_SESSION['staff_role']      = $u['role'];
      header('Location: /staff/index.php'); exit;
    } else {
      // 失敗：カウントアップ＆ロック（5回で5分ロック）
      if ($u) {
        $attempts = (int)$u['login_attempts'] + 1;
        $lockSql = 'UPDATE admin_users SET login_attempts=?, locked_until=? WHERE id=?';
        $lockedUntilVal = null;
        if ($attempts >= 5) {
          $lockedUntilVal = (new DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s');
          $attempts = 0; // ロックと同時にカウンタリセット
        }
        $up = $pdo->prepare($lockSql);
        $up->execute([$attempts, $lockedUntilVal, (int)$u['id']]);
      }
      $err = 'ユーザー名またはパスワードが違います。';
    }
  } catch (Throwable $e) {
    http_response_code(500);
    $err = 'ログイン処理でエラーが発生しました。時間をおいて再度お試しください。';
  }
}
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/staff/assets/style.css">
<title>スタッフログイン</title>
<header class="header">
  <div class="container"><div class="brand">スタッフ｜ログイン</div></div>
</header>
<main class="container">
  <?php if ($err): ?><div class="notice"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <form class="form" method="post" action="/staff/login.php">
    <div style="margin-bottom:8px;">
      <label>ユーザー名</label><br>
      <input class="input" type="text" name="user" autocomplete="username" required>
    </div>
    <div style="margin-bottom:8px;">
      <label>パスワード</label><br>
      <input class="input" type="password" name="pass" autocomplete="current-password" required>
    </div>
    <div><button class="button" type="submit">ログイン</button></div>
  </form>
</main>
