<?php
require_once __DIR__ . '/_auth.php';
require_login(['owner']);

$id=(int)($_GET['id']??0);
if($id<=0){ http_response_code(400); exit('Bad Request'); }
if($id===($_SESSION['admin']['id']??-1)){ exit('自分自身は削除できません'); }

$st=$pdo->prepare('DELETE FROM admin_users WHERE id=?');
$st->execute([$id]);

header('Location:/admin/users_list.php');
