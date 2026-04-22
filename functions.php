<?php
date_default_timezone_set('Asia/Tokyo');

// 日付文字列を安全にタイムスタンプ変換（JST基準・スラッシュ区切り対応）
function jst_strtotime($str) {
    if (!$str) return 0;
    $str = str_replace('/', '-', $str);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $str, new DateTimeZone('Asia/Tokyo'));
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $str, new DateTimeZone('Asia/Tokyo'));
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d\TH:i', $str, new DateTimeZone('Asia/Tokyo'));
    if (!$dt) return 0;
    return $dt->getTimestamp();
}

// JST基準で日付フォーマット
function jst_date($format, $timestamp) {
    if (!$timestamp) return '';
    $dt = new DateTime('@'.$timestamp);
    $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
    return $dt->format($format);
}

// シリーズ名からハッシュタグを自動抽出
function lottery_extract_hashtags($series) {
    $dict = [
        // ポケカ系
        'ピカチュウ'     => '#ピカチュウ',
        'リザードン'     => '#リザードン',
        'イーブイ'       => '#イーブイ',
        'ミュウ'         => '#ミュウ',
        'ミュウツー'     => '#ミュウツー',
        'カビゴン'       => '#カビゴン',
        'ゲンガー'       => '#ゲンガー',
        'ルカリオ'       => '#ルカリオ',
        'マリィ'         => '#マリィ',
        'サーナイト'     => '#サーナイト',
        'スカーレット'   => '#スカーレット',
        'バイオレット'   => '#バイオレット',
        'テラスタル'     => '#テラスタル',
        '151'            => '#ポケモン151',
        // スニーカー系
        'Nike'           => '#Nike',
        'NIKE'           => '#Nike',
        'Jordan'         => '#Jordan #AirJordan',
        'Dunk'           => '#NikeDunk',
        'Air Force'      => '#AirForce1',
        'Air Max'        => '#AirMax',
        'adidas'         => '#adidas',
        'Adidas'         => '#adidas',
        'Yeezy'          => '#Yeezy',
        'New Balance'    => '#NewBalance',
        'ASICS'          => '#ASICS',
        'asics'          => '#ASICS',
        'Puma'           => '#Puma',
        'PUMA'           => '#Puma',
        'Vans'           => '#Vans',
        'Converse'       => '#Converse',
        'SNKRS'          => '#SNKRS',
        // その他系
        'ワンピース'     => '#ワンピース',
        '鬼滅'           => '#鬼滅の刃',
        '呪術'           => '#呪術廻戦',
        'ドラゴンボール' => '#ドラゴンボール',
        'ガンダム'       => '#ガンダム',
        'ディズニー'     => '#Disney',
        'Disney'         => '#Disney',
        'サンリオ'       => '#サンリオ',
        'ハローキティ'   => '#HelloKitty',
        'スヌーピー'     => '#Snoopy',
        '一番くじ'       => '#一番くじ',
        'フィギュア'     => '#フィギュア',
        'トレカ'         => '#トレカ',
    ];
    $tags = [];
    foreach ($dict as $keyword => $tag) {
        if (mb_stripos($series, $keyword) !== false) {
            foreach (explode(' ', $tag) as $t) {
                if ($t && !in_array($t, $tags)) $tags[] = $t;
            }
        }
    }
    return implode(' ', array_slice(array_unique($tags), 0, 5));
}

// シリーズリストからタグをまとめてベースタグに追加
function lottery_build_tags($base_tags, $series_list) {
    $extra = [];
    foreach ($series_list as $series) {
        $extracted = lottery_extract_hashtags($series);
        if ($extracted) {
            foreach (explode(' ', $extracted) as $t) {
                if ($t && !in_array($t, $extra)) $extra[] = $t;
            }
        }
    }
    $extra = array_slice(array_unique($extra), 0, 5);
    return $base_tags . ($extra ? ' ' . implode(' ', $extra) : '');
}

function tyusensearch_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'tyusensearch_setup');

// テーマ有効化時にcronを強制登録
function tyusensearch_activate_cron() {
    $schedules = wp_get_schedules();
    // every10minが使えるよう手動で追加
    if (!isset($schedules['every10min'])) {
        $schedules['every10min'] = ['interval'=>600,'display'=>'10分ごと'];
    }
    $hooks = [
        'lottery_morning_summary'  => ['hour'=>8,  'min'=>0,  'recur'=>'daily'],
        'lottery_evening_summary'  => ['hour'=>20, 'min'=>0,  'recur'=>'daily'],
        'lottery_night_summary'    => ['hour'=>23, 'min'=>0,  'recur'=>'daily'],
        'lottery_deadline_check'   => ['hour'=>-1, 'min'=>-1, 'recur'=>'every10min'],
        'lottery_loop_post'        => ['hour'=>-1, 'min'=>-1, 'recur'=>'every3hours'],
    ];
    foreach ($hooks as $hook => $conf) {
        if (!wp_next_scheduled($hook)) {
            if ($conf['hour'] >= 0) {
                $t = mktime($conf['hour'], $conf['min'], 0);
                if (time() >= $t) $t += 86400;
            } else {
                $t = time() + 60;
            }
            wp_schedule_event($t, $conf['recur'], $hook);
        }
    }
}
add_action('after_switch_theme', 'tyusensearch_activate_cron');
add_action('init', 'tyusensearch_activate_cron'); // 毎リクエストで未登録なら再登録

function tyusensearch_scripts() {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Noto+Sans+JP:wght@400;700;900&display=swap', [], null);
}
add_action('wp_enqueue_scripts', 'tyusensearch_scripts');

// ========================================
// 管理画面：抽選情報一覧ページ
// ========================================
add_action('admin_menu', function() {
    add_menu_page('抽選情報管理', '抽選情報管理', 'manage_options', 'lottery-manager', 'lottery_manager_page', 'dashicons-tickets-alt', 3);
});

function lottery_manager_page() {
    if (isset($_POST['delete_id']) && check_admin_referer('lottery_delete')) {
        wp_delete_post(intval($_POST['delete_id']), true);
        echo '<div class="notice notice-success"><p>削除しました。</p></div>';
    }
    if (isset($_POST['save_id']) && check_admin_referer('lottery_save')) {
        $id = intval($_POST['save_id']);
        $fields = array('series','store','category','start_date','end_date','lottery_url','note');
        foreach ($fields as $key) {
            $val = $key === 'lottery_url' ? esc_url_raw($_POST[$key]) : sanitize_text_field($_POST[$key]);
            update_field($key, $val, $id);
        }
        echo '<div class="notice notice-success"><p>保存しました。</p></div>';
    }

    $posts = get_posts(array('post_type' => 'lottery', 'numberposts' => -1, 'post_status' => 'publish'));
    ?>
    <div class="wrap">
    <h1>抽選情報管理</h1>
    <p style="color:#666;">各行を直接編集して「💾 保存」を押してください。締切から1週間後に自動削除されます。</p>
    <style>
    #lt { border-collapse:collapse; width:100%; font-size:13px; }
    #lt th { background:#1e2635; color:#eef2ff; padding:10px 8px; text-align:left; position:sticky; top:32px; z-index:1; white-space:nowrap; }
    #lt td { padding:6px 8px; border-bottom:1px solid #eee; vertical-align:middle; }
    #lt tr:hover td { background:#f5f9ff; }
    #lt input, #lt select, #lt textarea { width:100%; font-size:12px; padding:4px 6px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; }
    #lt textarea { min-height:32px; resize:vertical; }
    .btn-save { background:#0073aa; color:#fff; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px; white-space:nowrap; }
    .btn-save:hover { background:#005d8c; }
    .btn-del { background:#dc3232; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:12px; white-space:nowrap; }
    .btn-del:hover { background:#a00; }
    .st-active { color:#0073aa; font-weight:bold; }
    .st-urgent { color:#dc3232; font-weight:bold; }
    .st-expired { color:#999; }
    .st-upcoming { color:#f0883e; font-weight:bold; }
    </style>
    <table id="lt">
    <thead><tr>
        <th>カテゴリ</th><th>シリーズ</th><th>店舗</th>
        <th>開始日<br><small style="font-weight:normal;color:#aaa">YYYYMMDD</small></th>
        <th>締切日<br><small style="font-weight:normal;color:#aaa">YYYYMMDD</small></th>
        <th>URL</th><th>備考</th><th>状態</th><th>操作</th>
    </tr></thead>
    <tbody>
    <?php foreach ($posts as $post):
        $acf = get_fields($post->ID);
        $series   = $acf['series'] ?? '';
        $store    = $acf['store'] ?? '';
        $cat      = $acf['category'] ?? 'other';
        $start    = $acf['start_date'] ?? '';
        $end      = $acf['end_date'] ?? '';
        $url      = $acf['lottery_url'] ?? '';
        $note     = $acf['note'] ?? '';
        $now = time();
        $end_ts   = $end   ? jst_strtotime($end)   : 0;
        $start_ts = $start ? jst_strtotime($start) : 0;
        if ($end_ts && $now > $end_ts) { $sl='終了'; $sc='st-expired'; }
        elseif ($start_ts && $now < $start_ts) { $sl='近日開始'; $sc='st-upcoming'; }
        elseif ($end_ts && ($end_ts-$now)<172800) { $sl='⚡締切間近'; $sc='st-urgent'; }
        else { $sl='受付中'; $sc='st-active'; }
    ?>
    <tr>
        <form method="post"><?php wp_nonce_field('lottery_save'); ?>
        <input type="hidden" name="save_id" value="<?php echo $post->ID; ?>">
        <td><select name="category">
            <option value="pokeca" <?php selected($cat,'pokeca'); ?>>🃏 ポケカ</option>
            <option value="sneaker" <?php selected($cat,'sneaker'); ?>>👟 スニーカー</option>
            <option value="other" <?php selected($cat,'other'); ?>>🎮 その他</option>
        </select></td>
        <td><input type="text" name="series" value="<?php echo esc_attr($series); ?>"></td>
        <td><input type="text" name="store" value="<?php echo esc_attr($store); ?>"></td>
        <td><input type="text" name="start_date" value="<?php echo esc_attr($start); ?>" placeholder="20260301" style="width:100px"></td>
        <td><input type="text" name="end_date" value="<?php echo esc_attr($end); ?>" placeholder="20260315" style="width:100px"></td>
        <td><input type="url" name="lottery_url" value="<?php echo esc_attr($url); ?>"></td>
        <td><textarea name="note"><?php echo esc_textarea($note); ?></textarea></td>
        <td><span class="<?php echo $sc; ?>"><?php echo $sl; ?></span></td>
        <td style="white-space:nowrap"><button type="submit" class="btn-save">💾 保存</button></td>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
            <?php wp_nonce_field('lottery_delete'); ?>
            <input type="hidden" name="delete_id" value="<?php echo $post->ID; ?>">
            <button type="submit" class="btn-del">🗑</button>
        </form>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
    <?php
}

// ========================================
// 自動非公開：締切から1週間後に非公開化
// ========================================
if (!wp_next_scheduled('lottery_auto_delete_hook')) {
    wp_schedule_event(time(), 'daily', 'lottery_auto_delete_hook');
}
add_action('lottery_auto_delete_hook', function() {
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
    foreach ($posts as $post) {
        $end = get_post_meta($post->ID, 'end_date', true);
        if (!$end) continue;
        $end_ts = jst_strtotime($end);
        if ($end_ts && (time() - $end_ts) > 7*24*3600) {
            wp_update_post(['ID'=>$post->ID, 'post_status'=>'private']);
        }
    }
});

// ========================================
// X（Twitter）自動投稿
// wp-config.php に以下を追記してください:
//   define('X_API_KEY',            '...');
//   define('X_API_SECRET',         '...');
//   define('X_ACCESS_TOKEN',       '...');
//   define('X_ACCESS_TOKEN_SECRET','...');
// ========================================
if (!defined('X_API_KEY'))             define('X_API_KEY',            '');
if (!defined('X_API_SECRET'))          define('X_API_SECRET',         '');
if (!defined('X_ACCESS_TOKEN'))        define('X_ACCESS_TOKEN',       '');
if (!defined('X_ACCESS_TOKEN_SECRET')) define('X_ACCESS_TOKEN_SECRET','');

function tyusensearch_post_to_x($text, $reply_to_id = null) {
    $url    = 'https://api.twitter.com/2/tweets';
    $method = 'POST';
    $oauth  = [
        'oauth_consumer_key'     => X_API_KEY,
        'oauth_nonce'            => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => time(),
        'oauth_token'            => X_ACCESS_TOKEN,
        'oauth_version'          => '1.0',
    ];
    $base_params = $oauth;
    ksort($base_params);
    $param_str = implode('&', array_map(function($k,$v){ return rawurlencode($k).'='.rawurlencode($v); }, array_keys($base_params), $base_params));
    $base_str  = $method.'&'.rawurlencode($url).'&'.rawurlencode($param_str);
    $sign_key  = rawurlencode(X_API_SECRET).'&'.rawurlencode(X_ACCESS_TOKEN_SECRET);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_str, $sign_key, true));

    $auth_parts = array_map(function($k,$v){ return rawurlencode($k).'="'.rawurlencode($v).'"'; }, array_keys($oauth), $oauth);
    $auth_header = 'OAuth '.implode(', ', $auth_parts);

    $payload = ['text' => $text];
    if ($reply_to_id) {
        $payload['reply'] = ['in_reply_to_tweet_id' => (string)$reply_to_id];
    }
    $body = json_encode($payload);
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => $auth_header,
            'Content-Type'  => 'application/json',
        ],
        'body'    => $body,
        'timeout' => 15,
    ]);

    // エラーログ
    if (is_wp_error($response)) {
        error_log('[抽選サーチ X投稿] WP_Error: ' . $response->get_error_message());
    } else {
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 201) {
            error_log('[抽選サーチ X投稿] HTTPエラー ' . $code . ': ' . wp_remote_retrieve_body($response));
        }
    }

    return $response;
}

// 投稿後にtweetIDを取得するヘルパー
function tyusensearch_get_tweet_id($response) {
    if (is_wp_error($response)) return null;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['data']['id'] ?? null;
}

// カテゴリ別一覧リンクをリプライとして投稿
function tyusensearch_reply_list_link($tweet_id, $cat) {
    if (!$tweet_id) return;
    $cat_labels = ['pokeca'=>'🃏ポケカ', 'sneaker'=>'👟スニーカー', 'other'=>'🎮その他'];
    $cat_urls   = ['pokeca'=>home_url('/pokeca/'), 'sneaker'=>home_url('/sneaker/'), 'other'=>home_url('/other/')];
    $cat_tags   = [
        'pokeca'  => "#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報 #抽選 #当選",
        'sneaker' => "#スニーカー #スニーカー抽選 #Nike #Jordan #抽選情報 #抽選 #当選",
        'other'   => "#抽選情報 #抽選 #当選",
    ];
    $label = $cat_labels[$cat] ?? 'その他';
    $url   = $cat_urls[$cat]   ?? home_url('/');
    $tags  = $cat_tags[$cat]   ?? '#抽選情報 #抽選 #当選';
    $comment = "📋 {$label}の抽選情報一覧はこちら👇\n{$url}\n\n{$tags}";
    tyusensearch_post_to_x($comment, $tweet_id);
}

// ========================================
// 定時まとめX投稿（3種）
// 朝8時  → 今日締切の抽選まとめ
// 夜20時 → 明日締切の抽選まとめ
// 夜23時 → 今日追加した情報まとめ
// ========================================

// 280文字に収まる分だけ行を追加するヘルパー
function tyusensearch_fit_lines($header, $lines, $footer, $limit = 280) {
    $result = '';
    foreach ($lines as $line) {
        $candidate = $header . $result . $line . "\n" . $footer;
        if (mb_strlen($candidate) > $limit) break;
        $result .= $line . "\n";
    }
    return $header . $result . $footer;
}

// カスタムcron間隔（initより先に登録必須）
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['every10min'])) {
        $schedules['every10min'] = ['interval'=>600, 'display'=>'10分ごと'];
    }
    return $schedules;
});

// cronイベント登録（全て一括削除→再登録で確実にリフレッシュ）
add_action('init', function() {
    // 既存を削除してリセット（スケジュールがずれた場合の対策）
    $hooks = ['lottery_morning_summary','lottery_evening_summary','lottery_night_summary','lottery_deadline_check'];
    foreach ($hooks as $hook) {
        $ts = wp_next_scheduled($hook);
        // 登録がなければ削除スキップ
        if ($ts === false) {
            // 新規登録へ進む
        }
    }

    if (!wp_next_scheduled('lottery_morning_summary')) {
        $t = mktime(8, 0, 0);
        if (time() >= $t) $t += 86400;
        wp_schedule_event($t, 'daily', 'lottery_morning_summary');
    }
    if (!wp_next_scheduled('lottery_evening_summary')) {
        $t = mktime(20, 0, 0);
        if (time() >= $t) $t += 86400;
        wp_schedule_event($t, 'daily', 'lottery_evening_summary');
    }
    if (!wp_next_scheduled('lottery_night_summary')) {
        $t = mktime(23, 0, 0);
        if (time() >= $t) $t += 86400;
        wp_schedule_event($t, 'daily', 'lottery_night_summary');
    }
    if (!wp_next_scheduled('lottery_deadline_check')) {
        wp_schedule_event(time(), 'every10min', 'lottery_deadline_check');
    }
});

// 締切24時間前に自動投稿
add_action('lottery_deadline_check', function() {
    $now   = time();
    $in24  = $now + 86400; // 24時間後
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
    $cat_names = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
    $cat_tags  = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
    $cat_urls  = ['pokeca'=>home_url('/pokeca/'),'sneaker'=>home_url('/sneaker/'),'other'=>home_url('/other/')];
    foreach ($posts as $p) {
        $end = get_post_meta($p->ID,'end_date',true);
        if (!$end) continue;
        $end_ts = jst_strtotime($end);
        // 24時間以内に締切 かつ まだ締切前
        if ($end_ts <= $now || $end_ts > $in24) continue;
        // 既に投稿済みかチェック（postmetaに記録）
        $already = get_post_meta($p->ID,'_x_deadline_notified',true);
        if ($already) continue;
        $series   = get_post_meta($p->ID,'series',true) ?: $p->post_title;
        $store    = get_post_meta($p->ID,'store',true)  ?: '';
        $cat      = get_post_meta($p->ID,'category',true) ?: 'other';
        $app_url  = get_permalink($p->ID); // 個別ページURL（引用リポスト回避）
        $list_url = $cat_urls[$cat];
        $end_str  = jst_date('n/j H:i', $end_ts);
        $extra    = lottery_build_tags($cat_tags[$cat], [$series]);
        $text     = "⚡ 締切まで24時間を切りました！

{$cat_names[$cat]} {$series}
🏪 {$store}
⏰ 締切：{$end_str}

👉 {$app_url}

一覧はこちら👇
{$list_url}

{$extra}";
        $result = tyusensearch_post_to_x($text);
        if ($result) {
            update_post_meta($p->ID,'_x_deadline_notified', date('Y-m-d H:i'));
        }
        sleep(2);
    }
});

// 共通：投稿文を組み立ててXに投稿するヘルパー
function lottery_build_and_post($cats, $cat_names, $cat_tags, $cat_urls, $header_tpl, $footer_tpl = null) {
    foreach ($cats as $cat => $lines) {
        if (empty($lines)) continue;
        $header = str_replace('{name}', $cat_names[$cat], $header_tpl);
        $footer = "\n👉 {$cat_urls[$cat]}\n{$cat_tags[$cat]}";
        $text   = tyusensearch_fit_lines($header, $lines, $footer);
        tyusensearch_post_to_x($text);
        sleep(3);
    }
}

// X投稿はpage-admin-lottery.phpの新規作成処理内で一元管理

// ========================================
// Lottery個別ページのOGP画像をYoastに渡す
// ========================================
add_filter('wpseo_opengraph_image', function($image) {
    if (is_singular('lottery')) {
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'large');
        if ($thumb) return $thumb;
    }
    return $image;
});

add_filter('wpseo_twitter_image', function($image) {
    if (is_singular('lottery')) {
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'large');
        if ($thumb) return $thumb;
    }
    return $image;
});

// OGタイトル・説明もlotteryページ用に最適化
add_filter('wpseo_title', function($title) {
    if (is_singular('lottery')) {
        $id     = get_the_ID();
        $series = get_post_meta($id, 'series', true) ?: get_the_title();
        $store  = get_post_meta($id, 'store', true)  ?: '';
        return $series . ($store ? '｜' . $store : '') . '｜抽選サーチ';
    }
    return $title;
});

add_filter('wpseo_metadesc', function($desc) {
    if (is_singular('lottery')) {
        $id     = get_the_ID();
        $series = get_post_meta($id, 'series', true)     ?: '';
        $store  = get_post_meta($id, 'store', true)      ?: '';
        $end    = get_post_meta($id, 'end_date', true)   ?: '';
        $end_str = $end ? jst_date('n月j日 H:i', jst_strtotime($end)) : '未定';
        return "{$series}の抽選情報。{$store}での応募締切は{$end_str}。詳細・応募はこちら。";
    }
    return $desc;
});


// ========================================
// 定時まとめX投稿（3本）
// ========================================

// カスタムスケジュール登録
add_filter('cron_schedules', function($s) {
    if (!isset($s['every3hours'])) {
        $s['every3hours'] = ['interval'=>10800, 'display'=>'3時間ごと'];
    }
    return $s;
});

// 3時間ごとのカテゴリ別ローテーション投稿
add_action('lottery_loop_post', function() {
    $cats = ['pokeca', 'sneaker', 'other'];
    $cat_names = ['pokeca'=>'🃏ポケカ', 'sneaker'=>'👟スニーカー', 'other'=>'🎮その他'];
    $cat_tags  = [
        'pokeca'  => '#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報',
        'sneaker' => '#スニーカー #スニーカー抽選 #抽選情報',
        'other'   => '#抽選情報 #限定グッズ',
    ];
    $cat_urls = [
        'pokeca'  => home_url('/pokeca/'),
        'sneaker' => home_url('/sneaker/'),
        'other'   => home_url('/other/'),
    ];

    // 前回投稿したカテゴリの次を選ぶ
    $last = get_option('lottery_loop_last_cat', 'other');
    $idx  = (array_search($last, $cats) + 1) % 3;
    $cat  = $cats[$idx];
    update_option('lottery_loop_last_cat', $cat);

    $now   = time();
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish',
        'meta_query'=>[['key'=>'category','value'=>$cat,'compare'=>'=']]]);

    $lines = [];
    foreach ($posts as $p) {
        $end_ts = jst_strtotime(get_post_meta($p->ID,'end_date',true));
        if ($end_ts && $end_ts < $now) continue; // 締切済みはスキップ
        $series  = get_post_meta($p->ID,'series',true)  ?: $p->post_title;
        $store   = get_post_meta($p->ID,'store',true)   ?: '';
        $end_str = $end_ts ? jst_date('n/j H:i', $end_ts) : '未定';
        $page_url = get_permalink($p->ID);
        $lines[] = "・{$series}｜{$store} 締切:{$end_str}" . "\n👉 {$page_url}";
    }

    if (empty($lines)) return;

    $name = $cat_names[$cat];
    $tags = $cat_tags[$cat];
    $url  = $cat_urls[$cat];
    $text = "【{$name} 現在の抽選情報】\n\n" . implode("\n", $lines) . "\n\n一覧はこちら👇\n{$url}\n\n{$tags}";
    tyusensearch_post_to_x($text);
});

// lottery_loop_postのCron登録をactivate_cronに追加




// 共通：280文字以内に収まるまでリスト行を削る
function lottery_trim_to_280($header, $lines, $footer) {
    // X Premium対応：全件1投稿にまとめる
    return [$header . implode("\n", $lines) . $footer];
}

// 朝8時：今日締切の抽選まとめ
add_action('lottery_morning_summary', function() {
    $lock_key = 'lottery_morning_lock_' . date('Y-m-d');
    if (get_option($lock_key)) return;
    update_option($lock_key, time(), false);
    $now     = time();
    $jst_off = 9*3600;
    $today_y = date('Y', $now+$jst_off);
    $today_m = date('n', $now+$jst_off);
    $today_d = date('j', $now+$jst_off);
    $day_start = mktime(0,0,0,$today_m,$today_d,$today_y) - $jst_off;
    $day_end   = mktime(23,59,59,$today_m,$today_d,$today_y) - $jst_off;
    $cat_names = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
    $lines = [];
    foreach ($posts as $p) {
        $end_ts = jst_strtotime(get_post_meta($p->ID,'end_date',true));
        if (!$end_ts || $end_ts < $day_start || $end_ts > $day_end) continue;
        $cat    = get_post_meta($p->ID,'category',true) ?: 'other';
        $series = get_post_meta($p->ID,'series',true)   ?: $p->post_title;
        $store  = get_post_meta($p->ID,'store',true)    ?: '';
        $emoji  = $cat_names[$cat] ?? '🎮その他';
        $page_url = get_permalink($p->ID);
        $lines[] = "{$emoji} ⏰".jst_date('H:i',$end_ts)." {$series}｜{$store}" . "\n👉 {$page_url}";
    }
    if (empty($lines)) return;
    $tags = '#ポケカ抽選 #スニーカー抽選 #抽選情報 #抽選サーチ';
    $text = "【本日締切の抽選まとめ⏰】\n\n" . implode("\n", $lines) . "\n\n一覧はこちら👇\n" . home_url('/') . "\n\n{$tags}";
    tyusensearch_post_to_x($text);
});

// 夜20時：明日締切の抽選まとめ
add_action('lottery_evening_summary', function() {
    $lock_key = 'lottery_evening_lock_' . date('Y-m-d');
    if (get_option($lock_key)) return;
    update_option($lock_key, time(), false);
    $now     = time();
    $jst_off = 9*3600;
    $tmrw_y  = date('Y', $now+$jst_off+86400);
    $tmrw_m  = date('n', $now+$jst_off+86400);
    $tmrw_d  = date('j', $now+$jst_off+86400);
    $day_start = mktime(0,0,0,$tmrw_m,$tmrw_d,$tmrw_y) - $jst_off;
    $day_end   = mktime(23,59,59,$tmrw_m,$tmrw_d,$tmrw_y) - $jst_off;
    $cat_names = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
    $lines = [];
    foreach ($posts as $p) {
        $end_ts = jst_strtotime(get_post_meta($p->ID,'end_date',true));
        if (!$end_ts || $end_ts < $day_start || $end_ts > $day_end) continue;
        $cat    = get_post_meta($p->ID,'category',true) ?: 'other';
        $series = get_post_meta($p->ID,'series',true)   ?: $p->post_title;
        $store  = get_post_meta($p->ID,'store',true)    ?: '';
        $emoji  = $cat_names[$cat] ?? '🎮その他';
        $page_url = get_permalink($p->ID);
        $lines[] = "{$emoji} ⏰".jst_date('H:i',$end_ts)." {$series}｜{$store}" . "\n👉 {$page_url}";
    }
    if (empty($lines)) return;
    $tags = '#ポケカ抽選 #スニーカー抽選 #抽選情報 #抽選サーチ';
    $text = "【明日締切の抽選まとめ⚡】

" . implode("
", $lines) . "

一覧はこちら👇
" . home_url('/') . "

{$tags}";
    tyusensearch_post_to_x($text);
});

// 夜23時：今日追加した抽選まとめ
add_action('lottery_night_summary', function() {
    $lock_key = 'lottery_night_lock_' . date('Y-m-d');
    if (get_option($lock_key)) return;
    update_option($lock_key, time(), false);
    $jst_off   = 9*3600;
    $now       = time();
    $today_y   = date('Y', $now+$jst_off);
    $today_m   = date('n', $now+$jst_off);
    $today_d   = date('j', $now+$jst_off);
    $day_start_utc = date('Y-m-d 00:00:00', mktime(0,0,0,$today_m,$today_d,$today_y) - $jst_off);
    $day_end_utc   = date('Y-m-d 23:59:59', mktime(23,59,59,$today_m,$today_d,$today_y) - $jst_off);
    $cat_names = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
    $posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish',
        'date_query'=>[['after'=>$day_start_utc,'before'=>$day_end_utc,'inclusive'=>true]]]);
    $lines = [];
    $now_t = time();
    foreach ($posts as $p) {
        $end_ts = jst_strtotime(get_post_meta($p->ID,'end_date',true));
        if ($end_ts && $now_t > $end_ts) continue;
        $cat    = get_post_meta($p->ID,'category',true) ?: 'other';
        $series = get_post_meta($p->ID,'series',true)   ?: $p->post_title;
        $store  = get_post_meta($p->ID,'store',true)    ?: '';
        $end_str = $end_ts ? jst_date('n/j H:i', $end_ts) : '未定';
        $emoji  = $cat_names[$cat] ?? '🎮その他';
        $page_url = get_permalink($p->ID);
        $lines[] = "{$emoji} {$series}｜{$store} 締切:{$end_str}" . "\n👉 {$page_url}";
    }
    if (empty($lines)) return;
    $tags = '#ポケカ抽選 #スニーカー抽選 #抽選情報 #抽選サーチ';
    $text = "【本日追加した新着抽選🆕】

" . implode("
", $lines) . "

一覧はこちら👇
" . home_url('/') . "

{$tags}";
    tyusensearch_post_to_x($text);
});


// ロックキーの定期クリーンアップ
add_action('lottery_delete_lock', function($key) {
    delete_option($key);
});

// =============================================
// iCal フィード（Googleカレンダー連携）
// URL: https://tyusensearch.com/?ical=lottery
// カテゴリ別: ?ical=lottery&cat=pokeca
// =============================================
add_action('init', function() {
    if (!isset($_GET['ical']) || $_GET['ical'] !== 'lottery') return;

    $cat_filter = isset($_GET['cat']) ? sanitize_text_field($_GET['cat']) : '';
    $valid_cats = ['pokeca', 'sneaker', 'other'];

    // 全抽選を取得
    $args = [
        'post_type'   => 'lottery',
        'numberposts' => -1,
        'post_status' => 'publish',
    ];
    if ($cat_filter && in_array($cat_filter, $valid_cats)) {
        $args['meta_query'] = [['key' => 'category', 'value' => $cat_filter]];
    }
    $posts = get_posts($args);

    // カレンダー名
    $cal_names = [
        ''        => '抽選サーチ｜全抽選カレンダー',
        'pokeca'  => '抽選サーチ｜ポケカ抽選カレンダー',
        'sneaker' => '抽選サーチ｜スニーカー抽選カレンダー',
        'other'   => '抽選サーチ｜その他抽選カレンダー',
    ];
    $cal_name = $cal_names[$cat_filter] ?? $cal_names[''];

    // iCal出力
    header('Content-Type: text/calendar; charset=UTF-8');
    header('Content-Disposition: inline; filename="tyusensearch.ics"');
    header('Cache-Control: no-cache, must-revalidate');

    $now_utc = gmdate('Ymd\THis\Z');
    $output  = "BEGIN:VCALENDAR\r\n";
    $output .= "VERSION:2.0\r\n";
    $output .= "PRODID:-//抽選サーチ//tyusensearch.com//JA\r\n";
    $output .= "CALSCALE:GREGORIAN\r\n";
    $output .= "METHOD:PUBLISH\r\n";
    $output .= "X-WR-CALNAME:{$cal_name}\r\n";
    $output .= "X-WR-TIMEZONE:Asia/Tokyo\r\n";
    $output .= "X-WR-CALDESC:抽選サーチ(tyusensearch.com)の抽選締切カレンダーです。自動更新されます。\r\n";
    $output .= "BEGIN:VTIMEZONE\r\n";
    $output .= "TZID:Asia/Tokyo\r\n";
    $output .= "BEGIN:STANDARD\r\n";
    $output .= "TZOFFSETFROM:+0900\r\n";
    $output .= "TZOFFSETTO:+0900\r\n";
    $output .= "TZNAME:JST\r\n";
    $output .= "DTSTART:19700101T000000\r\n";
    $output .= "END:STANDARD\r\n";
    $output .= "END:VTIMEZONE\r\n";

    $cat_emojis = ['pokeca' => '🃏', 'sneaker' => '👟', 'other' => '🎮'];

    foreach ($posts as $p) {
        $series = get_post_meta($p->ID, 'series', true) ?: $p->post_title;
        $store  = get_post_meta($p->ID, 'store', true)  ?: '';
        $cat    = get_post_meta($p->ID, 'category', true) ?: 'other';
        $start  = get_post_meta($p->ID, 'start_date', true) ?: '';
        $end    = get_post_meta($p->ID, 'end_date', true)   ?: '';
        $url    = get_permalink($p->ID);
        $note   = get_post_meta($p->ID, 'note', true) ?: '';
        $emoji  = $cat_emojis[$cat] ?? '🎮';

        // 締切日がないものはスキップ
        if (!$end) continue;

        $end_ts   = jst_strtotime($end);
        $start_ts = $start ? jst_strtotime($start) : $end_ts;

        // DBの値は日本時間(JST)なのでUTCに変換（-9時間）して出力
        $jst_offset = 9 * 3600;
        $dtstart = gmdate('Ymd\THis\Z', $end_ts - 3600 - $jst_offset); // 締切1時間前
        $dtend   = gmdate('Ymd\THis\Z', $end_ts - $jst_offset);         // 締切時刻
        $dtstamp  = $now_utc;

        // UID（重複しないよう投稿IDベース）
        $uid = 'lottery-' . $p->ID . '@tyusensearch.com';

        // 概要・説明
        $summary = $emoji . ' 【抽選締切】' . $series . ($store ? '｜' . $store : '');
        $desc    = '締切：' . date('n月j日 H:i', $end_ts) . '\n';
        $desc   .= '応募開始：' . ($start ? date('n月j日 H:i', $start_ts) : '未定') . '\n';
        if ($store) $desc .= '販売店：' . $store . '\n';
        if ($note)  $desc .= '備考：' . $note . '\n';
        $desc   .= '詳細：' . get_permalink($p->ID);

        // 特殊文字エスケープ
        $summary = str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $summary);
        $desc    = str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $desc);

        $output .= "BEGIN:VEVENT\r\n";
        $output .= "UID:{$uid}\r\n";
        $output .= "DTSTAMP:{$dtstamp}\r\n";
        $output .= "DTSTART:{$dtstart}\r\n";
        $output .= "DTEND:{$dtend}\r\n";
        $output .= "SUMMARY:{$summary}\r\n";
        $output .= "DESCRIPTION:{$desc}\r\n";
        $output .= "URL:" . get_permalink($p->ID) . "\r\n";
        // 1日前通知
        $output .= "BEGIN:VALARM\r\n";
        $output .= "TRIGGER:-P1D\r\n";
        $output .= "ACTION:DISPLAY\r\n";
        $output .= "DESCRIPTION:⏰ 明日締切！{$summary}\r\n";
        $output .= "END:VALARM\r\n";
        // 6時間前通知
        $output .= "BEGIN:VALARM\r\n";
        $output .= "TRIGGER:-PT6H\r\n";
        $output .= "ACTION:DISPLAY\r\n";
        $output .= "DESCRIPTION:⚡ 締切まで6時間！{$summary}\r\n";
        $output .= "END:VALARM\r\n";
        $output .= "END:VEVENT\r\n";
    }

    $output .= "END:VCALENDAR\r\n";
    echo $output;
    exit;
});

// ========================================
// WPForms ダークテーマ対応CSS
// ========================================
add_action('wp_head', function() {
    if (!is_page('contact')) return;
    echo '<style>
.wpforms-container input,
.wpforms-container textarea,
.wpforms-container select {
    background: #1a1f2e !important;
    color: #e6edf3 !important;
    border: 1px solid #30363d !important;
    border-radius: 8px !important;
    padding: 10px 14px !important;
}
.wpforms-container input:focus,
.wpforms-container textarea:focus {
    border-color: #00f0c0 !important;
    outline: none !important;
}
.wpforms-container label {
    color: #e6edf3 !important;
    font-weight: 700 !important;
    font-size: 13px !important;
}
.wpforms-container .wpforms-field {
    margin-bottom: 16px !important;
}
.wpforms-container button[type=submit],
.wpforms-container input[type=submit] {
    background: #00f0c0 !important;
    color: #000 !important;
    font-weight: 900 !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    font-size: 14px !important;
}
.wpforms-container .wpforms-confirmation-container-full {
    background: #1a2e1a !important;
    color: #4ade80 !important;
    border: 1px solid #4ade80 !important;
    border-radius: 8px !important;
    padding: 16px !important;
}
</style>';
});

// prefecture を REST API で返す
add_action('rest_api_init', function() {
    register_rest_field('lottery', 'prefecture', [
        'get_callback' => function($post) {
            return get_post_meta($post['id'], 'prefecture', true) ?: '';
        },
        'schema' => ['type' => 'string'],
    ]);
});

// ==========================================
// SEO: 個別ページのtitleタグ最適化
// ==========================================
add_filter('pre_get_document_title', function($title) {
    if (!is_singular('lottery')) return $title;
    $pid    = get_the_ID();
    $series = get_post_meta($pid, 'series', true) ?: get_the_title();
    $store  = get_post_meta($pid, 'store', true)  ?: '';
    $pref   = get_post_meta($pid, 'prefecture', true) ?: '';
    $cat    = get_post_meta($pid, 'category', true) ?: 'other';
    $cat_labels = ['pokeca'=>'ポケカ','sneaker'=>'スニーカー','other'=>'その他'];
    $cat_label  = $cat_labels[$cat] ?? 'その他';
    $parts = array_filter([$series, $store, $pref ? $pref.'の店舗' : '']);
    return implode('｜', $parts) . '｜' . $cat_label . '抽選｜抽選サーチ';
}, 10);

// ==========================================
// SEO: descriptionメタタグ・canonical・JSON-LD
// ==========================================
add_action('wp_head', function() {
    if (!is_singular('lottery')) return;
    $pid    = get_the_ID();
    $series = get_post_meta($pid, 'series', true) ?: get_the_title();
    $store  = get_post_meta($pid, 'store', true)  ?: '';
    $pref   = get_post_meta($pid, 'prefecture', true) ?: '';
    $cat    = get_post_meta($pid, 'category', true) ?: 'other';
    $start  = get_post_meta($pid, 'start_date', true) ?: '';
    $end    = get_post_meta($pid, 'end_date', true)   ?: '';
    $url    = get_post_meta($pid, 'lottery_url', true) ?: '';
    $thumb  = get_the_post_thumbnail_url($pid, 'large') ?: '';
    $permalink = get_permalink($pid);

    $cat_labels = ['pokeca'=>'ポケカ','sneaker'=>'スニーカー','other'=>'その他'];
    $cat_label  = $cat_labels[$cat] ?? 'その他';

    $end_str   = $end   ? date('n月j日 H:i', jst_strtotime($end))   : '未定';
    $start_str = $start ? date('n月j日 H:i', jst_strtotime($start)) : '未定';
    $pref_str  = $pref  ? $pref . 'の' : '';

    // description
    $desc = "{$series}（{$store}）の{$cat_label}抽選情報。{$pref_str}応募期間は{$start_str}〜{$end_str}。抽選サーチで最新の抽選情報をチェック。";
    echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";

    // canonical
    echo '<link rel="canonical" href="' . esc_url($permalink) . '" />' . "\n";

    // JSON-LD 構造化データ
    $ld = [
        '@context' => 'https://schema.org',
        '@type'    => 'Event',
        'name'     => $series . ($store ? '｜' . $store : ''),
        'description' => $desc,
        'url'      => $permalink,
        'organizer' => [
            '@type' => 'Organization',
            'name'  => $store ?: '抽選サーチ',
        ],
        'location' => [
            '@type' => 'Place',
            'name'  => $store ?: '抽選サーチ',
            'address' => [
                '@type'           => 'PostalAddress',
                'addressRegion'   => $pref ?: '日本',
                'addressCountry'  => 'JP',
            ],
        ],
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    ];
    if ($start) $ld['startDate'] = date('c', jst_strtotime($start));
    if ($end)   $ld['endDate']   = date('c', jst_strtotime($end));
    if ($thumb) $ld['image']     = $thumb;
    if ($url)   $ld['offers']    = [
        '@type'       => 'Offer',
        'url'         => $url,
        'price'       => '0',
        'priceCurrency' => 'JPY',
        'availability'  => 'https://schema.org/InStock',
        'validThrough'  => $end ? date('c', jst_strtotime($end)) : '',
    ];

    echo '<script type="application/ld+json">' . "\n";
    echo json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n" . '</script>' . "\n";
}, 5);

// ==========================================
// AI自動入力：Gemini API Ajaxエンドポイント
// ==========================================
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE'); // ←ここにAPIキーを設定
