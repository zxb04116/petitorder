<?php
require_once __DIR__ . '/_auth.php';
require_login();
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$id=(int)($_GET['id']??0);
$row=['id'=>0,'name'=>'','price_yen'=>0,'image_path'=>null,'status'=>'selling','display_order'=>1000];
if($id){
  $st=$pdo->prepare("SELECT * FROM products WHERE id=?");
  $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row){ http_response_code(404); exit('Not Found'); }
}

render_header($id?'商品編集':'商品新規'); ?>
<div class="card">
  <div class="actions" style="margin-bottom:12px;">
    <a href="/admin/products_list.php" class="btn secondary">← 一覧へ戻る</a>
  </div>

  <form action="/admin/products_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

    <label>商品名</label>
    <input name="name" value="<?= htmlspecialchars($row['name']) ?>" required>

    <label>価格（税込）</label>
    <input type="number" name="price_yen" value="<?= (int)$row['price_yen'] ?>" required>

    <label>表示順</label>
    <input type="number" name="display_order" value="<?= (int)$row['display_order'] ?>">

    <label>状態</label>
    <select name="status">
      <option value="selling" <?= $row['status']==='selling'?'selected':''; ?>>販売中</option>
      <option value="ended"   <?= $row['status']==='ended'  ?'selected':''; ?>>販売終了</option>
    </select>

    <div style="margin-top:12px;">
      <label>現在の画像</label>
      <div>
        <?php if(!empty($row['image_path'])): ?>
          <img class="thumb" style="height:120px" src="<?= htmlspecialchars($row['image_path']) ?>"><br>
          <label><input type="checkbox" name="delete_image" value="1"> 画像を削除する</label>
        <?php else: ?>
          <em>登録なし</em>
        <?php endif; ?>
      </div>
    </div>

    <label style="margin-top:12px;">画像を選択（差し替え）</label>
    <input type="file" name="image" accept="image/*">
    <p class="muted"><small>対応拡張子：jpg, jpeg, png, webp</small></p>

    <div class="actions" style="margin-top:12px;">
      <button type="submit"><?= $id?'更新':'登録' ?></button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
