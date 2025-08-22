<?php
// /var/www/html/admin/check_password.php （作成後にブラウザで開く）
// 終わったら必ず削除！
$pdo = new PDO('mysql:host=localhost;dbname=cake_shop;charset=utf8mb4','cake_user','wor]eaSy',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$st = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username='admin'");
$st->execute();
$hash = $st->fetchColumn();
$pw = 'Admin@1234'; // ここで試したいパスワードを変えられます
echo "<pre>";
echo "hash: ", htmlspecialchars($hash), PHP_EOL;
echo "verify(Admin@1234): ", password_verify($pw, $hash) ? "TRUE" : "FALSE", PHP_EOL;
echo "</pre>";
