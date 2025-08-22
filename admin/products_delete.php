<?php
require_once __DIR__ . '/_auth.php';
require_login(['owner']);
require_once __DIR__ . '/_common.php';

$id=(int)($_GET['id']??0);
if($id<=0){ http_response_code(400); exit('Bad Request'); }

$st=$pdo->prepare("SELECT image_path FROM products WHERE id=?");
$st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
if(!$row){ http_response_code(404); exit('Not Found'); }

safeUnlinkPublicPath($row['image_path']??null);

$st=$pdo->prepare("DELETE FROM products WHERE id=?");
$st->execute([$id]);

header('Location:/admin/products_list.php');
