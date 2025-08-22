<?php
require_once __DIR__ . '/_bootstrap.php';

function require_login(array $roles = []): void {
  if (empty($_SESSION['admin'])) {
    header('Location: /admin/auth_login.php');
    exit;
  }
  if ($roles) {
    if (!in_array($_SESSION['admin']['role'], $roles, true)) {
      http_response_code(403); exit('権限がありません');
    }
  }
}
