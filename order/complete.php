<?php
declare(strict_types=1);

/**
 * complete.php
 * - index.php -> confirm.php -> complete.php の最終登録処理
 * - スキーマ(schema.sql)に厳密に合わせた版
 *   orders: (id, order_no, order_date, pickup_slot, status, items_total, amount_yen, created_at)
 *   order_items: (id, order_id, product_id, qty, unit_price)
 */

require_once __DIR__ . '/_bootstrap.php'; // セッション/CSRF/$pdo/共通ヘッダ
send_common_headers();

// 開発中のみ（本番は 0 に戻す）
ini_set('display_errors', '1');
error_reporting(E_ALL);

// CSRF検証（POST）
if (function_exists('csrf_check')) {
    csrf_check();
}

// ---- 入力/セッションの取得 ----
$preview = $_SESSION['order_preview'] ?? null;
if (!$preview || empty($preview['lines']) || !is_array($preview['lines'])) {
    http_response_code(400);
    exit('確認画面の有効期限が切れました。最初からやり直してください。');
}

$lines         = $preview['lines'];            // 各行: id, name, price, qty, sub
$items_total   = (int)($preview['total_qty'] ?? 0);
$amount_yen    = (int)($preview['total_amount'] ?? 0);

// 受取時刻（任意）: '' or HH:MM or HH:MM:SS
$pickup_raw = trim($_POST['pickup_slot'] ?? '');

// 'HH:MM'→'HH:MM:00'、空/不正はnull
function normalize_time_or_null(string $t): ?string {
    if ($t === '') return null;
    if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d):(60|[0-5]\d)$/', $t)) return $t;
    if (preg_match('/^(2[0-3]|[01]\d)$/', $t)) return $t . ':00';
    if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $t)) return $t . ':00';
    return null;
}
$pickup_slot = normalize_time_or_null($pickup_raw);

// ---- DB登録 ----
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('DB接続が初期化されていません（$pdo未定義）');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // 注文番号の採番（例: yymmdd-0001）
    $order_date = date('Y-m-d');

    $seqStmt = $pdo->prepare("
        SELECT LPAD(COALESCE(MAX(CAST(SUBSTRING(order_no, 8) AS UNSIGNED)), 0) + 1, 4, '0') AS seq
          FROM orders
         WHERE order_date = ?
    ");
    $seqStmt->execute([$order_date]);
    $seq = $seqStmt->fetchColumn();
    if (!$seq) $seq = '0001';
    $order_no = date('ymd', strtotime($order_date)) . '-' . $seq;

    // orders へINSERT（schema.sql 準拠）
    $sqlOrder = "
        INSERT INTO orders (
            order_no,
            order_date,
            pickup_slot,
            status,
            items_total,
            amount_yen,
            created_at
        ) VALUES (
            :order_no,
            :order_date,
            :pickup_slot,
            'confirmed',
            :items_total,
            :amount_yen,
            NOW()
        )
    ";
    $stmO = $pdo->prepare($sqlOrder);
    $stmO->bindValue(':order_no',   $order_no);
    $stmO->bindValue(':order_date', $order_date);
    if ($pickup_slot === null) {
        $stmO->bindValue(':pickup_slot', null, PDO::PARAM_NULL);
    } else {
        $stmO->bindValue(':pickup_slot', $pickup_slot);
    }
    $stmO->bindValue(':items_total', $items_total, PDO::PARAM_INT);
    $stmO->bindValue(':amount_yen',  $amount_yen,  PDO::PARAM_INT);
    $stmO->execute();
    $order_id = (int)$pdo->lastInsertId();

    // order_items へINSERT（line_amount 列は存在しない点に注意）
    $sqlItem = "
        INSERT INTO order_items (
            order_id,
            product_id,
            qty,
            unit_price
        ) VALUES (
            :order_id,
            :product_id,
            :qty,
            :unit_price
        )
    ";
    $stmI = $pdo->prepare($sqlItem);
    foreach ($lines as $row) {
        $pid  = (int)($row['id']    ?? 0);
        $qty  = (int)($row['qty']   ?? 0);
        $unit = (int)($row['price'] ?? 0);
        if ($pid <= 0 || $qty <= 0) {
            throw new InvalidArgumentException('注文明細の内容が不正です');
        }
        $stmI->execute([
            ':order_id'   => $order_id,
            ':product_id' => $pid,
            ':qty'        => $qty,
            ':unit_price' => $unit,
        ]);
    }

    $pdo->commit();

    // 一時データの破棄
    unset($_SESSION['order_preview']);

    // 完了画面（簡易）
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><meta charset='utf-8'>";
    echo "<h2>ご注文を受付けました</h2>";
    echo "<p>注文番号: " . htmlspecialchars($order_no, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<p>注文ID: " . htmlspecialchars((string)$order_id, ENT_QUOTES, 'UTF-8') . "</p>";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    // 開発用：詳細を画面表示（本番では外してください）
    echo "<pre style='color:#b91c1c; background:#fff1f2; padding:12px; border:1px solid #fecdd3; border-radius:8px; font-family:ui-monospace, SFMono-Regular, Menlo, monospace'>";
    echo "complete.php でエラーが発生しました\n\n";
    echo "Message\n" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n";
    echo "Trace\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "\n";
    echo "</pre>";
    exit;
}
