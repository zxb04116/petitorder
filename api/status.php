<?php
declare(strict_types=1);

/** start output buffer to swallow any accidental output from includes */
if (!headers_sent()) { ob_start(); }
/** clear all buffers before redirecting */
function _clear_buffers(): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
}
/** robust logger for diagnosis (tries sys temp, then script dir; also mirrors to error_log) */
function _status_log($msg){
  $line = '['.date('Y-m-d H:i:s').'] '.$msg;
  // Prefer system temp dir
  $dir = sys_get_temp_dir();
  if (!is_dir($dir) || !is_writable($dir)) {
    $dir = __DIR__; // fallback to current script directory
  }
  $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'status_api.log';
  @file_put_contents($file, $line.PHP_EOL, FILE_APPEND);
  // Also write to PHP error log for environments where file writes are restricted
  @error_log('status_api: ' . $line);
}

require_once __DIR__ . '/_bootstrap.php';
_status_log('START method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));
// guard for includes above

// 共通ヘッダ & 認証
send_common_headers();
staff_require_login();

// POST以外は一覧へ（白画面防止）
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST') {
  _status_log('REJECT non-post');
  _clear_buffers();
  header('Location: /staff/index.php?err=method');
  exit;
}

// CSRF検証（_auth.php 側の実装に従う）
if (function_exists('csrf_check')) {
  try {
    csrf_check(); // hidden csrf を内部で検証する想定
  } catch (Throwable $e) {
    _status_log('CSRF error');
    _clear_buffers();
    header('Location: /staff/index.php?err=csrf');
    exit;
  }
}

// 入力
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$to      = isset($_POST['to']) ? (string)$_POST['to'] : '';
_status_log('INPUT order_id=' . var_export($orderId, true) . ' to=' . var_export($to, true) . ' post_keys=' . implode(',', array_keys($_POST)));

// ---- Normalize target status against DB ENUM options ----
try {
  $enumVals = [];
  $colInfo = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
  if ($colInfo && isset($colInfo['Type']) && stripos($colInfo['Type'], 'enum(') === 0) {
    // parse enum('a','b','c')
    $m = [];
    if (preg_match("/^enum\((.*)\)$/i", $colInfo['Type'], $m)) {
      $inner = $m[1];
      // split by ',' but strip quotes
      foreach (preg_split("/,\s*/", $inner) as $part) {
        $val = trim($part);
        $val = trim($val, "'\"");
        if ($val !== '') $enumVals[$val] = true;
      }
    }
  }
  if (!empty($enumVals)) {
    // allow British spelling
    if ($to === 'cancelled' && isset($enumVals['canceled'])) $to = 'canceled';
    // If 'delivered' is not supported, map to the closest semantic value if available
    if ($to === 'delivered' && !isset($enumVals['delivered'])) {
      foreach (['picked_up','completed','complete','done','finished','closed','ready'] as $alt) {
        if (isset($enumVals[$alt])) { $to = $alt; break; }
      }
    }
  }
  _status_log('NORMALIZED to=' . $to . ' enum=[' . implode(',', array_keys($enumVals)) . ']');
} catch (Throwable $e) {
  _status_log('ENUM CHECK FAILED: ' . $e->getMessage());
}

// 許容ステータス（画面＋DB列のENUMを統合）
$allowed = ['preparing','ready','delivered','picked_up','canceled'];
try {
  if (!empty($enumVals)) {
    $allowed = array_values(array_unique(array_merge($allowed, array_keys($enumVals))));
  }
} catch (Throwable $e) { /* ignore */ }
if ($orderId <= 0 || !in_array($to, $allowed, true)) {
  _status_log('BADREQ (order_id/to invalid)');
  _clear_buffers();
  header('Location: /staff/index.php?err=badreq');
  exit;
}

// DB接続（_bootstrap.php で生成された $pdo を利用）
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  _status_log('PDO missing');
  _clear_buffers();
  header('Location: /staff/index.php?err=pdo');
  exit;
}

try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('SET innodb_lock_wait_timeout=5');
  $pdo->beginTransaction();
  _status_log('TX begin');

  // 対象をロック取得
  $st = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
  $st->execute([$orderId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    _status_log('NOT FOUND id=' . $orderId);
    $pdo->rollBack();
    _clear_buffers();
    header('Location: /staff/index.php?err=404');
    exit;
  }
  $from = (string)$row['status'];
  _status_log('CURRENT from=' . $from . ' to=' . $to);

  // 遷移ルール（完了系の別名にも対応）
  $completeSet = ['delivered','picked_up','completed','complete','done','finished','closed'];
  $isToComplete = in_array($to, $completeSet, true);
  $isFromComplete = in_array($from, $completeSet, true);

  $ok = false;
  if ($from === $to) {
    $ok = true;
  } else {
    if ($isFromComplete) {
      $ok = false; // 完了・クローズからは変更不可
    } else {
      switch ($from) {
        case 'preparing':
          // ready / canceled / 完了系を許容
          $ok = ($to === 'ready' || $to === 'canceled' || $isToComplete);
          break;
        case 'ready':
          // canceled / 完了系を許容
          $ok = ($to === 'canceled' || $isToComplete);
          break;
        default:
          $ok = false;
      }
    }
  }

  if (!$ok) {
    _status_log('INVALID TRANSITION from=' . $from . ' to=' . $to);
    $pdo->rollBack();
    _clear_buffers();
    header('Location: /staff/index.php?err=transition');
    exit;
  }

  // 更新（updated_at 無しにも対応）
  if ($from !== $to) {
    _status_log('UPDATE TRY to=' . $to . ' id=' . $orderId);
    try {
      $up = $pdo->prepare('UPDATE orders SET status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
      $up->execute([$to, $orderId]);
    } catch (Throwable $e) {
      _status_log('UPDATE fallback (no updated_at)');
      $up = $pdo->prepare('UPDATE orders SET status=? WHERE id=?');
      $up->execute([$to, $orderId]);
      _status_log('UPDATED without updated_at');
    }
    _status_log('UPDATED ok');
  }

  $pdo->commit();
  _status_log('TX commit success');

  // 成功時：一覧へ戻る
  _status_log('SUCCESS redirect to index');
  _clear_buffers();
  header('Location: /staff/index.php');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  _status_log('EXCEPTION ' . $e->getMessage());
  // 失敗時も一覧へ（白画面回避）
  _clear_buffers();
  header('Location: /staff/index.php?err=500');
  exit;
}
