<?php
require_once __DIR__ . '/../env.php';
mb_internal_encoding('UTF-8');
ini_set('session.use_strict_mode', '1');
session_name('cake_order');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// 共通セキュリティヘッダ
function send_common_headers(): void {
  if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    // 必要に応じて調整してください（外部CDN等を使う場合）
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
  }
}

// CSRFユーティリティ
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $t = (string)($_POST['csrf'] ?? '');
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
      http_response_code(400);
      exit('CSRFトークン不正');
    }
  }
}

// PDO接続（環境変数優先、未設定時は従来値を使用）
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'cake_shop';
$dbUser = getenv('DB_USER') ?: 'cake_user';
$dbPass = getenv('DB_PASS') ?: 'wor]eaSy'; // 本番では必ず環境変数を設定してください

try {
  $pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName),
    $dbUser,
    $dbPass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  send_common_headers();
  echo '<!doctype html><meta charset="utf-8"><title>エラー</title>';
  echo '<h2>データベースに接続できませんでした</h2>';
  echo '<p>しばらくしてから再度お試しください。</p>';
  exit;
}
