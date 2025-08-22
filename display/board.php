<?php declare(strict_types=1); ?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/display/assets/style.css">
<title>注文状況｜店内ディスプレイ</title>

<header class="board-header">
  <div>
    <div class="brand">注文状況</div>
    <div class="sub">自動更新・「お渡しできます」に到達でサウンド</div>
  </div>
  <div class="right">
    <div class="clock" id="clock">--:--</div>
    <div class="updated" id="updated">更新: --:--</div>
    <button class="button ghost" id="fsBtn" type="button">全画面</button>
  </div>
</header>

<main class="board">
  <section class="col"><h2>受付中</h2><div id="col_pending" class="cards"></div></section>
  <section class="col"><h2>ご用意しています</h2><div id="col_preparing" class="cards"></div></section>
  <section class="col"><h2>お渡しできます</h2><div id="col_ready" class="cards"></div></section>
</main>

<audio id="sfx" src="/staff/assets/order_notify.mp3" preload="auto"></audio>

<script>
// 表示用ラベル
const LABELS = { pending:'受付中', confirmed:'受付中', preparing:'ご用意しています', ready:'お渡しできます' };

// DOM取得
const DOM = {
  pending: document.getElementById('col_pending'),
  preparing: document.getElementById('col_preparing'),
  ready: document.getElementById('col_ready'),
  clock: document.getElementById('clock'),
  updated: document.getElementById('updated'),
  sfx: document.getElementById('sfx'),
  fsBtn: document.getElementById('fsBtn'),
};

// 時計表示
const z = n => String(n).padStart(2,'0');
const fmtTime = d => `${z(d.getHours())}:${z(d.getMinutes())}`;
setInterval(()=>{ DOM.clock.textContent = fmtTime(new Date()); }, 1000);
DOM.clock.textContent = fmtTime(new Date());

// 番号は「ハイフンの右・数字のみの下4桁」を表示
function extract4(r){
  const raw = (r.order_no ?? String(r.id)) + '';
  const afterHyphen = raw.split('-').pop();
  const digits = String(afterHyphen).replace(/\D+/g,'');
  if (!digits) return String(r.id).slice(-4).padStart(4,'0');
  return digits.slice(-4).padStart(4,'0');
}

// ★ ご提示の group() をそのまま利用（status正規化＋受付時刻昇順）
function group(rows){
  const P=[], PREP=[], READY=[];
  for (const r of (rows||[])) {
    const st = String(r.status ?? '').toLowerCase().trim();
    switch (st) {
      case 'pending':
      case 'confirmed':
        P.push(r); break;
      case 'preparing':
        PREP.push(r); break;
      case 'ready':
        READY.push(r); break;
      default:
        // 表示対象外（picked_up/delivered/canceled 等）は無視
        ;
    }
  }
  const asc = (a,b)=> String(a.created_at||'').localeCompare(String(b.created_at||''));
  P.sort(asc); PREP.sort(asc); READY.sort(asc);
  return {P, PREP, READY};
}

// カード描画
function renderCards(el, rows){
  if (!Array.isArray(rows) || rows.length===0) { el.innerHTML = '<div class="empty">—</div>'; return; }
  let html = '';
  for (const r of rows) {
    const code4 = extract4(r);
    const qty = (r.total_qty ?? '') ? `<span class="qty">${r.total_qty}点</span>` : '';
    html += `<div class="card b-${(r.status||'').toLowerCase().trim()}">
      <div class="primary">${code4}</div>
      <div class="meta">
        <span class="status">${LABELS[(r.status||'').toLowerCase().trim()] ?? r.status}</span>
        ${qty}
        ${r.created_at ? `<span class="time">${(r.created_at||'').slice(11,16)}</span>` : ''}
      </div>
    </div>`;
  }
  el.innerHTML = html;
}

// サウンド（ready 新規到達時のみ）
let readySeen = new Set();
let unlocked = false;
document.addEventListener('pointerdown', async () => {
  try { await DOM.sfx.play(); DOM.sfx.pause(); DOM.sfx.currentTime = 0; unlocked = true; } catch(_) {}
}, { passive:true });
function playReadySound(){ if (!unlocked) return; try { DOM.sfx.currentTime = 0; DOM.sfx.play().catch(()=>{}); } catch(_) {} }

// API+更新
async function fetchList(){
  // タイムアウト付きfetch（8秒）
  if (typeof AbortController !== 'undefined') {
    const c = new AbortController(); const t = setTimeout(()=>c.abort(), 8000);
    try {
      const res = await fetch('/display/orders_public.php', { cache:'no-store', credentials:'include', signal:c.signal });
      if (!res.ok) throw new Error('HTTP '+res.status);
      return await res.json();
    } finally { clearTimeout(t); }
  } else {
    const raced = await Promise.race([
      fetch('/display/orders_public.php', { cache:'no-store', credentials:'include' }),
      new Promise((_,rej)=>setTimeout(()=>rej(new Error('Timeout')), 8000))
    ]);
    if (!raced.ok) throw new Error('HTTP '+raced.status);
    return await raced.json();
  }
}

// ready新規検出→サウンド
function diffReadySound(current){
  const now = new Set(current.map(r=>Number(r.id)));
  let newComer = false;
  for (const id of now) if (!readySeen.has(id)) { newComer = true; break; }
  readySeen = now;
  if (newComer) playReadySound();
}

// 定期更新
async function tick(){
  try {
    const data = await fetchList();
    const rows = Array.isArray(data.rows) ? data.rows : [];
    const g = group(rows);
    renderCards(DOM.pending, g.P);
    renderCards(DOM.preparing, g.PREP);
    renderCards(DOM.ready, g.READY);

    if (readySeen.size === 0) {
      // 初回は鳴らさない（既存readyを基準化）
      readySeen = new Set(g.READY.map(r=>Number(r.id)));
    } else {
      diffReadySound(g.READY);
    }
    DOM.updated.textContent = '更新: ' + fmtTime(new Date());
    DOM.updated.style.color = '';
  } catch(e){
    DOM.updated.textContent = '更新失敗';
    DOM.updated.style.color = '#ef4444';
  }
}
tick();
setInterval(tick, 5000);

// フルスクリーン切替
DOM.fsBtn?.addEventListener('click', ()=>{
  const el = document.documentElement;
  if (!document.fullscreenElement) { el.requestFullscreen?.(); DOM.fsBtn.textContent = '全画面解除'; }
  else { document.exitFullscreen?.(); DOM.fsBtn.textContent = '全画面'; }
});
</script>