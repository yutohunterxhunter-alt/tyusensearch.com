<?php get_header(); ?>

<style>
.ticker-bar { background: var(--urgent); padding: 7px 0; display: flex; align-items: center; overflow: hidden; }
.ticker-lbl { font-family: var(--font-d); font-size: 13px; letter-spacing: 2px; color: #fff; padding: 0 12px; flex-shrink: 0; border-right: 1px solid rgba(255,255,255,.3); }
.ticker-track { overflow: hidden; flex: 1; }
.ticker-scroll { display: flex; gap: 32px; white-space: nowrap; animation: ticker 30s linear infinite; }
.ticker-scroll:hover { animation-play-state: paused; }
@keyframes ticker { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
.ticker-item { font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; padding: 0 8px; }
.cat-nav { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid var(--border); background: var(--s1); }
.cat-nav::-webkit-scrollbar { display: none; }
.cat-btn { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--s2); color: var(--text); font-family: var(--font-b); font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
.cat-btn-pokeca  { border-color: var(--pokeca);  color: var(--pokeca);  background: rgba(245,197,24,.08); }
.cat-btn-sneaker { border-color: var(--sneaker); color: var(--sneaker); background: rgba(59,158,255,.08); }
.cat-btn-other   { border-color: var(--other);   color: var(--other);   background: rgba(167,139,250,.08); }
.sec-head { display: flex; align-items: baseline; justify-content: space-between; padding: 18px 16px 10px; }
.sec-title { font-family: var(--font-d); font-size: 20px; letter-spacing: 2px; line-height: 1; }
.sec-title-accent { color: var(--neon); }
.sec-link { font-size: 11px; color: var(--muted); text-decoration: none; border-bottom: 1px solid var(--border); }
.new-scroll { overflow-x: auto; padding: 0 16px 16px; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
.new-scroll::-webkit-scrollbar { display: none; }
.new-track { display: flex; flex-direction: row; flex-wrap: nowrap; gap: 10px; width: max-content; }
.new-card { flex-shrink: 0; width: calc((100vw - 48px) / 3); max-width: 160px; min-width: 110px; background: var(--s2); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; text-decoration: none; color: var(--text); display: block; }
.new-card-pk { border-top: 3px solid var(--pokeca); }
.new-card-sn { border-top: 3px solid var(--sneaker); }
.new-card-ot { border-top: 3px solid var(--other); }
.card-thumb { height: 80px; background: var(--s3); display: flex; align-items: center; justify-content: center; font-size: 34px; position: relative; overflow: hidden; }
.card-thumb img { width: 100%; height: 100%; object-fit: cover; }
.card-new { position: absolute; top: 6px; right: 6px; background: var(--neon); color: #000; font-family: var(--font-d); font-size: 10px; letter-spacing: 1px; padding: 1px 6px; border-radius: 2px; }
.card-body { padding: 10px 11px 12px; }
.card-cat  { font-size: 9px; letter-spacing: 1.5px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; }
.cat-pk { color: var(--pokeca); } .cat-sn { color: var(--sneaker); } .cat-ot { color: var(--other); }
.card-name  { font-size: 13px; font-weight: 900; line-height: 1.3; margin-bottom: 4px; }
.card-store { font-size: 11px; color: var(--muted); margin-bottom: 6px; }
.card-status { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 3px; }
.st-active { background: rgba(0,240,192,.1); color: var(--neon); border: 1px solid rgba(0,240,192,.25); }
.st-urgent { background: rgba(255,62,108,.12); color: var(--urgent); border: 1px solid rgba(255,62,108,.3); }
.st-soon   { background: rgba(59,158,255,.1); color: var(--sneaker); border: 1px solid rgba(59,158,255,.25); }
.card-dl { font-size: 10px; color: var(--muted); margin-top: 6px; }
.card-dl-near { color: var(--urgent); font-weight: 700; }
.new-loading { padding: 24px 16px; color: var(--muted); font-size: 13px; }
.urgent-list { padding: 0 16px 8px; display: flex; flex-direction: column; gap: 8px; }
.urgent-row { display: flex; align-items: center; gap: 12px; background: var(--s2); border: 1px solid rgba(255,62,108,.25); border-left: 3px solid var(--urgent); border-radius: 8px; padding: 11px 13px; text-decoration: none; color: var(--text); }
.urg-info { flex: 1; min-width: 0; }
.urg-name  { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.urg-store { font-size: 11px; color: var(--muted); margin-top: 2px; }
.urg-bar-wrap { margin-top: 6px; height: 3px; background: var(--border); border-radius: 99px; overflow: hidden; }
.urg-bar { height: 100%; background: var(--urgent); border-radius: 99px; }
.urg-right { flex-shrink: 0; text-align: right; }
.urg-time  { font-size: 14px; font-weight: 900; color: var(--urgent); white-space: nowrap; }
.no-data { padding: 20px 16px; text-align: center; color: var(--muted); font-size: 13px; }
.result-row { display: flex; align-items: center; gap: 12px; background: var(--s2); border: 1px solid rgba(245,197,24,.2); border-left: 3px solid var(--accent); border-radius: 8px; padding: 11px 13px; text-decoration: none; color: var(--text); }
.result-name { font-size: 13px; font-weight: 700; line-height: 1.4; }
.result-store { font-size: 11px; color: var(--muted); margin-top: 2px; }
.result-time { font-size: 12px; font-weight: 900; color: var(--accent); white-space: nowrap; flex-shrink: 0; }
.cal-wrap { padding: 0 16px 24px; }
.cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.cal-month { font-size: 13px; font-weight: 700; color: var(--text); }
.cal-dow-row { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 3px; }
.cal-dow { text-align: center; font-size: 10px; color: var(--muted); padding: 4px 0; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 10px; }
.cal-day { background: var(--s2); border: 1px solid var(--border); border-radius: 5px; padding: 5px 2px 4px; min-height: 42px; text-align: center; }
.cal-day-empty { background: transparent; border-color: transparent; }
.cal-day-today { border-color: var(--neon); background: rgba(0,240,192,.07); }
.cal-num { font-size: 11px; font-weight: 700; color: var(--muted); }
.cal-day-today .cal-num { color: var(--neon); }
.cal-dots { display: flex; justify-content: center; gap: 2px; margin-top: 4px; flex-wrap: wrap; }
.cal-dot { width: 5px; height: 5px; border-radius: 50%; }
.dot-pk { background: var(--pokeca); } .dot-sn { background: var(--sneaker); } .dot-ot { background: var(--other); }
.cal-legend { display: flex; gap: 14px; }
.leg { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--muted); }
.leg-dot { width: 8px; height: 8px; border-radius: 50%; }
.cal-modal-overlay { display: none; position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.85); z-index: 200; align-items: flex-end; justify-content: center; }
.cal-modal-overlay.open { display: flex; }
.cal-modal { background: var(--s2); border: 1px solid var(--border); border-radius: 16px 16px 0 0; padding: 20px 20px 44px; width: 100%; max-width: 600px; max-height: 80vh; overflow-y: auto; }
.cal-modal-title { font-size: 16px; font-weight: 900; margin-bottom: 16px; color: var(--neon); }
.cal-modal-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 8px; text-decoration: none; color: var(--text); }
.cal-modal-emoji { font-size: 24px; flex-shrink: 0; }
.cal-modal-info { flex: 1; min-width: 0; }
.cal-modal-name { font-size: 13px; font-weight: 700; }
.cal-modal-store { font-size: 11px; color: var(--muted); margin-top: 2px; }
.cal-modal-time { font-size: 11px; color: var(--urgent); font-weight: 700; margin-top: 2px; }
.cal-modal-arrow { font-size: 12px; color: var(--muted); flex-shrink: 0; }
.cal-modal-empty { text-align: center; padding: 24px; color: var(--muted); font-size: 13px; }
.cal-modal-close { width: 100%; padding: 12px; background: var(--border); border: none; color: var(--muted); font-family: var(--font-b); font-size: 14px; font-weight: 700; border-radius: 8px; cursor: pointer; margin-top: 16px; }
.cal-day-has-event { cursor: pointer; }
.cal-day-has-event:hover { border-color: var(--neon); background: rgba(0,240,192,.05); }
</style>

<div class="ticker-bar">
  <div class="ticker-lbl">締切間近</div>
  <div class="ticker-track">
    <div class="ticker-scroll" id="ticker-scroll">
      <span class="ticker-item">読み込み中...</span>
    </div>
  </div>
</div>

<nav class="cat-nav">
  <a href="<?php echo home_url('/pokeca-series/'); ?>" class="cat-btn cat-btn-pokeca">🃏 ポケカ 抽選一覧</a>
  <a href="<?php echo home_url('/sneaker-series/'); ?>" class="cat-btn cat-btn-sneaker">👟 スニーカー 抽選一覧</a>
  <a href="<?php echo home_url('/other-series/'); ?>" class="cat-btn cat-btn-other">🎮 その他 抽選一覧</a>
  <a href="#cal" class="cat-btn">🗓 締切カレンダー</a>
</nav>

<div style="margin:10px 14px;">
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-9050998756903244"
     data-ad-slot="5702551378"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>
<div class="sec-head">
  <div class="sec-title">NEW <span class="sec-title-accent">ARRIVALS</span></div>
  <a href="<?php echo home_url('/pokeca/'); ?>" class="sec-link">すべて見る →</a>
</div>
<div class="new-scroll">
  <div class="new-track" id="new-track">
    <div class="new-loading">読み込み中...</div>
  </div>
</div>

<div class="sec-head" style="padding-top:6px">
  <div class="sec-title">⚡ 締切<span class="sec-title-accent">間近</span></div>
</div>
<div class="urgent-list" id="urgent-list">
  <div class="no-data">読み込み中...</div>
</div>

<div style="margin:10px 14px;">
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-9050998756903244"
     data-ad-slot="5853614671"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>
<div class="sec-head" style="padding-top:18px">
  <div class="sec-title">本日の抽選<span class="sec-title-accent">発表</span></div>
</div>
<div class="urgent-list" id="result-list">
  <div class="no-data">読み込み中...</div>
</div>

<div class="sec-head" id="cal" style="padding-top:18px">
  <div class="sec-title">締切<span class="sec-title-accent">カレンダー</span></div>
</div>
<div class="cal-wrap">
  <div class="cal-head">
    <span class="cal-month" id="cal-month"></span>
  </div>
  <div class="cal-dow-row">
    <div class="cal-dow">日</div><div class="cal-dow">月</div><div class="cal-dow">火</div>
    <div class="cal-dow">水</div><div class="cal-dow">木</div><div class="cal-dow">金</div><div class="cal-dow">土</div>
  </div>
  <div class="cal-grid" id="cal-grid"></div>
  <div class="cal-legend">
    <div class="leg"><div class="leg-dot dot-pk"></div>ポケカ</div>
    <div class="leg"><div class="leg-dot dot-sn"></div>スニーカー</div>
    <div class="leg"><div class="leg-dot dot-ot"></div>その他</div>
  </div>
</div>

<div class="cal-modal-overlay" id="cal-modal" onclick="closeCalModal(event)">
  <div class="cal-modal">
    <div class="cal-modal-title" id="cal-modal-title"></div>
    <div id="cal-modal-body"></div>
    <button class="cal-modal-close" onclick="closeCalModal()">閉じる</button>
  </div>
</div>

<script>
var WP_API = '<?php echo home_url('/wp-json/wp/v2/lottery'); ?>';
var allPosts = [];
var CAT_EMOJI = { pokeca: '🃏', sneaker: '👟', other: '🎮' };
var CAT_LABEL = { pokeca: 'ポケカ', sneaker: 'スニーカー', other: 'その他' };
var CAT_CLS   = { pokeca: 'pk', sneaker: 'sn', other: 'ot' };

function parseDate(str) {
  if (!str) return null;
  if (str.length === 8) return new Date(str.slice(0,4)+'-'+str.slice(4,6)+'-'+str.slice(6,8)+'T00:00:00+09:00');
  if (str.length === 14) return new Date(str.slice(0,4)+'-'+str.slice(4,6)+'-'+str.slice(6,8)+'T'+str.slice(8,10)+':'+str.slice(10,12)+':'+str.slice(12,14)+'+09:00');
  return new Date(str);
}
function getStatus(post) {
  var now = Date.now();
  var s = parseDate(post.acf.start_date);
  var e = parseDate(post.acf.end_date);
  var st = s ? s.getTime() : 0;
  if (!e) return now < st ? 'upcoming' : 'active';
  var et = e.getTime();
  if (now < st) return 'upcoming';
  if (now > et) return 'expired';
  if ((et - now) / 3600000 <= 24) return 'urgent';
  return 'active';
}
function getRemain(post) {
  var e = parseDate(post.acf.end_date);
  if (!e) return '未定';
  var diff = e.getTime() - Date.now();
  if (diff <= 0) return '終了';
  var h = Math.floor(diff/3600000), d = Math.floor(h/24), m = Math.floor((diff%3600000)/60000);
  if (d >= 1) return d+'日'+(h%24)+'時間';
  if (h >= 1) return h+'時間'+m+'分';
  return m+'分';
}
function fmtDate(str) {
  var d = parseDate(str);
  if (!d || isNaN(d.getTime())) return '未定';
  return (d.getMonth()+1)+'/'+d.getDate();
}
function renderTicker(posts) {
  var urgent = posts.filter(function(p){ return getStatus(p)==='urgent'; });
  var scroll = document.getElementById('ticker-scroll');
  if (!urgent.length) { scroll.innerHTML = '<span class="ticker-item">現在締切間近の抽選はありません</span>'; return; }
  var items = urgent.concat(urgent).map(function(p) {
    return '<span class="ticker-item">'+(CAT_EMOJI[p.acf.category]||'📌')+' '+p.acf.series+' — '+p.acf.store+' / 残り'+getRemain(p)+'</span>';
  }).join('');
  scroll.innerHTML = items;
}
function renderNewArrivals(posts) {
  var active = posts.filter(function(p){ var st=getStatus(p); return st==='active'||st==='urgent'||st==='upcoming'; }).slice(0,10);
  var track = document.getElementById('new-track');
  if (!active.length) { track.innerHTML = '<div class="new-loading">現在受付中の抽選情報はありません</div>'; return; }
  track.innerHTML = active.map(function(p, i) {
    var cat = p.acf.category || 'other';
    var cls = CAT_CLS[cat] || 'ot';
    var st  = getStatus(p);
    var stHTML = st==='urgent' ? '<span class="card-status st-urgent">⚡締切間近</span>'
               : st==='upcoming' ? '<span class="card-status st-soon">近日開始</span>'
               : '<span class="card-status st-active">受付中</span>';
    var dlText = p.acf.end_date ? '締切: '+fmtDate(p.acf.end_date) : '締切: 未定';
    var dlCls  = st==='urgent' ? 'card-dl card-dl-near' : 'card-dl';
    var thumb  = p._thumbUrl || '';
    var thumbHTML = thumb ? '<img src="'+thumb+'" alt="'+p.acf.series+'">' : '<span>'+(CAT_EMOJI[cat]||'📌')+'</span>';
    var isNew = i===0 ? '<span class="card-new">NEW</span>' : '';
    return '<a href="'+p.link+'" class="new-card new-card-'+cls+'">'
      +'<div class="card-thumb">'+thumbHTML+isNew+'</div>'
      +'<div class="card-body"><div class="card-cat cat-'+cls+'">'+(CAT_LABEL[cat]||'その他')+'</div>'
      +'<div class="card-name">'+p.acf.series+'</div>'
      +'<div class="card-store">'+p.acf.store+'</div>'
      +stHTML+'<div class="'+dlCls+'">'+dlText+'</div></div></a>';
  }).join('');
}
function renderResult(posts) {
  var todayStr = new Date().toISOString().slice(0,10);
  var results = posts.filter(function(p) {
    if (!p.acf.result_date) return false;
    var d = parseDate(p.acf.result_date);
    if (!d) return false;
    return d.toISOString().slice(0,10) === todayStr;
  });
  var list = document.getElementById('result-list');
  if (!results.length) {
    list.innerHTML = '<div class="no-data">本日の発表予定はありません</div>';
    return;
  }
  results.sort(function(a,b) {
    return parseDate(a.acf.result_date) - parseDate(b.acf.result_date);
  });
  list.innerHTML = results.map(function(p) {
    var cat = p.acf.category || 'other';
    var d = parseDate(p.acf.result_date);
    var timeStr = d ? ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2) : '';
    return '<a href="'+p.link+'" class="result-row">'
      +'<div class="urg-info">'
      +'<div class="result-name">'+(CAT_EMOJI[cat]||'📌')+' '+p.acf.series+'</div>'
      +'<div class="result-store">'+p.acf.store+(p.acf.prefecture ? ' 📍'+p.acf.prefecture : '')+'</div>'
      +'</div>'
      +'<div class="result-time">🏆 '+timeStr+'</div>'
      +'</a>';
  }).join('');
}
function renderUrgent(posts) {
  var urgent = posts.filter(function(p){ return getStatus(p)==='urgent'; });
  var list = document.getElementById('urgent-list');
  if (!urgent.length) { list.innerHTML = '<div class="no-data">現在締切間近の抽選はありません</div>'; return; }
  list.innerHTML = urgent.map(function(p) {
    var e = parseDate(p.acf.end_date);
    var pct = e ? Math.max(2, Math.min(100, (e.getTime()-Date.now()) / (2*86400000) * 100)) : 50;
    var cat = p.acf.category || 'other';
    return '<a href="'+p.link+'" class="urgent-row">'
      +'<div class="urg-info"><div class="urg-name">'+(CAT_EMOJI[cat]||'📌')+' '+p.acf.series+' — '+p.acf.store+'</div>'
      +'<div class="urg-store">'+(CAT_LABEL[cat]||'その他')+' / オンライン</div>'
      +'<div class="urg-bar-wrap"><div class="urg-bar" style="width:'+pct+'%"></div></div></div>'
      +'<div class="urg-right"><div class="urg-time">残り'+getRemain(p)+'</div></div></a>';
  }).join('');
}
var calPosts = [];
function renderCalendar(posts) {
  calPosts = posts;
  var now = new Date(), year = now.getFullYear(), month = now.getMonth();
  document.getElementById('cal-month').textContent = year+'年'+(month+1)+'月';
  var dots = {};
  posts.forEach(function(p) {
    var e = parseDate(p.acf.end_date);
    if (!e || e.getFullYear()!==year || e.getMonth()!==month) return;
    var d = e.getDate();
    if (!dots[d]) dots[d] = [];
    dots[d].push({ cat: p.acf.category, post: p });
  });
  var firstDay = new Date(year,month,1).getDay();
  var daysInMonth = new Date(year,month+1,0).getDate();
  var today = now.getDate();
  var html = '';
  for (var i=0;i<firstDay;i++) html += '<div class="cal-day cal-day-empty"></div>';
  for (var d=1;d<=daysInMonth;d++) {
    var tc = d===today ? ' cal-day-today' : '';
    var he = dots[d] ? ' cal-day-has-event' : '';
    var oc = dots[d] ? ' onclick="openCalModal('+d+')"' : '';
    var dotHTML = '';
    if (dots[d]) {
      dotHTML = '<div class="cal-dots">';
      dots[d].forEach(function(item) {
        dotHTML += '<div class="cal-dot '+(item.cat==='pokeca'?'dot-pk':item.cat==='sneaker'?'dot-sn':'dot-ot')+'"></div>';
      });
      dotHTML += '</div>';
    }
    html += '<div class="cal-day'+tc+he+'"'+oc+'><div class="cal-num">'+d+'</div>'+dotHTML+'</div>';
  }
  document.getElementById('cal-grid').innerHTML = html;
}
function openCalModal(day) {
  var now = new Date(), year = now.getFullYear(), month = now.getMonth();
  var matched = calPosts.filter(function(p) {
    var e = parseDate(p.acf.end_date);
    return e && e.getFullYear()===year && e.getMonth()===month && e.getDate()===day;
  });
  document.getElementById('cal-modal-title').textContent = (month+1)+'月'+day+'日の抽選';
  var html = matched.length ? matched.map(function(p) {
    var cat = p.acf.category||'other', e = parseDate(p.acf.end_date);
    var timeStr = e ? '締切: '+(e.getMonth()+1)+'/'+e.getDate()+' '+('0'+e.getHours()).slice(-2)+':'+('0'+e.getMinutes()).slice(-2) : '締切未定';
    return '<a href="'+p.link+'" class="cal-modal-item">'
      +'<div class="cal-modal-emoji">'+(CAT_EMOJI[cat]||'📌')+'</div>'
      +'<div class="cal-modal-info"><div class="cal-modal-name">'+p.acf.series+'</div>'
      +'<div class="cal-modal-store">'+p.acf.store+'</div>'
      +'<div class="cal-modal-time">'+timeStr+'</div></div>'
      +'<div class="cal-modal-arrow">詳細 →</div></a>';
  }).join('') : '<div class="cal-modal-empty">この日の抽選情報はありません</div>';
  document.getElementById('cal-modal-body').innerHTML = html;
  document.getElementById('cal-modal').classList.add('open');
}
function closeCalModal(e) {
  if (!e || e.target.classList.contains('cal-modal-overlay'))
    document.getElementById('cal-modal').classList.remove('open');
}
fetch(WP_API+'?per_page=100&_embed')
  .then(function(r){ return r.json(); })
  .then(function(data) {
    allPosts = data.filter(function(p){ return p.acf && p.acf.category; });
    allPosts.forEach(function(p) {
      try {
        var media = p._embedded && p._embedded['wp:featuredmedia'];
        var m = media && media[0];
        p._thumbUrl = (m && m.source_url) ? m.source_url : '';
      } catch(e) { p._thumbUrl = ''; }
    });
    allPosts.sort(function(a,b){ return b.id - a.id; });
    renderTicker(allPosts);
    renderNewArrivals(allPosts);
    renderUrgent(allPosts);
    renderResult(allPosts);
    renderCalendar(allPosts);
  })
  .catch(function() {
    document.getElementById('new-track').innerHTML = '<div class="new-loading">データの取得に失敗しました</div>';
    document.getElementById('urgent-list').innerHTML = '<div class="no-data">データの取得に失敗しました</div>';
  });
setInterval(function() {
  if (allPosts.length) { renderTicker(allPosts); renderNewArrivals(allPosts); renderUrgent(allPosts); renderResult(allPosts); }
}, 60000);
</script>

<?php get_footer(); ?>
