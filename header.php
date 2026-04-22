<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9050998756903244" crossorigin="anonymous"></script>
<?php
if (is_singular('lottery')) {
    $pid    = get_the_ID();
    $series = get_post_meta($pid, 'series', true) ?: get_the_title();
    $store  = get_post_meta($pid, 'store', true)  ?: '';
    $end    = get_post_meta($pid, 'end_date', true) ?: '';
    $end_str = $end ? date('n月j日 H:i', strtotime($end)) : '未定';
    $thumb  = get_the_post_thumbnail_url($pid, 'large') ?: '';
    $page_url = get_permalink($pid);
    $og_title = $series . ($store ? '｜'.$store : '') . '｜抽選サーチ';
    $og_desc  = "{$series}の抽選情報。{$store}での応募締切は{$end_str}。";
    echo '<meta property="og:type" content="article" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($og_desc) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($page_url) . '" />' . "\n";
    echo '<meta property="og:site_name" content="抽選サーチ" />' . "\n";
    if ($thumb) {
        echo '<meta property="og:image" content="' . esc_url($thumb) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($thumb) . '" />' . "\n";
    } else {
        echo '<meta name="twitter:card" content="summary" />' . "\n";
    }
    echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . '" />' . "\n";
    echo '<meta name="twitter:site" content="@tyusensearch" />' . "\n";
}
?>
<?php wp_head(); ?>
<style>
:root {
  --bg: #0f1419; --s1: #1a2030; --s2: #1e2635; --s3: #252d3d; --border: #2a3448;
  --text: #eef2ff; --muted: #7a8aaa;
  --neon: #00f0c0; --urgent: #ff3e6c;
  --pokeca: #f5c518; --sneaker: #3b9eff; --other: #a78bfa;
  --font-d: 'Bebas Neue', cursive;
  --font-b: 'Noto Sans JP', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html { scroll-behavior: smooth; }
body {
  background: #0f1419;
  background: radial-gradient(ellipse 100% 50% at 50% 0%, #152030 0%, #0f1419 55%);
  color: var(--text); font-family: var(--font-b); font-size: 14px; line-height: 1.6;
  padding-bottom: 72px;
}
.topbar {
  position: sticky; top: 0; z-index: 100;
  background: var(--s1); border-bottom: 1px solid var(--border);
  height: 50px; display: flex; align-items: center;
  padding: 0 16px; justify-content: space-between;
}
.logo { font-family: var(--font-d); font-size: 22px; letter-spacing: 2px; color: var(--text); text-decoration: none; }
.logo span { color: var(--neon); }
.live-badge { display: flex; align-items: center; gap: 6px; font-size: 10px; color: var(--muted); }
.live-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--urgent); animation: blink 1.4s ease infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
.bottom-nav { position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; z-index: 9999 !important; background: var(--s1) !important; border-top: 1px solid var(--border) !important; display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
.nav-btn { flex: 1 1 0 !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; padding: 8px 0 10px !important; gap: 2px !important; font-size: 9px !important; color: var(--muted) !important; background: none !important; border: none !important; font-family: var(--font-b) !important; cursor: pointer !important; min-width: 0 !important; width: auto !important; text-decoration: none !important; }
.nav-btn.active { color: var(--neon) !important; }
.nav-ico { font-size: 18px !important; line-height: 1 !important; display: block !important; }
</style>
</head>
<body>
<header class="topbar">
  <a class="logo" href="<?php echo home_url('/'); ?>">抽選<span>サーチ</span></a>
  <div class="live-badge"><div class="live-dot"></div>LIVE更新中</div>
</header>
