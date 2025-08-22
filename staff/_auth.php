<?php
require_once __DIR__ . '/_bootstrap.php';

function staff_is_logged_in(): bool {
  return !empty($_SESSION['staff_logged_in']);
}
function staff_require_login(): void {
  if (!staff_is_logged_in()) {
    header('Location: /staff/login.php'); exit;
  }
}