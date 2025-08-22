<!-- TEST -->
<?php
require_once __DIR__ . '/_bootstrap.php';
send_common_headers();

$draft = $_SESSION['draft_qty'] ?? [];

$rows = $pdo->query("SELECT id,name,price_yen,image_path FROM products WHERE status='selling' ORDER BY display_order,id")->fetchAll();
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/order/assets/styles.css">
<title>プチフール｜商品のご注文</title>

<header class="header">
  <div class="container">
    <div class="brand">プチフール｜商品のご注文</div>
  </div>
</header>

<main class="container">
  <form id="orderForm" method="post" action="/order/confirm.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="grid">
      <?php foreach($rows as $p): $pid=(int)$p['id']; ?>
      <div class="card" data-pid="<?= $pid ?>">
        <?php if (!empty($p['image_path'])): ?>
          <img src="<?= htmlspecialchars($p['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <div class="card-body">
          <h3><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <div class="price">¥<?= number_format((int)$p['price_yen']) ?></div>

          <!-- 数量コントローラ（±ボタン＋数値入力） -->
          <div class="qty">
            <label for="qty-<?= $pid ?>">数量</label>
            <div style="display:flex; gap:8px; align-items:center;">
              <button type="button" class="btn btn-secondary" data-dec data-pid="<?= $pid ?>" aria-label="数量を1減らす" style="padding:10px 14px;">−</button>
              <input type="number" class="qty-input" id="qty-<?= $pid ?>" name="qty[<?= $pid ?>]" value="<?= isset($draft[$pid]) ? (int)$draft[$pid] : 0 ?>" min="0" max="99" step="1" inputmode="numeric" pattern="\d*" style="text-align:center; max-width:100px;">
              <button type="button" class="button" data-inc data-pid="<?= $pid ?>" aria-label="数量を1増やす" style="padding:10px 14px;">＋</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </form>
</main>

<!-- 画面下 固定アクションバー -->
<div class="action-bar">
  <div class="summary"><span id="summaryText">数量を入力してください</span></div>
  <div class="primary">
    <!-- 文字を大きめに -->
    <button form="orderForm" type="submit" class="button" style="font-size:18px; padding:16px 20px;">
      注文内容の確認
    </button>
  </div>
</div>

<script>
// 数量の±制御と合計点数の簡易表示（フロント側）
(function(){
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
  const maxQty = 99; // サーバー側も同じ上限で検証

  function setQty(pid, next){
    const input = document.getElementById('qty-'+pid);
    if(!input) return;
    const v = parseInt(input.value || '0', 10) || 0;
    let nv = typeof next === 'number' ? next : v;
    nv = clamp(nv, 0, maxQty);
    if(nv !== v){ input.value = nv; }
    updateSummary();
  }

  function updateSummary(){
    const inputs = document.querySelectorAll('.qty-input');
    let total = 0;
    inputs.forEach(i => { const n = parseInt(i.value || '0', 10) || 0; total += n; });
    const s = document.getElementById('summaryText');
    s.textContent = total > 0 ? `合計 ${total} 点` : '数量を入力してください';
  }

  document.addEventListener('click', (e)=>{
    const incBtn = e.target.closest('[data-inc]');
    const decBtn = e.target.closest('[data-dec]');
    if(incBtn){
      const pid = incBtn.getAttribute('data-pid');
      const input = document.getElementById('qty-'+pid);
      const v = parseInt(input.value || '0', 10) || 0;
      setQty(pid, v+1);
    }
    if(decBtn){
      const pid = decBtn.getAttribute('data-pid');
      const input = document.getElementById('qty-'+pid);
      const v = parseInt(input.value || '0', 10) || 0;
      setQty(pid, v-1);
    }
  }, false);

  // 直接入力時もサマリ更新＆範囲クランプ
  document.addEventListener('input', (e)=>{
    const input = e.target;
    if(input.matches('.qty-input')){
      let v = parseInt(input.value || '0', 10);
      if(isNaN(v)) v = 0;
      v = clamp(v, 0, maxQty);
      if(String(v) !== input.value) input.value = v;
      updateSummary();
    }
  });

  // 初期表示
  updateSummary();

  // 送信前チェック：合計0なら送信しない
  const form = document.getElementById('orderForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      let total = 0;
      document.querySelectorAll('#orderForm .qty-input').forEach(el => {
        const v = parseInt(el.value || '0', 10);
        const n = isNaN(v) ? 0 : Math.max(0, Math.min(99, v));
        el.value = String(n);
        total += n;
      });
      if (total < 1) {
        e.preventDefault();
        alert('商品を1点以上お選びください。');
      }
    });
  }
})();
</script>
