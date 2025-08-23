<?php
mb_internal_encoding('UTF-8');
ini_set('session.use_strict_mode', '1');
session_name('cake_admin');
session_start();

// 共通セキュリティヘッダ
function send_common_headers(): void {
  if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
  }
}

$pdo = new PDO(
  'mysql:host=localhost;dbname=cake_shop;charset=utf8mb4', // ←DB名
  'cake_user',                                                // ←ユーザー
  'wor]eaSy',                                                // ←パスワード
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// CSRF
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['csrf'] ?? '';
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
      http_response_code(400); exit('CSRFトークン不正');
    }
  }
}
