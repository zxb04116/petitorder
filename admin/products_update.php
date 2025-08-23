<?php
require_once __DIR__ . '/_auth.php';
require_login();
require_once __DIR__ . '/_common.php';
csrf_check();

function uploadErrorMessage(int $code): string {
  // PHP official error codes mapping
  return match ($code) {
    UPLOAD_ERR_INI_SIZE   => 'アップロード失敗：ファイルサイズが大きすぎます（php.ini の upload_max_filesize を超過）',
    UPLOAD_ERR_FORM_SIZE  => 'アップロード失敗：ファイルサイズが大きすぎます（フォームの MAX_FILE_SIZE を超過）',
    UPLOAD_ERR_PARTIAL    => 'アップロード失敗：ファイルが途中で中断されました',
    UPLOAD_ERR_NO_FILE    => 'ファイルが選択されていません',
    UPLOAD_ERR_NO_TMP_DIR => 'アップロード失敗：一時ディレクトリが見つかりません（/tmp）',
    UPLOAD_ERR_CANT_WRITE => 'アップロード失敗：ディスクへの書き込みに失敗しました（権限・空き容量を確認）',
    UPLOAD_ERR_EXTENSION  => 'アップロード失敗：拡張によって停止しました',
    default               => 'アップロード失敗：不明なエラー（コード: '.$code.'）',
  };
}

$id=(int)($_POST['id']??0);
$name=trim($_POST['name']??'');
$price_yen=(int)($_POST['price_yen']??0);
$display_order=(int)($_POST['display_order']??1000);
$status=(($_POST['status']??'selling')==='ended')?'ended':'selling';
$delete_image=isset($_POST['delete_image']) && $_POST['delete_image']==='1';

if($name==='' || $price_yen<=0){ exit('入力値が不正です'); }

$old=null;
if($id){
  $st=$pdo->prepare("SELECT * FROM products WHERE id=?");
  $st->execute([$id]); $old=$st->fetch(PDO::FETCH_ASSOC);
  if(!$old){ http_response_code(404); exit('Not Found'); }
}

try{
  $pdo->beginTransaction();

  $new_image_path=$old['image_path']??null;

  if($delete_image && $new_image_path){
    safeUnlinkPublicPath($new_image_path);
    $new_image_path=null;
  }

  if (!empty($_FILES['image']['name'])) {
    $err = (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_OK) {
      $saved = validateAndSaveImage($_FILES['image']);
      if ($saved) {
        if ($new_image_path && $new_image_path !== $saved) {
          safeUnlinkPublicPath($new_image_path);
        }
        $new_image_path = $saved;
      } else {
        throw new RuntimeException('画像の保存に失敗しました（validateAndSaveImage が false を返却）');
      }
    } elseif ($err !== UPLOAD_ERR_NO_FILE) {
      // ファイルは指定されたが正常にアップロードされなかった
      throw new RuntimeException(uploadErrorMessage($err));
    }
  }

  if($id){
    $st=$pdo->prepare("UPDATE products SET name=?, price_yen=?, image_path=?, status=?, display_order=?, updated_at=NOW() WHERE id=?");
    $st->execute([$name,$price_yen,$new_image_path,$status,$display_order,$id]);
  }else{
    $st=$pdo->prepare("INSERT INTO products (name,price_yen,image_path,status,display_order) VALUES (?,?,?,?,?)");
    $st->execute([$name,$price_yen,$new_image_path,$status,$display_order]);
  }

  $pdo->commit();
  header('Location:/admin/products_list.php');
}catch(Throwable $e){
  $pdo->rollBack();
  http_response_code(500);
  error_log('[products_update] '.$e->getMessage());
  echo 'エラー：'.htmlspecialchars($e->getMessage());
}
