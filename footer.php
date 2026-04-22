<!-- SNSフォローバナー -->
<div style="margin:16px 16px 0;background:linear-gradient(135deg,#1a2030,#252d3d);border:1px solid #2a3448;border-radius:12px;padding:16px;">
  <div style="text-align:center;font-size:12px;color:#7a8aaa;margin-bottom:12px;letter-spacing:1px;">📣 最新の抽選情報をXでチェック！</div>
  <a href="https://x.com/tyusensearch" target="_blank" rel="noopener"
     style="display:flex;align-items:center;justify-content:center;gap:10px;background:#000;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:700;border:1px solid #333;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    @tyusensearch をフォローする
  </a>
</div>

<footer style="padding:24px 16px 16px;border-top:1px solid #2a3448;margin-top:16px;">
  <div style="text-align:center;margin-bottom:12px;">
    <span style="font-family:'Bebas Neue',cursive;font-size:18px;letter-spacing:2px;color:#eef2ff;">抽選<span style="color:#00f0c0;">サーチ</span></span>
  </div>
  <div style="display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:12px;">
    <a href="<?php echo home_url('/privacy-policy/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">プライバシーポリシー</a>
    <a href="<?php echo home_url('/contact/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">お問い合わせ</a>
    <a href="<?php echo home_url('/about/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">運営者情報</a>
    <a href="<?php echo home_url('/pokeca/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">ポケカ抽選一覧</a>
    <a href="<?php echo home_url('/sneaker/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">スニーカー抽選一覧</a>
    <a href="<?php echo home_url('/other/'); ?>" style="font-size:12px;color:#7a8aaa;text-decoration:none;">その他抽選一覧</a>
  </div>
  <p style="text-align:center;font-size:11px;color:#7a8aaa;">© <?php echo date('Y'); ?> 抽選サーチ All Rights Reserved.</p>
</footer>

<nav class="bottom-nav">
  <a class="nav-btn <?php echo is_front_page() ? 'active' : ''; ?>" href="<?php echo home_url('/'); ?>"><span class="nav-ico">🏠</span>ホーム</a>
  <a class="nav-btn <?php echo is_page('pokeca-series') ? 'active' : ''; ?>" href="<?php echo home_url('/pokeca-series/'); ?>"><span class="nav-ico">🃏</span>ポケカ</a>
  <a class="nav-btn <?php echo is_page('sneaker-series') ? 'active' : ''; ?>" href="<?php echo home_url('/sneaker-series/'); ?>"><span class="nav-ico">👟</span>スニーカー</a>
  <a class="nav-btn <?php echo is_page('other-series') ? 'active' : ''; ?>" href="<?php echo home_url('/other-series/'); ?>"><span class="nav-ico">🎮</span>その他</a>
  <a class="nav-btn" href="#cal"><span class="nav-ico">🗓</span>カレンダー</a>
</nav>

<?php wp_footer(); ?>
</body>
</html>
