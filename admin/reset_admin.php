<?php
// 実行後は必ずファイル削除してください
$pdo = new PDO('mysql:host=localhost;dbname=cake_shop;charset=utf8mb4','cake_user','wor]eaSy',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$hash = password_hash('Admin@1234', PASSWORD_DEFAULT); // ここで同一環境で新規生成
$st = $pdo->prepare("UPDATE admin_users
  SET password_hash = ?, login_attempts = 0, locked_until = NULL, is_active = 1, role='owner'
  WHERE username = 'admin'");
$st->execute([$hash]);

echo "OK: admin password reset to Admin@1234";
