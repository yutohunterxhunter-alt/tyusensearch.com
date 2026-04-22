<?php get_header(); ?>
<?php
if (have_posts()) { the_post(); }
$id     = get_the_ID();
$series = get_post_meta($id, 'series', true)      ?: '';
$store  = get_post_meta($id, 'store', true)       ?: '';
$cat    = get_post_meta($id, 'category', true)    ?: 'other';
$start  = get_post_meta($id, 'start_date', true)  ?: '';
$end    = get_post_meta($id, 'end_date', true)    ?: '';
$url    = get_post_meta($id, 'lottery_url', true) ?: '';
$note   = get_post_meta($id, 'note', true)        ?: '';
$result = get_post_meta($id, 'result_date', true) ?: '';

$cat_labels = ['pokeca'=>'ポケカ','sneaker'=>'スニーカー','other'=>'その他'];
$cat_emojis = ['pokeca'=>'🃏','sneaker'=>'👟','other'=>'🎮'];
$cat_colors = ['pokeca'=>'#f5c518','sneaker'=>'#3b9eff','other'=>'#a78bfa'];
$label = $cat_labels[$cat] ?? 'その他';
$emoji = $cat_emojis[$cat] ?? '🎮';
$color = $cat_colors[$cat] ?? '#a78bfa';
$thumb = get_the_post_thumbnail_url($id, 'large') ?: '';
$back  = $cat==='pokeca' ? home_url('/pokeca/') : ($cat==='sneaker' ? home_url('/sneaker/') : home_url('/other/'));

$now = time();
$end_ts   = $end   ? jst_strtotime($end)   : 0;
$start_ts = $start ? jst_strtotime($start) : 0;
if ($end_ts && $now > $end_ts)           { $status='終了';       $sc='st-expired'; }
elseif ($start_ts && $now < $start_ts)   { $status='近日開始';   $sc='st-upcoming'; }
elseif ($end_ts && ($end_ts-$now)<86400) { $status='⚡締切間近'; $sc='st-urgent'; }
else                                      { $status='受付中';     $sc='st-active'; }

function fmt_ld($str) {
    if (!$str) return '未定';
    $dt = new DateTime($str, new DateTimeZone('Asia/Tokyo'));
    return $dt->format('n/j H:i');
}
?>
<style>
:root{--bg:#0d1117;--surface:#161b22;--surface2:#1c2128;--border:#30363d;--text:#e6edf3;--muted:#7d8590;--red:#f85149;--blue:#58a6ff;--accent:#f5c518;--font:'Zen Kaku Gothic New',sans-serif;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;padding-bottom:80px;}
.det-header{background:var(--surface);border-bottom:1px solid var(--border);padding:12px 16px;position:sticky;top:0;z-index:100;display:flex;align-items:center;gap:12px;}
.back-btn{color:var(--muted);text-decoration:none;font-size:13px;}
.site-tag{font-size:9px;letter-spacing:2px;color:var(--accent);border:1px solid var(--accent);padding:2px 7px;border-radius:2px;text-decoration:none;margin-left:auto;}
.hero-img{width:100%;height:280px;object-fit:contain;display:block;background:var(--surface2);}
.hero-empty{width:100%;height:160px;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:64px;}
.detail{padding:20px 16px;}
.cat-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;margin-bottom:10px;}
.det-title{font-size:22px;font-weight:900;line-height:1.3;margin-bottom:4px;}
.det-store{font-size:14px;color:var(--muted);margin-bottom:16px;}
.badge{display:inline-flex;align-items:center;font-size:12px;font-weight:700;padding:5px 12px;border-radius:6px;margin-bottom:20px;}
.st-active{background:rgba(245,197,24,.1);color:var(--accent);border:1px solid var(--accent);}
.st-urgent{background:rgba(248,81,73,.1);color:var(--red);border:1px solid #f8514940;}
.st-expired{background:#ffffff0a;color:var(--muted);border:1px solid var(--border);}
.st-upcoming{background:rgba(88,166,255,.1);color:var(--blue);border:1px solid #58a6ff40;}
.apply-btn{display:flex;align-items:center;justify-content:center;gap:8px;background:var(--accent);color:#000;font-weight:900;font-size:16px;padding:14px 20px;border-radius:10px;text-decoration:none;margin-bottom:16px;}
.info-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-label{font-size:12px;color:var(--muted);}
.info-val{font-size:14px;font-weight:700;}
.info-val.urgent{color:var(--red);}
.note-box{background:var(--surface);border:1px solid var(--border);border-left:3px solid var(--accent);border-radius:8px;padding:14px;font-size:13px;color:var(--muted);line-height:1.7;}
.bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#1a2030;border-top:1px solid #2a3448;display:flex;width:100%;}
.nav-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0 10px;gap:2px;font-size:9px;color:#7a8aaa;background:none;border:none;font-family:var(--font);cursor:pointer;text-decoration:none;}
.nav-btn.active{color:#00f0c0;}
.nav-ico{font-size:18px;line-height:1;display:block;}
</style>

<div class="det-header">
  <a href="<?php echo esc_url($back); ?>" class="back-btn">← 一覧に戻る</a>
  <a href="<?php echo home_url('/'); ?>" class="site-tag">抽選サーチ</a>
</div>

<?php if ($thumb): ?>
  <img class="hero-img" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($series); ?>">
<?php else: ?>
  <div class="hero-empty"><?php echo $emoji; ?></div>
<?php endif; ?>

<div class="detail">
  <span class="cat-badge" style="background:<?php echo $color; ?>20;color:<?php echo $color; ?>;border:1px solid <?php echo $color; ?>40;">
    <?php echo $emoji . ' ' . esc_html($label); ?>
  </span>
  <div class="det-title"><?php echo esc_html($series ?: get_the_title()); ?></div>
  <div class="det-store"><?php echo esc_html($store); ?></div>
  <span class="badge <?php echo $sc; ?>"><?php echo $status; ?></span>

  <?php if ($url): ?>
  <a href="<?php echo esc_url($url); ?>" class="apply-btn" target="_blank" rel="noopener">今すぐ応募する →</a>
  <?php endif; ?>

  <div class="info-card">
    <div class="info-row">
      <span class="info-label">応募開始</span>
      <span class="info-val"><?php echo fmt_ld($start); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">応募締切</span>
      <span class="info-val <?php echo $sc==='st-urgent'?'urgent':''; ?>"><?php echo fmt_ld($end); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">カテゴリ</span>
      <span class="info-val"><?php echo $emoji . ' ' . esc_html($label); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">販売店舗</span>
      <span class="info-val"><?php echo esc_html($store); ?></span>
    </div>
  </div>

  <?php if ($result): ?>
  <div class="note-box" style="border-left-color:#f5c518;">🏆 抽選発表日：<?php
    $result_ts = jst_strtotime($result);
    // 時間が00:00の場合は日付のみ表示
    echo (date('H:i', $result_ts) === '00:00')
        ? jst_date('Y年n月j日', $result_ts)
        : jst_date('Y年n月j日 H:i', $result_ts);
  ?></div>
  <?php endif; ?>
  <?php if ($note): ?>
  <div class="note-box">📝 <?php echo esc_html($note); ?></div>
  <?php endif; ?>
</div>

<nav class="bottom-nav">
  <a class="nav-btn" href="<?php echo home_url('/'); ?>"><span class="nav-ico">🏠</span>ホーム</a>
  <a class="nav-btn <?php echo $cat==='pokeca'?'active':''; ?>" href="<?php echo home_url('/pokeca/'); ?>"><span class="nav-ico">🃏</span>ポケカ</a>
  <a class="nav-btn <?php echo $cat==='sneaker'?'active':''; ?>" href="<?php echo home_url('/sneaker/'); ?>"><span class="nav-ico">👟</span>スニーカー</a>
  <a class="nav-btn <?php echo $cat==='other'?'active':''; ?>" href="<?php echo home_url('/other/'); ?>"><span class="nav-ico">🎮</span>その他</a>
  <a class="nav-btn" href="<?php echo home_url('/'); ?>#cal"><span class="nav-ico">🗓</span>カレンダー</a>
</nav>

<?php wp_footer(); ?>
