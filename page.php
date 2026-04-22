<?php get_header(); ?>

<style>
.page-content { padding: 24px 16px; max-width: 680px; margin: 0 auto; }
.page-content h1 { font-size: 20px; font-weight: 900; color: #00f0c0; margin-bottom: 20px; }
.page-content .entry-content { color: #eef2ff; line-height: 1.9; font-size: 14px; }
.page-content .entry-content p { margin-bottom: 14px; color: #eef2ff; }
.page-content .entry-content a { color: #58a6ff; }
.page-content .entry-content input,
.page-content .entry-content textarea,
.page-content .entry-content select {
  background: #1e2635 !important;
  color: #eef2ff !important;
  border: 1px solid #2a3448 !important;
  border-radius: 8px !important;
  padding: 10px 12px !important;
  width: 100% !important;
  font-size: 14px !important;
  margin-bottom: 10px !important;
}
.page-content .entry-content input[type="submit"],
.page-content .entry-content button[type="submit"] {
  background: #00f0c0 !important;
  color: #000 !important;
  font-weight: 900 !important;
  border: none !important;
  cursor: pointer !important;
  width: auto !important;
  padding: 12px 28px !important;
}
/* WPForms / Contact Form 7 */
.wpcf7-form label { color: #eef2ff !important; }
.wpcf7-form input, .wpcf7-form textarea { background: #1e2635 !important; color: #eef2ff !important; border: 1px solid #2a3448 !important; }
</style>

<div class="page-content">
  <?php while (have_posts()) : the_post(); ?>
    <h1><?php the_title(); ?></h1>
    <div class="entry-content">
      <?php the_content(); ?>
    </div>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
