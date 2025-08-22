<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$u = trim($_POST['username'] ?? '');
$p = $_POST['password'] ?? '';

$st = $pdo->prepare('SELECT * FROM admin_users WHERE username=? AND is_active=1');
$st->execute([$u]);
$user = $st->fetch(PDO::FETCH_ASSOC);

$err = 'ユーザーIDまたはパスワードが不正です';
$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));

if (!$user) { header('Location:/admin/auth_login.php?e='.urlencode($err)); exit; }

if ($user['locked_until'] && $now < new DateTime($user['locked_until'])) {
  header('Location:/admin/auth_login.php?e='.urlencode('一時ロック中。しばらく後に')); exit;
}

if (!password_verify($p, $user['password_hash'])) {
  $attempts = (int)$user['login_attempts'] + 1;
  $locked = null;
  if ($attempts >= 5) { $locked = (clone $now)->modify('+5 minutes')->format('Y-m-d H:i:s'); $attempts = 0; }
  $upd = $pdo->prepare('UPDATE admin_users SET login_attempts=?, locked_until=? WHERE id=?');
  $upd->execute([$attempts, $locked, $user['id']]);
  header('Location:/admin/auth_login.php?e='.urlencode($err)); exit;
}

$upd = $pdo->prepare('UPDATE admin_users SET login_attempts=0, locked_until=NULL, last_login_at=? WHERE id=?');
$upd->execute([$now->format('Y-m-d H:i:s'), $user['id']]);

$_SESSION['admin'] = ['id'=>(int)$user['id'],'username'=>$user['username'],'role'=>$user['role']];
session_regenerate_id(true);

header('Location:/admin/products_list.php');
