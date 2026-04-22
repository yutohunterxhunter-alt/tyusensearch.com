<?php
/*
Template Name: その他抽選一覧
*/
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>その他 抽選情報一覧｜抽選サーチ</title>
<meta property="og:title" content="その他 抽選情報一覧｜抽選サーチ">
<meta property="og:description" content="その他の抽選情報を店舗別に一覧表示。締切間近の抽選をリアルタイムでチェック。">
<meta property="og:url" content="https://tyusensearch.com/other/">
<meta property="og:type" content="website">
<meta property="og:image" content="https://tyusensearch.com/ogp/ogp-other.jpg">
<meta name="twitter:card" content="summary_large_image">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{
  --bg:#0d1117;--s1:#161b22;--s2:#1c2128;--bd:#30363d;
  --tx:#e6edf3;--mu:#7d8590;
  --green:#3fb950;--red:#f85149;--yellow:#f5c518;--blue:#58a6ff;--orange:#f0883e;
  --font:'Zen Kaku Gothic New',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
body{background:var(--bg);color:var(--tx);font-family:var(--font);min-height:100vh;font-size:14px;padding-bottom:72px;}

/* ヘッダー */
.hd{background:var(--s1);border-bottom:1px solid var(--bd);padding:10px 14px;position:sticky;top:0;z-index:100;}
.hd-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.site-tag{font-size:9px;letter-spacing:2px;color:var(--yellow);border:1px solid var(--yellow);padding:2px 7px;border-radius:2px;text-decoration:none;}
.hd h1{font-size:16px;font-weight:900;}
.hd h1 span{color:var(--yellow);}

/* 検索バー */
.search-bar{display:flex;gap:8px;padding:10px 14px;background:var(--s1);border-bottom:1px solid var(--bd);}
.search-inp{flex:1;background:var(--s2);border:1px solid var(--bd);border-radius:8px;padding:9px 12px;color:var(--tx);font-size:13px;font-family:var(--font);outline:none;}
.search-inp:focus{border-color:var(--yellow);}
.search-inp::placeholder{color:var(--mu);}

/* フィルタバー */
.filter-bar{display:flex;gap:6px;padding:8px 14px;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid var(--bd);background:var(--s1);}
.filter-bar::-webkit-scrollbar{display:none;}
/* シリーズドロップダウン */
.th-store-btn{background:none;border:none;color:var(--mu);font-family:var(--font);font-size:10px;font-weight:900;letter-spacing:.5px;cursor:pointer;display:flex;align-items:center;gap:4px;padding:0;}
.th-store-btn:hover{color:var(--blue);}
.th-store-btn.active{color:var(--blue);}
.series-dropdown{position:absolute;top:100%;left:0;background:var(--s2);border:1px solid var(--bd);border-radius:8px;min-width:180px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.5);display:none;max-height:280px;overflow-y:auto;}
.series-dropdown.open{display:block;}
.series-opt{padding:10px 14px;font-size:13px;cursor:pointer;border-bottom:1px solid var(--bd);transition:background .1s;}
.series-opt:last-child{border-bottom:none;}
.series-opt:hover{background:var(--s1);}
.series-opt.active{color:var(--blue);font-weight:700;}
.th-series-wrap{position:relative;}
.f-btn{flex-shrink:0;padding:4px 12px;border-radius:20px;border:1px solid var(--bd);background:transparent;color:var(--mu);font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .15s;}
.f-btn.active{background:rgba(245,197,24,.12);border-color:var(--yellow);color:var(--yellow);}
.stat-wrap{margin-left:auto;display:flex;gap:10px;align-items:center;flex-shrink:0;}
.stat{font-size:11px;}
.stat b{font-weight:900;}
.stat-a{color:var(--yellow);}
.stat-u{color:var(--red);}

/* テーブルラッパー */
.tbl-wrap{overflow-x:auto;padding:0;}
table{width:100%;border-collapse:collapse;font-size:12px;}
thead th{background:var(--s2);padding:8px 10px;font-size:10px;font-weight:900;color:var(--mu);letter-spacing:.5px;text-align:left;border-bottom:2px solid var(--bd);white-space:nowrap;position:sticky;top:0;z-index:10;}
thead th:first-child{width:44px;text-align:center;}
thead th.th-store{min-width:120px;}
thead th.th-period{min-width:180px;}
thead th.th-note{min-width:100px;}
thead th.th-pref{width:60px;font-size:10px;}
.td-pref{font-size:11px;color:#a78bfa;white-space:nowrap;cursor:pointer;}

tbody tr{border-bottom:1px solid var(--bd);transition:background .1s;}
tbody tr:hover{background:var(--s2);}
tbody tr.row-urgent{background:rgba(248,81,73,.06);}
tbody tr.row-urgent:hover{background:rgba(248,81,73,.12);}
tbody tr.row-expired{opacity:.4;}
tbody tr.row-upcoming{background:rgba(88,166,255,.04);}

td{padding:8px 10px;vertical-align:middle;}
td:first-child{text-align:center;padding:8px 6px;}

/* チェックボタン */
.chk{width:32px;height:32px;border-radius:6px;border:1px solid var(--bd);background:transparent;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;margin:0 auto;transition:all .15s;}
.chk.applied{background:rgba(88,166,255,.2);border-color:var(--blue);}
.chk.won{background:rgba(63,185,80,.2);border-color:var(--green);}
.chk.lost{background:rgba(255,255,255,.05);border-color:var(--bd);}

/* シリーズ・店舗 */
.td-store{font-size:13px;font-weight:900;line-height:1.3;}
.td-store a{color:var(--tx);text-decoration:none;border-bottom:1px solid transparent;transition:border-color .15s;}
.td-store a:hover{border-bottom-color:var(--yellow);}
.td-series{font-size:10px;color:var(--mu);margin-top:2px;}

/* バッジ */
.badge{display:inline-flex;align-items:center;font-size:9px;font-weight:900;padding:2px 7px;border-radius:3px;white-space:nowrap;letter-spacing:.3px;}
.b-active{background:rgba(245,197,24,.12);color:var(--yellow);border:1px solid rgba(245,197,24,.3);}
.b-urgent{background:rgba(248,81,73,.12);color:var(--red);border:1px solid rgba(248,81,73,.3);}
.b-upcoming{background:rgba(88,166,255,.1);color:var(--blue);border:1px solid rgba(88,166,255,.3);}
.b-expired{background:rgba(255,255,255,.05);color:var(--mu);border:1px solid var(--bd);}

/* 応募期間セル */
.td-period{}
.period-dates{display:flex;gap:6px;font-size:11px;color:var(--mu);margin-bottom:5px;white-space:nowrap;}
.period-dates .val{color:var(--tx);font-weight:700;font-size:11px;}
.bar-track{width:100%;height:5px;background:var(--bd);border-radius:99px;overflow:hidden;margin-bottom:4px;}
.bar-fill{height:100%;border-radius:99px;transition:width .3s;}
.bar-g{background:var(--green);}
.bar-y{background:var(--yellow);}
.bar-r{background:var(--red);}
.remain{font-size:11px;font-weight:900;}
.remain.urgent{color:var(--red);}
.remain.normal{color:var(--mu);}
.remain.upcoming{color:var(--blue);}

/* 備考 */
.td-note{font-size:11px;color:var(--mu);max-width:140px;line-height:1.5;}

/* 空・ローディング */
.empty-row td{text-align:center;padding:40px;color:var(--mu);}

/* フッター */
.tbl-footer{padding:10px 14px;font-size:11px;color:var(--mu);text-align:right;}

/* ボトムナビ */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1a2030;border-top:1px solid #2a3448;display:flex;}
.nav-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0 10px;gap:2px;font-size:9px;color:#7a8aaa;background:none;border:none;font-family:var(--font);cursor:pointer;text-decoration:none;}
.nav-btn.active{color:#f5c518;}
.nav-ico{font-size:18px;line-height:1;display:block;}

/* 自分で追加モーダル */
.my-modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.85);z-index:300;display:none;align-items:flex-end;justify-content:center;}
.my-modal-overlay.open{display:flex;}
.my-modal{background:var(--s2);border:1px solid var(--bd);border-radius:16px 16px 0 0;padding:20px 20px 48px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;}
.my-modal h3{font-size:16px;font-weight:900;margin-bottom:4px;}
.my-modal-note{font-size:11px;color:var(--mu);margin-bottom:16px;}
.my-form-label{font-size:11px;color:var(--mu);margin:12px 0 5px;display:block;}
.my-form-inp{width:100%;background:var(--bg);border:1px solid var(--bd);color:var(--tx);font-family:var(--font);font-size:14px;padding:10px 12px;border-radius:8px;outline:none;box-sizing:border-box;}
.my-form-inp:focus{border-color:var(--yellow);}
.my-modal-actions{display:flex;gap:8px;margin-top:16px;}
.my-btn{flex:1;padding:12px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:900;cursor:pointer;}
.my-btn-primary{background:var(--yellow);color:#000;}
.my-btn-ghost{background:var(--bd);color:var(--mu);}
.my-entries-list{max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}
.my-entry-row{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg);border:1px solid var(--bd);border-radius:7px;}
.my-entry-del{background:transparent;border:none;color:var(--red);font-size:16px;cursor:pointer;padding:0 4px;}
.my-badge{display:inline-flex;align-items:center;font-size:9px;font-weight:900;padding:2px 7px;border-radius:3px;background:rgba(167,139,250,.15);color:#a78bfa;border:1px solid rgba(167,139,250,.3);}
/* 追加ボタン */
.add-my-btn{position:fixed;right:16px;bottom:80px;z-index:200;background:var(--yellow);color:#000;border:none;border-radius:50px;padding:10px 18px;font-size:13px;font-weight:900;font-family:var(--font);cursor:pointer;box-shadow:0 4px 16px rgba(245,197,24,.4);display:flex;align-items:center;gap:6px;}

/* チェック凡例ポップアップ */
.chk-legend{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:var(--s2);border:1px solid var(--bd);border-radius:10px;padding:12px 16px;font-size:12px;z-index:500;display:none;box-shadow:0 8px 32px rgba(0,0,0,.5);white-space:nowrap;}
.chk-legend.show{display:block;}
.chk-legend-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
.chk-legend-row:last-child{margin-bottom:0;}
.legend-chk{width:26px;height:26px;border-radius:5px;border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;font-size:13px;}
</style>
</head>
<body>

<div class="hd">
  <div class="hd-top">
    <a href="<?php echo home_url('/'); ?>" class="site-tag">抽選サーチ</a>
    <span style="font-size:11px;color:var(--mu);" id="last-upd"></span>
  </div>
  <h1>🎮 その他<span> 抽選速報</span></h1>
</div>

<div class="search-bar">
  <input type="text" class="search-inp" id="search-inp" placeholder="🔍 店舗名・シリーズ・都道府県で検索..." oninput="applyFilter()">
</div>

<div class="filter-bar" id="filter-bar">
  <button class="f-btn active" data-f="all">すべて</button>
  <button class="f-btn" data-f="active">受付中</button>
  <button class="f-btn" data-f="urgent">⚡締切間近</button>
  <button class="f-btn" data-f="upcoming">近日開始</button>
  <button class="f-btn" data-f="applied">✓ 応募済</button>
  <button class="f-btn" data-f="expired">終了</button>
  <div class="stat-wrap">
    <span class="stat stat-a">受付中 <b id="s-active">0</b></span>
    <span class="stat stat-u">⚡ <b id="s-urgent">0</b></span>
  </div>
</div>

<div style="padding:6px 14px;background:var(--s1);border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:8px;">
  <span style="font-size:11px;color:var(--mu);flex-shrink:0;">📍 都道府県</span>
  <select id="pref-select" onchange="filterByPref(this.value)" style="flex:1;background:var(--s2);border:1px solid var(--bd);border-radius:6px;padding:5px 8px;color:var(--tx);font-size:12px;font-family:var(--font);">
    <option value="all">すべて（全国）</option>
    <option value="全国">🗾 全国（複数店舗）</option>
    <option value="オンライン">🌐 オンライン</option>
    <optgroup label="北海道・東北">
      <option value="北海道">北海道</option>
      <option value="青森県">青森県</option><option value="岩手県">岩手県</option><option value="宮城県">宮城県</option>
      <option value="秋田県">秋田県</option><option value="山形県">山形県</option><option value="福島県">福島県</option>
    </optgroup>
    <optgroup label="関東">
      <option value="茨城県">茨城県</option><option value="栃木県">栃木県</option><option value="群馬県">群馬県</option>
      <option value="埼玉県">埼玉県</option><option value="千葉県">千葉県</option><option value="東京都">東京都</option><option value="神奈川県">神奈川県</option>
    </optgroup>
    <optgroup label="中部">
      <option value="新潟県">新潟県</option><option value="富山県">富山県</option><option value="石川県">石川県</option><option value="福井県">福井県</option>
      <option value="山梨県">山梨県</option><option value="長野県">長野県</option><option value="岐阜県">岐阜県</option><option value="静岡県">静岡県</option><option value="愛知県">愛知県</option><option value="三重県">三重県</option>
    </optgroup>
    <optgroup label="近畿">
      <option value="滋賀県">滋賀県</option><option value="京都府">京都府</option><option value="大阪府">大阪府</option>
      <option value="兵庫県">兵庫県</option><option value="奈良県">奈良県</option><option value="和歌山県">和歌山県</option>
    </optgroup>
    <optgroup label="中国・四国">
      <option value="鳥取県">鳥取県</option><option value="島根県">島根県</option><option value="岡山県">岡山県</option><option value="広島県">広島県</option><option value="山口県">山口県</option>
      <option value="徳島県">徳島県</option><option value="香川県">香川県</option><option value="愛媛県">愛媛県</option><option value="高知県">高知県</option>
    </optgroup>
    <optgroup label="九州・沖縄">
      <option value="福岡県">福岡県</option><option value="佐賀県">佐賀県</option><option value="長崎県">長崎県</option><option value="熊本県">熊本県</option>
      <option value="大分県">大分県</option><option value="宮崎県">宮崎県</option><option value="鹿児島県">鹿児島県</option><option value="沖縄県">沖縄県</option>
    </optgroup>
  </select>
</div>

<div style="margin:10px 14px;">
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-9050998756903244"
     data-ad-slot="5702551378"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>

<div class="tbl-wrap">
  <table id="main-table">
    <thead>
      <tr>
        <th title="応募済みチェック">✓</th>
        <th class="th-pref">📍</th>
        <th class="th-store th-series-wrap"><button class="th-store-btn" id="th-series-btn" onclick="toggleSeriesDropdown()">店舗名 / シリーズ ▾</button><div class="series-dropdown" id="series-dropdown"></div></th>
        <th class="th-period">応募期間</th>
        <th class="th-note">備考</th>
      </tr>
    </thead>
    <tbody id="tbl-body">
      <tr class="empty-row"><td colspan="5">読み込み中...</td></tr>
    </tbody>
  </table>
</div>
<div class="tbl-footer" id="tbl-footer"></div>

<div style="margin:10px 14px;">
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-9050998756903244"
     data-ad-slot="5853614671"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>

<footer style="padding:20px 16px 16px;border-top:1px solid #30363d;margin-top:8px;">
  <div style="text-align:center;margin-bottom:10px;">
    <a href="<?php echo home_url('/'); ?>" style="font-family:var(--font);font-size:16px;font-weight:900;color:var(--tx);text-decoration:none;">抽選サーチ</a>
  </div>
  <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:10px;">
    <a href="<?php echo home_url('/privacy-policy/'); ?>" style="font-size:12px;color:var(--mu);text-decoration:none;">プライバシーポリシー</a>
    <a href="<?php echo home_url('/contact/'); ?>" style="font-size:12px;color:var(--mu);text-decoration:none;">お問い合わせ</a>
  </div>
  <p style="text-align:center;font-size:11px;color:var(--mu);">© <?php echo date('Y'); ?> 抽選サーチ All Rights Reserved.</p>
</footer>

<button class="add-my-btn" id="add-my-btn">＋ 自分で追加</button>

<div class="my-modal-overlay" id="my-modal-overlay">
  <div class="my-modal">
    <h3>📝 自分で抽選情報を追加</h3>
    <p class="my-modal-note">このデバイスのみに保存されます。管理者には見えません。</p>
    <div class="my-entries-list" id="my-entries-list"></div>
    <div style="font-size:11px;font-weight:900;color:var(--yellow);letter-spacing:1px;margin-bottom:10px;padding-top:12px;border-top:1px solid var(--bd);">＋ 新規追加</div>
    <label class="my-form-label">シリーズ名 *</label>
    <input type="text" class="my-form-inp" id="my-series" placeholder="例：ニンジャスピナー">
    <label class="my-form-label">店舗名 *</label>
    <input type="text" class="my-form-inp" id="my-store" placeholder="例：カードラボ秋葉原" maxlength="50">
    <label class="my-form-label">応募開始日時</label>
    <input type="datetime-local" class="my-form-inp" id="my-start">
    <label class="my-form-label">応募締切日時</label>
    <input type="datetime-local" class="my-form-inp" id="my-end">
    <label class="my-form-label">応募URL</label>
    <input type="url" class="my-form-inp" id="my-url" placeholder="https://...">
    <label class="my-form-label">備考</label>
    <input type="text" class="my-form-inp" id="my-note" placeholder="例：WEB抽選、会員限定">
    <div class="my-modal-actions">
      <button class="my-btn my-btn-ghost" id="my-modal-close">閉じる</button>
      <button class="my-btn my-btn-primary" id="my-modal-save">追加する</button>
    </div>
  </div>
</div>

<nav class="bottom-nav">
  <a class="nav-btn" href="<?php echo home_url('/'); ?>"><span class="nav-ico">🏠</span>ホーム</a>
  <a class="nav-btn active" href="<?php echo home_url('/other/'); ?>"><span class="nav-ico">🃏</span>ポケカ</a>
  <a class="nav-btn" href="<?php echo home_url('/sneaker/'); ?>"><span class="nav-ico">👟</span>スニーカー</a>
  <a class="nav-btn active" href="<?php echo home_url('/other/'); ?>"><span class="nav-ico">🎮</span>その他</a>
  <a class="nav-btn" href="<?php echo home_url('/'); ?>#cal"><span class="nav-ico">🗓</span>カレンダー</a>
</nav>

<?php wp_footer(); ?>

<script>
var CAT = 'other';
var WP_API = '<?php echo home_url('/wp-json/wp/v2/lottery'); ?>';
var CHECK_KEY = 'tcs_checks_pokeca';
var wpEntries = [];
var myEntries = [];
var activeFilter = 'all';
var activeSeries = 'all';
var activePref = 'all';
var searchQ = '';
var MY_KEY = 'tcs_my_other';

function loadMyEntries() {
  try { myEntries = JSON.parse(localStorage.getItem(MY_KEY) || '[]'); } catch(e) { myEntries = []; }
}
function saveMyEntries() {
  localStorage.setItem(MY_KEY, JSON.stringify(myEntries));
}
function allEntries() { return wpEntries.concat(myEntries); }

// Cookie ベースのチェック保存
function getCookieChecks() {
  var m = document.cookie.match(/(?:^|; )tcs_chk_other=([^;]*)/);
  try { return m ? JSON.parse(decodeURIComponent(m[1])) : {}; } catch(e) { return {}; }
}
function saveCookieChecks(obj) {
  var exp = new Date(); exp.setFullYear(exp.getFullYear()+1);
  document.cookie = 'tcs_chk_other=' + encodeURIComponent(JSON.stringify(obj)) + '; expires=' + exp.toUTCString() + '; path=/';
}
function getCheck(id) { return getCookieChecks()[id] || ''; }
function toggleCheck(id) {
  var obj = getCookieChecks();
  if (obj[id] === 'applied') { obj[id] = ''; }
  else { obj[id] = 'applied'; }
  saveCookieChecks(obj);
  renderTable();
}

function parseWpDate(str) {
  if (!str) return '';
  // YYYYMMDD形式
  if (/^\d{8}$/.test(str)) return str.slice(0,4)+'-'+str.slice(4,6)+'-'+str.slice(6,8)+'T00:00:00+09:00';
  // YYYYMMDDHHmmss形式
  if (/^\d{14}$/.test(str)) return str.slice(0,4)+'-'+str.slice(4,6)+'-'+str.slice(6,8)+'T'+str.slice(8,10)+':'+str.slice(10,12)+':'+str.slice(12,14)+'+09:00';
  // "2026-03-04 10:00:00" スペース区切り → Tに変換
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(str)) return str.replace(' ', 'T')+'+09:00';
  // "2026-03-08T23:59" ISO形式（秒なし）
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) return str+':00+09:00';
  return str;
}
function fmtDt(iso, dateOnly) {
  if (!iso) return '未定';
  var d = new Date(iso);
  if (isNaN(d.getTime())) return '未定';
  var base = (d.getMonth()+1)+'/'+d.getDate();
  if (dateOnly || (d.getHours()===0 && d.getMinutes()===0)) return base;
  return base+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
}
function getStatus(e) {
  var now = Date.now(), s = new Date(e.start).getTime();
  if (!e.end) return now < s ? 'upcoming' : 'active';
  var en = new Date(e.end).getTime();
  if (now < s) return 'upcoming';
  if (now > en) return 'expired';
  if ((en - now) / 3600000 <= 24) return 'urgent';
  return 'active';
}
function getRemain(e) {
  if (!e.end) return { text: '未定', cls: 'normal' };
  var diff = new Date(e.end).getTime() - Date.now();
  if (diff <= 0) return { text: '終了', cls: 'normal' };
  var h = Math.floor(diff/3600000), d = Math.floor(h/24), m = Math.floor((diff%3600000)/60000);
  if (d >= 1) return { text: d+'日'+(h%24)+'時間', cls: 'normal' };
  if (h >= 1) return { text: h+'時間'+m+'分', cls: 'urgent' };
  return { text: m+'分', cls: 'urgent' };
}
function getPct(e) {
  if (!e.end) return 100;
  var en = new Date(e.end).getTime(), now = Date.now();
  var diff = en - now;
  if (diff <= 0) return 0;
  // 残り5日(120h)=100%, 0=0%
  var maxMs = 120 * 3600000;
  return Math.max(2, Math.min(100, diff / maxMs * 100));
}

function loadWpEntries() {
  fetch(WP_API+'?per_page=100&_fields=id,acf,prefecture,featured_media,link')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      wpEntries = data.filter(function(p){ return p.acf && p.acf.category === CAT; }).map(function(p) {
        return {
          id: 'wp_'+p.id,
          source: 'wp',
          series: p.acf.series || '',
          store: p.acf.store || '',
          pref: p.prefecture || p.acf.prefecture || '',
          start: parseWpDate(p.acf.start_date),
          end: parseWpDate(p.acf.end_date),
          result: parseWpDate(p.acf.result_date),
          url: p.acf.lottery_url || '',
          note: p.acf.note || '',
          link: p.link || '',
          thumb: '',
          featured_media: p.featured_media || 0
        };
      });
      var mediaIds = wpEntries.map(function(e){ return e.featured_media; }).filter(function(id){ return id > 0; });
      if (mediaIds.length > 0) {
        fetch('<?php echo home_url('/wp-json/wp/v2/media'); ?>?include='+mediaIds.join(',')+'&_fields=id,source_url,media_details')
          .then(function(r){ return r.json(); })
          .then(function(md) {
            var map = {};
            md.forEach(function(m) {
              map[m.id] = (m.media_details&&m.media_details.sizes&&m.media_details.sizes.thumbnail)
                ? m.media_details.sizes.thumbnail.source_url : m.source_url;
            });
            wpEntries.forEach(function(e) { if (e.featured_media && map[e.featured_media]) e.thumb = map[e.featured_media]; });
            renderTable();
          }).catch(function(){ renderTable(); });
      } else {
        renderTable();
      }
    }).catch(function() {
      document.getElementById('tbl-body').innerHTML = '<tr class="empty-row"><td colspan="5">データの取得に失敗しました</td></tr>';
    });
}

function filterByPref(pref) {
  activePref = pref;
  var sel = document.getElementById('pref-select');
  if (sel) sel.value = pref;
  renderTable();
}
function applyFilter() {
  searchQ = document.getElementById('search-inp').value.trim().toLowerCase();
  renderTable();
}
function toggleSeriesDropdown() {
  document.getElementById('series-dropdown').classList.toggle('open');
}
// 外クリックで閉じる
document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.th-series-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('series-dropdown').classList.remove('open');
  }
});


function renderTable() {
  // ドロップダウン内容更新
  var dd = document.getElementById('series-dropdown');
  if (dd) {
    var seen2 = {}, seriesList2 = [];
    allEntries().forEach(function(e){ if(e.series && !seen2[e.series]){ seen2[e.series]=true; seriesList2.push(e.series); } });
    var ddHtml = '<div class="series-opt'+(activeSeries==='all'?' active':'')+'" data-s="__all__">' + 'すべて ('+allEntries().length+'件)</div>';
    seriesList2.forEach(function(s){
      var cnt = allEntries().filter(function(e){ return e.series===s; }).length;
      var escaped = s.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
      ddHtml += '<div class="series-opt'+(activeSeries===s?' active':'')+'" data-s="'+escaped+'">'+s+' ('+cnt+')</div>';
    });
    dd.innerHTML = ddHtml;
    var btn = document.getElementById('th-series-btn');
    if (btn) btn.classList.toggle('active', activeSeries !== 'all');
    if (btn) btn.textContent = (activeSeries==='all' ? '店舗名 / シリーズ ▾' : '▸ '+activeSeries+' ✕');
  }

  var rows = allEntries().filter(function(e) {
    var st = getStatus(e);
    var chk = getCheck(e.id);
    if (activeSeries !== 'all' && e.series !== activeSeries) return false;
    if (activePref !== 'all' && (e.pref || '') !== activePref) return false;
    if (activeFilter === 'applied' && chk !== 'applied') return false;
    if (activeFilter !== 'all' && activeFilter !== 'applied' && st !== activeFilter) return false;
    if (searchQ) {
      var hay = (e.store + ' ' + e.series + ' ' + (e.pref||'')).toLowerCase();
      if (hay.indexOf(searchQ) === -1) return false;
    }
    return true;
  });

  rows.sort(function(a,b){
    var sa = getStatus(a), sb = getStatus(b);
    // expired は最後
    if (sa === 'expired' && sb !== 'expired') return 1;
    if (sb === 'expired' && sa !== 'expired') return -1;
    // upcoming は終了の前
    if (sa === 'upcoming' && sb !== 'upcoming' && sb !== 'expired') return 1;
    if (sb === 'upcoming' && sa !== 'upcoming' && sa !== 'expired') return -1;
    // 残り時間が少ない順（end_dateが近い順）
    var ea = a.end ? new Date(a.end).getTime() : Infinity;
    var eb = b.end ? new Date(b.end).getTime() : Infinity;
    return ea - eb;
  });

  // stats
  document.getElementById('s-active').textContent = allEntries().filter(function(e){ return getStatus(e)==='active'; }).length;
  document.getElementById('s-urgent').textContent = allEntries().filter(function(e){ return getStatus(e)==='urgent'; }).length;

  if (!rows.length) {
    document.getElementById('tbl-body').innerHTML = '<tr class="empty-row"><td colspan="5">該当する情報がありません</td></tr>';
    document.getElementById('tbl-footer').textContent = '';
    return;
  }

  var badgeMap = {
    active:   '<span class="badge b-active">受付中</span>',
    urgent:   '<span class="badge b-urgent">⚡締切間近</span>',
    upcoming: '<span class="badge b-upcoming">近日開始</span>',
    expired:  '<span class="badge b-expired">終了</span>'
  };

  var html = '';
  rows.forEach(function(e, idx) {
    var st = getStatus(e);
    var pct = getPct(e);
    var rem = getRemain(e);
    var chk = getCheck(e.id);
    var barCls = pct > 60 ? 'bar-g' : pct > 25 ? 'bar-y' : 'bar-r';
    var rowCls = st==='urgent' ? 'row-urgent' : st==='expired' ? 'row-expired' : st==='upcoming' ? 'row-upcoming' : '';
    var chkIcon = chk === 'applied' ? '✓' : '−';
    var chkCls  = chk === 'applied' ? 'applied' : '';
    var myBadge = e.source === 'mine' ? ' <span class="my-badge">自分</span>' : '';
    var prefHTML = '';
    var storeHTML = e.link ? '<a href="'+e.link+'">'+e.store+'</a>'+myBadge : e.store+myBadge;
    var applyBtn = e.url ? '<div style="margin-top:6px;"><a href="'+e.url+'" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;background:var(--yellow);color:#000;font-weight:900;font-size:11px;padding:5px 12px;border-radius:6px;text-decoration:none;">応募する →</a></div>' : '';
    var periodHTML = '';
    if (st === 'active' || st === 'urgent') {
      periodHTML = '<div class="period-dates"><span>開始</span><span class="val">'+fmtDt(e.start)+'</span><span style="color:var(--bd)">│</span><span>締切</span><span class="val'+(st==='urgent'?' urgent':'')+'">'+fmtDt(e.end)+'</span></div>'
        + '<div class="bar-track"><div class="bar-fill '+barCls+'" style="width:'+pct+'%"></div></div>'
        + '<div class="remain '+rem.cls+'">残り '+rem.text+'</div>'
        + (e.result ? '<div style="font-size:10px;color:#f5c518;margin-top:3px;">🏆 発表: '+fmtDt(e.result, true)+'</div>' : '');
    } else if (st === 'upcoming') {
      periodHTML = '<div class="period-dates"><span>開始</span><span class="val" style="color:var(--blue)">'+fmtDt(e.start)+'</span></div><div class="remain upcoming">開始待ち</div>';
    } else {
      periodHTML = '<div class="period-dates"><span>締切</span><span class="val">'+fmtDt(e.end)+'</span></div><div class="remain normal">終了</div>'
        + (e.result ? '<div style="font-size:10px;color:#f5c518;margin-top:3px;">🏆 発表: '+fmtDt(e.result, true)+'</div>' : '');
    }
    html += '<tr class="'+rowCls+'">'
      + '<td><button class="chk '+chkCls+'" onclick="toggleCheck(\''+e.id+'\')" title="応募済みにする">'+chkIcon+'</button></td>'
      + '<td class="td-pref" onclick="filterByPref(\''+e.pref+'\')">'+(e.pref||'−')+'</td>'
      + '<td><div class="td-store">'+storeHTML+'</div><div class="td-series">'+e.series+'</div><div style="margin-top:4px;">'+badgeMap[st]+'</div>'+applyBtn+'</td>'
      + '<td class="td-period">'+periodHTML+'</td>'
      + '<td class="td-note">'+(e.note || '−')+'</td>'
      + '</tr>';
  });

  document.getElementById('tbl-body').innerHTML = html;
  document.getElementById('tbl-footer').textContent = rows.length + '件表示 / 最終更新: ' + new Date().toLocaleTimeString('ja-JP');
  document.getElementById('last-upd').textContent = new Date().toLocaleTimeString('ja-JP') + ' 更新';
}

// フィルターバーのイベント委譲
document.getElementById('filter-bar').addEventListener('click', function(e) {
  var btn = e.target.closest('[data-f]');
  if (!btn) return;
  activeFilter = btn.getAttribute('data-f');
  document.querySelectorAll('.f-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  renderTable();
});

// ドロップダウンのイベント委譲
document.getElementById('series-dropdown').addEventListener('click', function(e) {
  var opt = e.target.closest('[data-s]');
  if (!opt) return;
  var s = opt.getAttribute('data-s');
  activeSeries = s === '__all__' ? 'all' : s;
  document.getElementById('series-dropdown').classList.remove('open');
  renderTable();
});

setInterval(function(){ loadWpEntries(); }, 60000);
loadMyEntries();
loadWpEntries();

// URLパラメータ ?series= で自動絞り込み
(function() {
  var params = new URLSearchParams(window.location.search);
  var s = params.get('series');
  if (s) {
    activeSeries = s;
    var btn = document.getElementById('th-series-btn');
    if (btn) btn.textContent = '▸ ' + s + ' ✕';
  }
})();

// モーダル開閉
document.getElementById('add-my-btn').addEventListener('click', function() {
  renderMyEntriesList();
  document.getElementById('my-modal-overlay').classList.add('open');
});
document.getElementById('my-modal-close').addEventListener('click', function() {
  document.getElementById('my-modal-overlay').classList.remove('open');
});
document.getElementById('my-modal-overlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});

// 追加ボタン
document.getElementById('my-modal-save').addEventListener('click', function() {
  var series = document.getElementById('my-series').value.trim();
  var store  = document.getElementById('my-store').value.trim();
  var start  = document.getElementById('my-start').value;
  var end    = document.getElementById('my-end').value;
  var url    = document.getElementById('my-url').value.trim();
  var note   = document.getElementById('my-note').value.trim();
  if (!series || !store) { alert('シリーズ名と店舗名は必須です'); return; }
  if (end && start && new Date(start) >= new Date(end)) { alert('締切は開始より後にしてください'); return; }
  myEntries.push({
    id: 'my_' + Date.now(),
    source: 'mine',
    series: series,
    store: store,
    start: start ? new Date(start).toISOString() : '',
    end:   end   ? new Date(end).toISOString()   : '',
    url:   url,
    note:  note,
    link:  url,
    thumb: ''
  });
  saveMyEntries();
  ['my-series','my-store','my-start','my-end','my-url','my-note'].forEach(function(id){ document.getElementById(id).value=''; });
  renderMyEntriesList();
  renderTable();
});

function renderMyEntriesList() {
  var el = document.getElementById('my-entries-list');
  if (!myEntries.length) { el.innerHTML = '<div style="font-size:12px;color:var(--mu);padding:8px 0;">まだ追加した情報はありません</div>'; return; }
  el.innerHTML = myEntries.map(function(e) {
    return '<div class="my-entry-row">'
      + '<div style="flex:1;min-width:0;">'
      + '<div style="font-size:13px;font-weight:700;">'+e.store+'</div>'
      + '<div style="font-size:10px;color:var(--mu);">'+e.series+(e.end?' / 締切:'+fmtDt(e.end):'')+'</div>'
      + '</div>'
      + '<button class="my-entry-del" data-id="'+e.id+'">🗑</button>'
      + '</div>';
  }).join('');
  el.querySelectorAll('.my-entry-del').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.getAttribute('data-id');
      if (!confirm('削除しますか？')) return;
      myEntries = myEntries.filter(function(e){ return e.id !== id; });
      saveMyEntries();
      renderMyEntriesList();
      renderTable();
    });
  });
}
</script>
</body>
</html>