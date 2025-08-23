<?php
// 実行後は必ずファイル削除してください
require_once __DIR__ . '/_bootstrap.php';
send_common_headers();

$hash = password_hash('Admin@1234', PASSWORD_DEFAULT); // ここで同一環境で新規生成
$st = $pdo->prepare("UPDATE admin_users
  SET password_hash = ?, login_attempts = 0, locked_until = NULL, is_active = 1, role='owner'
  WHERE username = 'admin'");
$st->execute([$hash]);

echo "OK: admin password reset to Admin@1234";
