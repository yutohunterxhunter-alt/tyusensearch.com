<?php
/*
Template Name: スニーカー商品カード一覧
*/
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>スニーカー 抽選中の商品一覧｜抽選サーチ</title>
<meta property="og:title" content="スニーカー 抽選中の商品一覧｜抽選サーチ">
<meta property="og:description" content="スニーカーの抽選中商品を一覧表示。商品を選んで店舗別の抽選情報をチェック。">
<meta property="og:url" content="https://tyusensearch.com/sneaker-series/">
<meta property="og:type" content="website">
<meta property="og:image" content="https://tyusensearch.com/ogp/ogp-sneaker.jpg">
<meta name="twitter:card" content="summary_large_image">
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;700;900&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{
  --bg:#0d1117;--s1:#161b22;--s2:#1c2128;--bd:#30363d;
  --tx:#e6edf3;--mu:#7d8590;
  --green:#3fb950;--red:#f85149;--yellow:#f5c518;--blue:#58a6ff;
  --font:'Zen Kaku Gothic New',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
body{background:var(--bg);color:var(--tx);font-family:var(--font);min-height:100vh;font-size:14px;padding-bottom:80px;}

.hd{background:var(--s1);border-bottom:1px solid var(--bd);padding:10px 14px;position:sticky;top:0;z-index:100;}
.hd-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.site-tag{font-size:9px;letter-spacing:2px;color:var(--yellow);border:1px solid var(--yellow);padding:2px 7px;border-radius:2px;text-decoration:none;}
.hd h1{font-size:16px;font-weight:900;}
.hd h1 span{color:var(--yellow);}

.search-bar{display:flex;gap:8px;padding:10px 14px;background:var(--s1);border-bottom:1px solid var(--bd);}
.search-inp{flex:1;background:var(--s2);border:1px solid var(--bd);border-radius:8px;padding:9px 12px;color:var(--tx);font-size:13px;font-family:var(--font);outline:none;}
.search-inp:focus{border-color:var(--yellow);}
.search-inp::placeholder{color:var(--mu);}

.filter-bar{display:flex;gap:6px;padding:8px 14px;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid var(--bd);background:var(--s1);}
.filter-bar::-webkit-scrollbar{display:none;}
.f-btn{flex-shrink:0;padding:4px 12px;border-radius:20px;border:1px solid var(--bd);background:transparent;color:var(--mu);font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;}
.f-btn.active{background:rgba(245,197,24,.12);border-color:var(--yellow);color:var(--yellow);}
.count-bar{padding:6px 14px;font-size:11px;color:var(--mu);background:var(--s1);border-bottom:1px solid var(--bd);}

/* カードグリッド */
.card-grid{padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(min-width:480px){.card-grid{grid-template-columns:repeat(3,1fr);}}

.series-card{background:var(--s1);border:1px solid var(--bd);border-radius:12px;overflow:hidden;cursor:pointer;text-decoration:none;color:var(--tx);display:block;transition:border-color .15s,transform .1s;}
.series-card:active{transform:scale(.97);}
.series-card.urgent{border-color:rgba(248,81,73,.5);}
.series-card.upcoming{border-color:rgba(88,166,255,.4);}

.card-thumb{width:100%;aspect-ratio:1/1;object-fit:cover;background:var(--s2);display:block;}
.card-thumb-ph{width:100%;aspect-ratio:1/1;background:var(--s2);display:flex;align-items:center;justify-content:center;font-size:40px;}

.card-body{padding:10px;}
.card-name{font-size:12px;font-weight:900;line-height:1.4;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-meta{display:flex;flex-wrap:wrap;gap:4px;align-items:center;}
.badge{display:inline-flex;align-items:center;font-size:9px;font-weight:900;padding:2px 6px;border-radius:3px;white-space:nowrap;}
.b-active{background:rgba(245,197,24,.12);color:var(--yellow);border:1px solid rgba(245,197,24,.3);}
.b-urgent{background:rgba(248,81,73,.12);color:var(--red);border:1px solid rgba(248,81,73,.3);}
.b-upcoming{background:rgba(88,166,255,.1);color:var(--blue);border:1px solid rgba(88,166,255,.3);}
.b-expired{background:rgba(255,255,255,.05);color:var(--mu);border:1px solid var(--bd);}
.card-stores{font-size:10px;color:var(--mu);margin-top:4px;}
.card-deadline{font-size:10px;color:var(--mu);margin-top:2px;}
.card-deadline.urgent{color:var(--red);font-weight:700;}

/* バー */
.card-bar{height:3px;background:var(--bd);}
.card-bar-fill{height:100%;transition:width .3s;}
.bar-g{background:var(--green);}
.bar-y{background:var(--yellow);}
.bar-r{background:var(--red);}

.empty-msg{text-align:center;padding:40px;color:var(--mu);grid-column:1/-1;}

/* ボトムナビ */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1a2030;border-top:1px solid #2a3448;display:flex;}
.nav-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0 10px;gap:2px;font-size:9px;color:#7a8aaa;background:none;border:none;font-family:var(--font);cursor:pointer;text-decoration:none;}
.nav-btn.active{color:#f5c518;}
.nav-ico{font-size:18px;line-height:1;display:block;}
</style>
</head>
<body>

<div class="hd">
  <div class="hd-top">
    <a href="<?php echo home_url('/'); ?>" class="site-tag">抽選サーチ</a>
    <span style="font-size:11px;color:var(--mu);" id="last-upd"></span>
  </div>
  <h1>👟 スニーカー<span> 商品一覧</span></h1>
</div>

<div class="search-bar">
  <input type="text" class="search-inp" id="search-inp" placeholder="🔍 商品名で検索..." oninput="applyFilter()">
</div>

<div class="filter-bar">
  <button class="f-btn active" data-f="all" onclick="setFilter(this,'all')">すべて</button>
  <button class="f-btn" data-f="active" onclick="setFilter(this,'active')">受付中</button>
  <button class="f-btn" data-f="urgent" onclick="setFilter(this,'urgent')">⚡締切間近</button>
  <button class="f-btn" data-f="upcoming" onclick="setFilter(this,'upcoming')">近日開始</button>
</div>
<div class="count-bar" id="count-bar"></div>

<div class="card-grid" id="card-grid">
  <div class="empty-msg">読み込み中...</div>
</div>

<?php wp_footer(); ?>

<nav class="bottom-nav">
  <a href="<?php echo home_url('/'); ?>" class="nav-btn"><span class="nav-ico">🏠</span>ホーム</a>
  <a href="<?php echo home_url('/sneaker-series/'); ?>" class="nav-btn"><span class="nav-ico">🃏</span>ポケカ</a>
  <a href="<?php echo home_url('/sneaker-series/'); ?>" class="nav-btn active"><span class="nav-ico">👟</span>スニーカー</a>
  <a href="<?php echo home_url('/other-series/'); ?>" class="nav-btn"><span class="nav-ico">🎮</span>その他</a>
</nav>

<script>
var WP_API = '<?php echo home_url('/wp-json/wp/v2/lottery'); ?>';
var LIST_URL = '<?php echo home_url('/sneaker/'); ?>';
var CAT = 'sneaker';
var allData = [];
var activeFilter = 'all';
var searchQ = '';

function parseWpDate(s) {
  if (!s) return null;
  s = s.replace(/\//g, '-');
  return new Date(s.indexOf('T') !== -1 ? s : s.replace(' ', 'T'));
}

function getStatus(e) {
  var now = new Date();
  if (!e.end) return 'active';
  if (e.end < now) return 'expired';
  if (e.start && e.start > now) return 'upcoming';
  if ((e.end - now) < 86400000) return 'urgent';
  return 'active';
}

function fmtDt(d) {
  if (!d) return '未定';
  return (d.getMonth()+1)+'/'+d.getDate()+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
}

function getPct(e) {
  if (!e.end) return 100;
  var now = Date.now(), en = e.end.getTime();
  var diff = en - now;
  if (diff <= 0) return 0;
  return Math.max(2, Math.min(100, diff / (120*3600000) * 100));
}

function loadData() {
  fetch(WP_API + '?per_page=100&_fields=id,acf,featured_media,link')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var entries = data
        .filter(function(p){ return p.acf && p.acf.category === CAT; })
        .map(function(p) {
          return {
            id: p.id,
            series: p.acf.series || '',
            store: p.acf.store || '',
            pref: p.acf.prefecture || '',
            start: parseWpDate(p.acf.start_date),
            end: parseWpDate(p.acf.end_date),
            url: p.acf.lottery_url || '',
            link: p.link || '',
            thumb: '',
            featured_media: p.featured_media || 0
          };
        });

      // サムネイル取得
      var mediaIds = entries.map(function(e){ return e.featured_media; }).filter(function(id){ return id > 0; });
      if (mediaIds.length > 0) {
        fetch('<?php echo home_url('/wp-json/wp/v2/media'); ?>?include='+mediaIds.join(',')+'&_fields=id,source_url,media_details')
          .then(function(r){ return r.json(); })
          .then(function(md) {
            var map = {};
            md.forEach(function(m){
              map[m.id] = (m.media_details&&m.media_details.sizes&&m.media_details.sizes.medium)
                ? m.media_details.sizes.medium.source_url : m.source_url;
            });
            entries.forEach(function(e){ if (e.featured_media && map[e.featured_media]) e.thumb = map[e.featured_media]; });
            allData = entries;
            render();
          }).catch(function(){ allData = entries; render(); });
      } else {
        allData = entries;
        render();
      }
      document.getElementById('last-upd').textContent = new Date().toLocaleTimeString('ja-JP')+' 更新';
    })
    .catch(function(){
      document.getElementById('card-grid').innerHTML = '<div class="empty-msg">データの取得に失敗しました</div>';
    });
}

function render() {
  // シリーズ別グループ化（終了済み除外）
  var groups = {}, order = [];
  allData.forEach(function(e) {
    var st = getStatus(e);
    if (st === 'expired') return;
    var key = e.series || '（未設定）';
    if (!groups[key]) { groups[key] = { entries: [], thumb: '', series: key }; order.push(key); }
    groups[key].entries.push(e);
    if (!groups[key].thumb && e.thumb) groups[key].thumb = e.thumb;
  });

  // フィルタ・検索
  var filtered = order.filter(function(key) {
    var g = groups[key];
    if (searchQ && key.toLowerCase().indexOf(searchQ) === -1) return false;
    if (activeFilter !== 'all') {
      var hasMatch = g.entries.some(function(e){ return getStatus(e) === activeFilter; });
      if (!hasMatch) return false;
    }
    return true;
  });

  // ソート：urgent→active→upcoming
  var so = {urgent:0,active:1,upcoming:2};
  filtered.sort(function(a, b) {
    var ga = groups[a], gb = groups[b];
    var sa = Math.min.apply(null, ga.entries.map(function(e){ return so[getStatus(e)]||99; }));
    var sb = Math.min.apply(null, gb.entries.map(function(e){ return so[getStatus(e)]||99; }));
    if (sa !== sb) return sa - sb;
    var ea = Math.min.apply(null, ga.entries.map(function(e){ return e.end ? e.end.getTime() : Infinity; }));
    var eb = Math.min.apply(null, gb.entries.map(function(e){ return e.end ? e.end.getTime() : Infinity; }));
    return ea - eb;
  });

  document.getElementById('count-bar').textContent = filtered.length + '商品';

  if (!filtered.length) {
    document.getElementById('card-grid').innerHTML = '<div class="empty-msg">該当する商品がありません</div>';
    return;
  }

  var badgeMap = {
    active:'<span class="badge b-active">受付中</span>',
    urgent:'<span class="badge b-urgent">⚡締切間近</span>',
    upcoming:'<span class="badge b-upcoming">近日開始</span>'
  };

  var html = '';
  filtered.forEach(function(key) {
    var g = groups[key];
    // 代表エントリ（一番緊急なもの）
    var sortedEntries = g.entries.slice().sort(function(a,b){
      var sa = so[getStatus(a)]||99, sb = so[getStatus(b)]||99;
      if (sa !== sb) return sa - sb;
      var ea = a.end ? a.end.getTime() : Infinity;
      var eb = b.end ? b.end.getTime() : Infinity;
      return ea - eb;
    });
    var rep = sortedEntries[0];
    var repSt = getStatus(rep);
    var pct = getPct(rep);
    var barCls = pct > 60 ? 'bar-g' : pct > 25 ? 'bar-y' : 'bar-r';
    var cardCls = repSt === 'urgent' ? ' urgent' : repSt === 'upcoming' ? ' upcoming' : '';

    // カードを押すと一覧ページへシリーズ絞り込みで遷移
    var dest = LIST_URL + '?series=' + encodeURIComponent(key);

    var thumbHtml = g.thumb
      ? '<img class="card-thumb" src="'+g.thumb+'" alt="'+escHtml(key)+'" loading="lazy">'
      : '<div class="card-thumb-ph">🃏</div>';

    var deadlineText = rep.end
      ? (repSt === 'urgent' ? '⏰ '+fmtDt(rep.end)+'締切' : fmtDt(rep.end)+'締切')
      : '';
    var deadlineCls = repSt === 'urgent' ? ' urgent' : '';

    html += '<a class="series-card'+cardCls+'" href="'+dest+'">';
    html += thumbHtml;
    if (repSt === 'active' || repSt === 'urgent') {
      html += '<div class="card-bar"><div class="card-bar-fill '+barCls+'" style="width:'+pct+'%"></div></div>';
    }
    html += '<div class="card-body">';
    html += '<div class="card-name">'+escHtml(key)+'</div>';
    html += '<div class="card-meta">'+(badgeMap[repSt]||'')+'</div>';
    html += '<div class="card-stores">'+g.entries.length+'店舗で抽選中</div>';
    if (deadlineText) html += '<div class="card-deadline'+deadlineCls+'">'+deadlineText+'</div>';
    html += '</div></a>';
  });

  document.getElementById('card-grid').innerHTML = html;
}

function setFilter(btn, f) {
  activeFilter = f;
  document.querySelectorAll('.f-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  render();
}
function applyFilter() {
  searchQ = document.getElementById('search-inp').value.trim().toLowerCase();
  render();
}
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

loadData();
setInterval(loadData, 60000);
</script>
</body>
</html>
