<?php
require_once __DIR__ . '/_bootstrap.php';
send_common_headers();
staff_require_login();
header('Content-Type: application/json; charset=utf-8');

$DEBUG = getenv('STAFF_API_DEBUG') === '1';
if ($DEBUG) {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  ini_set('log_errors', '1');
  ini_set('error_log', '/tmp/php_staff_api_errors.log');
}

try {
  $tab = $_GET['tab'] ?? 'list';

  // ordersテーブルに存在するカラムを調査
  $colsStmt = $pdo->query("SHOW COLUMNS FROM orders");
  $cols = [];
  foreach ($colsStmt->fetchAll() as $c) { $cols[] = $c['Field']; }

  // 列マッピング（存在する方を使う）
  $createdCol = in_array('created_at', $cols, true) ? 'created_at'
              : (in_array('order_date', $cols, true) ? 'order_date' : null);

  $amountCol  = in_array('amount_yen', $cols, true) ? 'amount_yen'
              : (in_array('amount', $cols, true) ? 'amount' : null);

  $qtyCol     = in_array('total_qty', $cols, true) ? 'total_qty'
              : (in_array('items_total', $cols, true) ? 'items_total' : null);

  // 注文番号は必須だが、名前揺れに対応（oder_no, order_number等）
  if (in_array('order_no', $cols, true)) {
    $orderNoCol = 'order_no';
  } elseif (in_array('oder_no', $cols, true)) {
    $orderNoCol = 'oder_no';
  } elseif (in_array('order_number', $cols, true)) {
    $orderNoCol = 'order_number';
  } else {
    $orderNoCol = null; // 最悪でもAPIが落ちないように
  }

  // SELECT句を組み立て（存在する列のみ）
  $select = "id, status";
  if ($createdCol) { $select .= ", {$createdCol} AS created_at"; }
  if ($amountCol)  { $select .= ", {$amountCol} AS amount"; }
  if ($qtyCol)     { $select .= ", {$qtyCol} AS total_qty"; }
  if ($orderNoCol) { $select .= ", {$orderNoCol} AS order_no"; }

  // WHERE句（orders.status の ENUM を参照しつつ、存在しない値は自動的に除外）
  $enumVals = [];
  try {
    $colInfo = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($colInfo && isset($colInfo['Type']) && stripos($colInfo['Type'], 'enum(') === 0) {
      if (preg_match("/^enum\\((.*)\\)$/i", $colInfo['Type'], $m)) {
        $inner = $m[1];
        foreach (preg_split("/,\\s*/", $inner) as $part) {
          $val = trim($part, " '\"");
          if ($val !== '') $enumVals[$val] = true;
        }
      }
    }
  } catch (Throwable $e) { /* ENUM 取得失敗時は空のまま（=自由文字列として扱う） */ }

  // 完了系・未完了系を候補化（スキーマに無い値は除外）
  $doneCandidates = ['delivered','picked_up','completed','complete','done','finished','closed','canceled'];
  $listCandidates = ['confirmed','pending','preparing','ready'];

  $filterByEnum = function(array $cands) use ($enumVals) {
    if (empty($enumVals)) return $cands; // enum でない場合はそのまま
    return array_values(array_filter($cands, function($s) use ($enumVals){ return isset($enumVals[$s]); }));
  };

  $doneStatuses = $filterByEnum($doneCandidates);
  $listStatuses = $filterByEnum($listCandidates);

  // どちらも空になってしまった場合のフォールバック
  if ($tab === 'done') {
    if (empty($doneStatuses)) $doneStatuses = ['picked_up','delivered','canceled'];
    $in = implode(',', array_map(fn($s) => $pdo->quote($s), $doneStatuses));
    $where = "status IN ($in)";
    // 当日のみ表示（完了タブ）。基準日は updated_at があればそれ、なければ created_at / order_date
    try {
      $colsStmt2 = $pdo->query("SHOW COLUMNS FROM orders");
      $cols2 = [];
      foreach ($colsStmt2->fetchAll() as $c2) { $cols2[] = $c2['Field']; }
      $updatedCol = in_array('updated_at', $cols2, true) ? 'updated_at' : null;
      $dateBase = $updatedCol ?: ($createdCol ?: null);
      if ($dateBase) {
        // サーバのローカル日付ベース。DBタイムゾーンがUTCの場合は CONVERT_TZ を検討
        $where .= " AND DATE({$dateBase}) = CURDATE()";
      }
    } catch (Throwable $e) { /* フィルタ付与失敗時は全件返す */ }
  } else {
    if (empty($listStatuses)) $listStatuses = ['confirmed','pending','preparing','ready'];
    $in = implode(',', array_map(fn($s) => $pdo->quote($s), $listStatuses));
    $where = "status IN ($in)";
  }

  // 並び順（受付時刻優先、昇順または降順）
  if ($tab === 'done') {
      $orderBy = $createdCol ? "{$createdCol} DESC, id DESC" : "id DESC";
  } else {
      $orderBy = $createdCol ? "{$createdCol} ASC, id ASC" : "id ASC";
  }

  // 実行
  $sql = "SELECT {$select} FROM orders WHERE {$where} ORDER BY {$orderBy} LIMIT 200";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // 補完と整形
  foreach ($rows as &$r) {
    if (!array_key_exists('created_at', $r)) $r['created_at'] = '';
    if (!array_key_exists('amount', $r))     $r['amount'] = null;
    if (!array_key_exists('total_qty', $r))  $r['total_qty'] = null;
    if (!array_key_exists('order_no', $r))   $r['order_no'] = '';

    // 注文番号は「ハイフンの右側の4桁」を表示用に整形（ハイフンが無い場合はそのまま）
    if ($r['order_no'] !== '' && strpos($r['order_no'], '-') !== false) {
      $parts = explode('-', $r['order_no']);
      $right = end($parts);
      $r['order_no'] = substr($right, -4);
    }
  }
  unset($r);

  echo json_encode(['rows' => $rows, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($DEBUG) {
    http_response_code(500);
    echo json_encode(['error' => 'server', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'server'], JSON_UNESCAPED_UNICODE);
  }
}