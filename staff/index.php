<?php
require_once __DIR__ . '/_auth.php';
send_common_headers();
staff_require_login();
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/staff/assets/style.css">
<title>スタッフ｜注文管理</title>

<header class="header">
  <div class="container">
    <div class="brand">スタッフ｜注文管理</div>
  </div>
</header>

<main class="container">
  <p><a class="btn-muted button" href="/staff/logout.php">ログアウト</a></p>

  <div class="tabs" id="tabs" style="position:relative; z-index:1;">
    <a href="?tab=list" class="tab" data-tab="list" role="button" tabindex="0">注文一覧</a>
    <a href="?tab=done" class="tab" data-tab="done" role="button" tabindex="0">注文完了</a>
  </div>

  <!-- サウンド制御（固定領域） -->
  <div id="soundArea" style="margin:12px 0; position:relative; z-index:1;">
    <div id="soundNotice" class="notice" style="margin-bottom:8px;">
      新しい注文が届いたら音を鳴らします。初回は「サウンドテスト」で再生を許可してください。
    </div>
    <button class="button" id="soundTestBtn" type="button">サウンドテスト</button>
    <audio id="orderAudio" src="/staff/assets/order_notify.mp3" preload="auto"></audio>
  </div>

  <!-- エラーバナー -->
  <div id="errorBanner" class="notice" style="display:none; background:#fee2e2; border-color:#fecaca; color:#7f1d1d; margin-bottom:8px;"></div>

  <!-- 一覧 -->
  <div id="list"></div>
</main>
<script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script>
// ====== 定数・状態 ======
const listEl = document.getElementById('list');
const errorEl = document.getElementById('errorBanner');
const audioEl = document.getElementById('orderAudio');
const btnTest = document.getElementById('soundTestBtn');
const noticeEl = document.getElementById('soundNotice');
const STATUS_LABELS = { pending:'受付中', confirmed:'受付中', preparing:'ご用意しています', ready:'お渡しできます', delivered:'お渡し済', picked_up:'お渡し済', canceled:'キャンセル' };
// URLクエリで初期タブ選択（未定義だとJSが止まるため最初に定義）
const urlParams = new URLSearchParams(location.search);
let currentTab = (urlParams.get('tab') === 'done') ? 'done' : 'list';
document.querySelectorAll('.tab').forEach(el => el.classList.toggle('active', el.dataset.tab === currentTab));

// ---- Web Audio API（確実再生用のバッファ方式） ----
let audioCtx = null;
let audioBuffer = null;
let audioInitPromise = null;

async function initAudioBuffer(){
  if (audioBuffer) return audioBuffer;
  if (!audioInitPromise) {
    audioInitPromise = (async () => {
      try {
        // ユーザー操作後に呼ばれることを想定
        if (!audioCtx) {
          const Ctx = window.AudioContext || window.webkitAudioContext;
          if (!Ctx) throw new Error('AudioContext unsupported');
          audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
          try { await audioCtx.resume(); } catch(_) {}
        }
        const res = await fetch('/staff/assets/order_notify.mp3', { cache: 'reload', credentials: 'include' });
        const arr = await res.arrayBuffer();
        // decodeAudioData は Promise を返す実装を想定
        const buf = await audioCtx.decodeAudioData(arr);
        audioBuffer = buf;
        return audioBuffer;
      } catch (e) {
        // 失敗してもフォールバック（<audio>）があるので致命的ではない
        return null;
      }
    })();
  }
  return audioInitPromise;
}

function playBufferOnce(){
  try {
    if (!audioCtx || !audioBuffer) return false;
    if (audioCtx.state === 'suspended') {
      audioCtx.resume().catch(()=>{});
    }
    const src = audioCtx.createBufferSource();
    src.buffer = audioBuffer;
    src.connect(audioCtx.destination);
    src.start(0);
    return true;
  } catch(_) { return false; }
}

// ---- fetch ヘルパ：タイムアウト付き ----
async function fetchWithTimeout(url, options = {}, timeoutMs = 8000) {
  if (typeof AbortController !== 'undefined') {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeoutMs);
    try {
      return await fetch(url, { ...options, signal: controller.signal });
    } finally {
      clearTimeout(id);
    }
  } else {
    // Fallback: AbortController が無い環境用（キャンセルはできないが、タイムアウトで先に失敗させる）
    return await Promise.race([
      fetch(url, options),
      new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), timeoutMs))
    ]);
  }
}
// リトライ制御フラグ（1回だけ再試行）
let fetchRetryScheduled = false;

// ---- 予期せぬJSエラーの可視化 ----
function showClientError(msg){
  try {
    if (!errorEl) return;
    errorEl.style.display = 'block';
    errorEl.textContent = 'クライアントエラー: ' + msg;
  } catch(_) {}
}
window.addEventListener('error', (e) => {
  showClientError(e && e.message ? e.message : 'Script error');
});
window.addEventListener('unhandledrejection', (e) => {
  let msg = '';
  try {
    const r = e && e.reason; msg = r ? (r.message || String(r)) : 'Promise rejection';
  } catch(_) { msg = 'Promise rejection'; }
  showClientError(msg);
});

// このセッションでの再生許可フラグ（ブラウザの自動再生制限対策）
let sessionUnlocked = false;
let unlocked = false; // 先に宣言してTDZ/ReferenceErrorを回避
// 初回取得ではサウンドを鳴らさない（既存注文での誤作動防止）
let initialLoadDone = false;

// ユーザーの任意操作でサウンドをアンロック（同セッション内）
function attemptUnlockFromGesture(){
  if (sessionUnlocked || !audioEl) return;
  try {
    audioEl.currentTime = 0;
    audioEl.play().then(() => {
      setTimeout(() => { try { audioEl.pause(); audioEl.currentTime = 0; } catch(_) {} }, 80);
      sessionUnlocked = true;
      unlocked = true;
      try { localStorage.setItem('soundUnlocked','1'); } catch(_) {}
      if (noticeEl) noticeEl.style.display = 'none';
      if (btnTest) { btnTest.textContent = 'サウンドテスト完了'; btnTest.disabled = true; btnTest.classList.add('btn-muted'); }
    }).catch(() => {});
  } catch(_) {}
  // Web Audio バッファを準備（初回操作で許可を取る）
  initAudioBuffer().catch(()=>{});
}
// クリックやキー押下でアンロックを試みる（何度でも試す）
document.addEventListener('pointerdown', attemptUnlockFromGesture, { passive: true });
document.addEventListener('keydown', attemptUnlockFromGesture);

let seenIds = { list: new Set(), done: new Set() };

// ====== サウンドUI 初期化（未許可なら必ず押せる） ======
(function initSoundUI(){
  try { unlocked = (localStorage.getItem('soundUnlocked') === '1'); } catch(e){ unlocked = false; }
  if (unlocked) {
    if (noticeEl) noticeEl.style.display = 'none';
    if (btnTest) { btnTest.textContent = 'サウンドテスト完了'; btnTest.disabled = true; btnTest.classList.add('btn-muted'); }
  } else {
    if (btnTest) { btnTest.disabled = false; btnTest.textContent = 'サウンドテスト'; btnTest.classList.remove('btn-muted'); }
    if (noticeEl) noticeEl.style.display = 'block';
  }
})();

// ====== イベント委任（再描画後も有効） ======
document.addEventListener('click', async (e) => {
  // タブ切替
  const tab = e.target.closest('.tab');
  if (tab) {
    e.preventDefault();
    const next = tab.dataset.tab || 'list';
    if (currentTab !== next) {
      currentTab = next;
      document.querySelectorAll('.tab').forEach(el => el.classList.toggle('active', el === tab));
      if (!seenIds[currentTab]) seenIds[currentTab] = new Set();
      fetchList();
    }
    return;
  }

  // サウンドテスト
  if (e.target && e.target.id === 'soundTestBtn') {
    e.preventDefault();
    if (!audioEl) return;
    // Web Audio バッファ初期化＆試し再生
    try { await initAudioBuffer(); playBufferOnce(); } catch(_) {}
    try {
      audioEl.currentTime = 0;
      await audioEl.play();
      setTimeout(()=>{ try { audioEl.pause(); audioEl.currentTime = 0; } catch(e){} }, 200);
      unlocked = true;
      try { localStorage.setItem('soundUnlocked','1'); } catch(e){}
      e.target.textContent = 'サウンドテスト完了';
      e.target.disabled = true;
      e.target.classList.add('btn-muted');
      if (noticeEl) noticeEl.style.display = 'none';
    } catch (err) {
      alert('サウンドを再生できませんでした。\nブラウザや端末の設定（消音、音量、自動再生）をご確認ください。');
      console.warn('Sound test failed:', err);
    }
    return;
  }
});

function playOrderSound() {
  // まず Web Audio バッファでの再生を試みる
  if (audioBuffer) {
    if (playBufferOnce()) return;
  }
  // バッファ未作成なら非同期で準備だけ進める（後続の通知で確実化）
  initAudioBuffer().catch(()=>{});

  // フォールバック：<audio> 要素の再生（従来方式）
  if (!audioEl) return;
  if (sessionUnlocked) {
    try { audioEl.currentTime = 0; audioEl.play().catch(()=>{}); } catch(_) {}
    return;
  }
  if (unlocked) {
    try {
      audioEl.currentTime = 0;
      audioEl.play().then(() => { sessionUnlocked = true; }).catch(() => {});
    } catch(_) {}
    return;
  }
}

// ====== 描画 ======
function render(rows){
  if (!Array.isArray(rows)) rows = [];
  if (rows.length === 0) {
    listEl.innerHTML = '<p>該当する注文はありません。</p>';
    return;
  }
  let html = '<table><thead><tr><th>受付時刻</th><th>注文番号</th><th>点数</th><th>金額</th><th>ステータス</th><th>操作</th></tr></thead><tbody>';
  for (const r of rows) {
    const label = STATUS_LABELS[r.status] ?? r.status;
    const detailUrl = '/staff/detail.php?id=' + encodeURIComponent(r.id) + '&from=' + encodeURIComponent(currentTab);
    let ops = `<a class="button" href="${detailUrl}">詳細</a>`;
    if (currentTab === 'list' && r.status === 'ready') {
      ops += ` <form style="display:inline" method="post" action="/api/status.php" onsubmit="return confirm('注文 #${r.id} をお渡し済みにしますか？');">
        <input type="hidden" name="csrf" value="${window.csrf || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '')}">
        <input type="hidden" name="order_id" value="${r.id}">
        <button class="button btn-muted" name="to" value="delivered" type="submit">お渡し完了</button>
      </form>`;
    }
    // 任意でキャンセルボタン（listタブのみ、完了済/キャンセル済は出さない）
    if (currentTab === 'list' && r.status !== 'delivered' && r.status !== 'picked_up' && r.status !== 'canceled') {
      ops += ` <form style="display:inline" method="post" action="/api/status.php" onsubmit="return confirm('注文 #${r.id} をキャンセルしますか？');">
        <input type="hidden" name="csrf" value="${window.csrf || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '')}">
        <input type="hidden" name="order_id" value="${r.id}">
        <button class="button btn-danger" name="to" value="canceled" type="submit">キャンセル</button>
      </form>`;
    }

    html += `<tr>
      <td>${r.created_at ?? ''}</td>
      <td>${r.order_no}</td>
      <td>${r.total_qty ?? ''}</td>
      <td>¥${Number(r.amount ?? 0).toLocaleString()}</td>
      <td><span class="badge b-${r.status}">${label}</span></td>
      <td class="row-actions">${ops}</td>
    </tr>`;
  }
  html += '</tbody></table>';
  listEl.innerHTML = html;
}

// 新規注文（pending/confirmed）検知でサウンド
function detectNewOrders(rows){
  const suppressForInitial = !initialLoadDone;
  if (currentTab !== 'list') return;
  const set = seenIds.list || (seenIds.list = new Set());
  let hasNew = false;
  for (const r of rows) {
    const id = Number(r.id);
    if (!set.has(id)) {
      set.add(id);
      if (!suppressForInitial && (r.status === 'pending' || r.status === 'confirmed')) {
        hasNew = true;
      }
    }
  }
  if (hasNew) playOrderSound();
}

// ====== API ======
async function fetchList(){
  try {
    const res = await fetchWithTimeout(
      '/api/orders.php?tab=' + encodeURIComponent(currentTab),
      { cache: 'no-store', credentials: 'include' }, // Cookieを確実に送る
      8000
    );
    if (!res.ok) {
      errorEl.style.display = 'block';
      errorEl.textContent = '一覧の取得に失敗しました（HTTP ' + res.status + '）';
      if (!fetchRetryScheduled) {
        fetchRetryScheduled = true;
        setTimeout(() => { fetchRetryScheduled = false; fetchList(); }, 1000);
      }
      return;
    }
    const txt = await res.text();
    let data;
    try { data = JSON.parse(txt); }
    catch (e) {
      errorEl.style.display = 'block';
      errorEl.textContent = '一覧の取得に失敗しました（JSON解析エラー）';
      return;
    }
    errorEl.style.display = 'none';
    // CSRFはAPIが返さなくてもサーバ埋め込みをフォールバックで使用
    if (!window.csrf || !window.csrf.length) window.csrf = (data.csrf || '');
    if (!window.csrf || !window.csrf.length) window.csrf = (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    const rows = Array.isArray(data.rows) ? data.rows : [];
    detectNewOrders(rows);
    render(rows);
    // 初回は“最初にデータが出現したタイミング”で基準化する（空→次回で鳴ってしまう問題を回避）
    if (!initialLoadDone && rows.length > 0) {
      initialLoadDone = true;
    }
  } catch (e) {
    const name = (e && e.name) ? e.name : 'Error';
    const offlineNote = (typeof navigator !== 'undefined' && navigator.onLine === false) ? '（オフラインの可能性）' : '';
    errorEl.style.display = 'block';
    errorEl.textContent = '一覧の取得に失敗しました（通信エラー: ' + name + '）' + offlineNote;
    if (!fetchRetryScheduled) {
      fetchRetryScheduled = true;
      setTimeout(() => { fetchRetryScheduled = false; fetchList(); }, 1000);
    }
  }
}

// 初回＆定期更新
fetchList();
setInterval(fetchList, 5000);
</script>
