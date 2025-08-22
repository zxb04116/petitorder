<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

function active($path) {
  $cur = $_SERVER['REQUEST_URI'] ?? '';
  return (strpos($cur, $path) === 0) ? 'is-active' : '';
}

function render_header(string $title = '管理画面'): void { ?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="/admin/assets/styles.css">
</head>
<body>
<header class="site-header">
  <div class="brand">
    <a href="/admin/products_list.php" class="brand-link">洋菓子店 管理</a>
  </div>

  <?php if (!empty($_SESSION['admin'])): ?>
  <nav class="nav">
    <a class="nav-link <?= active('/admin/products_list.php') ?>" href="/admin/products_list.php">商品一覧</a>
    <a class="nav-link <?= active('/admin/products_edit.php') ?>" href="/admin/products_edit.php">新規商品</a>
    <a class="nav-link <?= active('/admin/account_password.php') ?>" href="/admin/account_password.php">パスワード変更</a>
    <?php if (($_SESSION['admin']['role'] ?? '') === 'owner'): ?>
      <a class="nav-link <?= active('/admin/users_list.php') ?>" href="/admin/users_list.php">ユーザー管理</a>
    <?php endif; ?>
    <a class="nav-link danger" href="/admin/auth_logout.php">ログアウト</a>
  </nav>
  <div class="whoami">
    <span class="chip"><?= htmlspecialchars($_SESSION['admin']['username']) ?></span>
    <span class="chip role"><?= htmlspecialchars($_SESSION['admin']['role']) ?></span>
  </div>
  <?php endif; ?>
</header>

<main class="container">
<?php }

function render_footer(): void { ?>
</main>
<footer class="site-footer">
  <small>&copy; <?= date('Y') ?> 洋菓子店 管理システム</small>
</footer>
</body>
</html>
<?php }
