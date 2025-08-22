<?php
require_once __DIR__ . '/_bootstrap.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('PUBLIC_PREFIX', '/uploads/products/');

function ensureUploadDir() {
  if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
}

function safeUnlinkPublicPath(?string $publicPath): void {
  if (!$publicPath) return;
  if (strpos($publicPath, PUBLIC_PREFIX) !== 0) return; // 想定外のパス拒否
  $real = realpath(UPLOAD_DIR . basename($publicPath));
  if ($real && strpos($real, realpath(UPLOAD_DIR)) === 0 && is_file($real)) {
    @unlink($real);
  }
}

function validateAndSaveImage(array $file): ?string {
  if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('画像アップロードに失敗');

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg','jpeg','png','webp'];
  if (!in_array($ext, $allowed, true)) throw new RuntimeException('許可されていない画像形式');

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);
  $okMime = ['image/jpeg','image/png','image/webp'];
  if (!in_array($mime, $okMime, true)) throw new RuntimeException('画像MIMEタイプ不正');

  ensureUploadDir();
  $filename = bin2hex(random_bytes(16)) . '.' . $ext;
  $target   = UPLOAD_DIR . $filename;
  if (!move_uploaded_file($file['tmp_name'], $target)) {
    throw new RuntimeException('画像保存に失敗');
  }
  return PUBLIC_PREFIX . $filename;
}
