<?php
require_once __DIR__ . '/_auth.php';
require_login(['owner']);
csrf_check();

$id=(int)($_POST['id']??0);
$username=trim($_POST['username']??'');
$role=(($_POST['role']??'staff')==='owner')?'owner':'staff';
$is_active=(int)(($_POST['is_active']??'1')==='0'?0:1);
$new_password=$_POST['new_password']??'';

if($username===''){ exit('ユーザーIDは必須です'); }
if($id === ($_SESSION['admin']['id']??-1) && $is_active===0){ exit('自分自身を無効化できません'); }

if($id){
  if($new_password!==''){
    if(strlen($new_password)<10) exit('パスワードは10文字以上');
    $hash=password_hash($new_password,PASSWORD_DEFAULT);
    $st=$pdo->prepare('UPDATE admin_users SET role=?, is_active=?, password_hash=? WHERE id=?');
    $st->execute([$role,$is_active,$hash,$id]);
  }else{
    $st=$pdo->prepare('UPDATE admin_users SET role=?, is_active=? WHERE id=?');
    $st->execute([$role,$is_active,$id]);
  }
}else{
  if(strlen($new_password)<10) exit('新規作成にはパスワードが必要（10文字以上）');
  $hash=password_hash($new_password,PASSWORD_DEFAULT);
  $st=$pdo->prepare('INSERT INTO admin_users (username,password_hash,role,is_active) VALUES (?,?,?,?)');
  $st->execute([$username,$hash,$role,$is_active]);
}
header('Location:/admin/users_list.php');
