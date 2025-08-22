<?php
require_once __DIR__ . '/_auth.php';
require_login();
require_once __DIR__ . '/_common.php';
csrf_check();

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

  if(!empty($_FILES['image']['name']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){
    $saved=validateAndSaveImage($_FILES['image']);
    if($saved){
      if($new_image_path && $new_image_path!==$saved){
        safeUnlinkPublicPath($new_image_path);
      }
      $new_image_path=$saved;
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
  echo 'エラー：'.htmlspecialchars($e->getMessage());
}
