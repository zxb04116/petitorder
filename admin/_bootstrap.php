<?php
require_once __DIR__ . '/../env.php';
mb_internal_encoding('UTF-8');
ini_set('session.use_strict_mode', '1');
session_name('cake_admin');
session_start();

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'cake_shop';
$dbUser = getenv('DB_USER') ?: 'cake_user';
$dbPass = getenv('DB_PASS') ?: 'wor]eaSy';

$pdo = new PDO(
  sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName),
  $dbUser,
  $dbPass,
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
