<?php
session_start();
/*
Template Name: 抽選管理画面
*/

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// 画像・URL・テキスト合算解析
if (($_POST['action_type'] ?? '') === 'ai_combined_parse') {
    header('Content-Type: application/json; charset=utf-8');
    $text       = sanitize_textarea_field($_POST['text']       ?? '');
    $fetch_url  = esc_url_raw($_POST['fetch_url']  ?? '');
    $image_data = $_POST['image_data'] ?? '';
    $image_mime = sanitize_text_field($_POST['image_mime'] ?? 'image/jpeg');

    if (!$text && !$fetch_url && !$image_data) {
        echo json_encode(['success'=>false,'error'=>'画像・URL・テキストのいずれかを入力してください']); exit;
    }

    // URL取得
    $url_content = '';
    if ($fetch_url) {
        $page_res = wp_remote_get($fetch_url, ['timeout'=>10,'user-agent'=>'Mozilla/5.0']);
        if (!is_wp_error($page_res)) {
            $html = wp_remote_retrieve_body($page_res);
            if ($html) {
                $og_title = $og_desc = '';
                if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_title = html_entity_decode($m[1]);
                if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_desc = html_entity_decode($m[1]);
                $main_html = $html;
                foreach (['/<article[^>]*>(.*?)<\/article>/is','/<main[^>]*>(.*?)<\/main>/is',
                          '/<div[^>]+class="[^"]*entry-content[^"]*"[^>]*>(.*?)<\/div>/is'] as $pat) {
                    if (preg_match($pat, $html, $m)) { $main_html = $m[1]; break; }
                }
                $body = strip_tags(preg_replace('/<(script|style|nav|header|footer)[^>]*>.*?<\/\1>/is', '', $main_html));
                $body = mb_substr(trim(preg_replace('/\s+/', ' ', $body)), 0, 3000);
                $url_content = "\n\n【URL情報：{$fetch_url}】\n";
                if ($og_title) $url_content .= "タイトル：{$og_title}\n";
                if ($og_desc)  $url_content .= "説明：{$og_desc}\n";
                $url_content .= "本文：{$body}";
            }
        }
    }

    // 画像圧縮
    $image_b64 = '';
    if ($image_data) {
        if (strpos($image_data, ',') !== false) $image_data = explode(',', $image_data)[1];
        $img_binary = base64_decode($image_data);
        if ($img_binary && function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($img_binary);
            if ($img) {
                $ow = imagesx($img); $oh = imagesy($img); $max = 1024;
                if ($ow > $max || $oh > $max) {
                    $r = min($max/$ow, $max/$oh);
                    $nw = intval($ow*$r); $nh = intval($oh*$r);
                    $res = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($res, $img, 0,0,0,0, $nw,$nh,$ow,$oh);
                    imagedestroy($img); $img = $res;
                }
                ob_start(); imagejpeg($img, null, 75); $c = ob_get_clean();
                imagedestroy($img);
                $image_b64 = base64_encode($c);
                $image_mime = 'image/jpeg';
            }
        }
        if (!$image_b64) $image_b64 = $image_data;
    }

    $today      = jst_date('Y年m月d日', time());
    $prompt_txt = "以下の情報から抽選情報を抽出してJSONで返してください。\n今日の日付：{$today}\n\n";
    if ($text)        $prompt_txt .= "【テキスト】\n{$text}\n";
    if ($url_content) $prompt_txt .= $url_content;
    $prompt_txt .= "\n\n【重要】\n- 1件の場合はJSON1つ、複数件の場合はJSON配列\n- 前後に余計な文字・コードブロックは不要\n- categoryはポケモンカード関連→pokeca、スニーカー→sneaker、それ以外→other\n- 日時はYYYY-MM-DDTHH:MM形式。時間不明な場合start=00:00,end=23:59。result_dateは日付のみ(YYYY-MM-DD)でもOK\n- prefectureは「東京都」「全国」「オンライン」などの形式\n- noteは30文字以内で簡潔に\n- 情報がない場合は[]\n\n1件：{\"series\":\"\",\"store\":\"\",\"prefecture\":\"\",\"category\":\"other\",\"start_date\":\"\",\"end_date\":\"\",\"result_date\":\"\",\"lottery_url\":\"\",\"note\":\"\"}\n複数：[{...},{...}]";

    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

    // 画像がある場合はmultimodalで送信
    if ($image_b64) {
        $parts = [
            ['inline_data' => ['mime_type' => $image_mime, 'data' => $image_b64]],
            ['text' => $prompt_txt],
        ];
    } else {
        $parts = [['text' => $prompt_txt]];
    }

    $res = wp_remote_post($gemini_url, [
        'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'contents'         => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 4096],
        ]),
    ]);

    if (is_wp_error($res)) { echo json_encode(['success'=>false,'error'=>$res->get_error_message()]); exit; }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$raw) { echo json_encode(['success'=>false,'error'=>'AIレスポンス空','debug'=>$body]); exit; }
    $raw  = trim(preg_replace('/```json|```/', '', $raw));
    $data = json_decode($raw, true);
    if (!$data && substr($raw,0,1)==='[') {
        $last = strrpos($raw,'}');
        if ($last!==false) $data = json_decode(substr($raw,0,$last+1).']', true);
    }
    if (!$data || $data === []) { echo json_encode(['success'=>false,'error'=>'情報が見つかりませんでした','raw'=>substr($raw,0,200)]); exit; }
    echo json_encode(['success'=>true, isset($data[0]) ? 'type' : 'type' => isset($data[0]) ? 'multi' : 'single', isset($data[0]) ? 'results' : 'data' => $data]);
    exit;
}

// 画像から解析
if (($_POST['action_type'] ?? '') === 'ai_image_parse') {
    header('Content-Type: application/json; charset=utf-8');
    $image_data = $_POST['image_data'] ?? '';
    $image_mime = sanitize_text_field($_POST['image_mime'] ?? 'image/jpeg');
    if (!$image_data) { echo json_encode(['success'=>false,'error'=>'画像データなし']); exit; }

    // base64のヘッダー部分を除去
    if (strpos($image_data, ',') !== false) {
        $image_data = explode(',', $image_data)[1];
    }

    // 画像を圧縮してサイズを削減（GD使用）
    $img_binary = base64_decode($image_data);
    if ($img_binary && function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($img_binary);
        if ($img) {
            $orig_w = imagesx($img);
            $orig_h = imagesy($img);
            $max_size = 1024;
            if ($orig_w > $max_size || $orig_h > $max_size) {
                $ratio  = min($max_size / $orig_w, $max_size / $orig_h);
                $new_w  = intval($orig_w * $ratio);
                $new_h  = intval($orig_h * $ratio);
                $resized = imagecreatetruecolor($new_w, $new_h);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
                imagedestroy($img);
                $img = $resized;
            }
            ob_start();
            imagejpeg($img, null, 75);
            $compressed = ob_get_clean();
            imagedestroy($img);
            $image_data = base64_encode($compressed);
            $image_mime = 'image/jpeg';
        }
    }

    $today      = jst_date('Y年m月d日', time());
    $prompt     = "この画像から抽選情報を抽出してJSONで返してください。\n今日の日付：{$today}\n\n【重要】\n- 1件だけの場合はJSON1つ、複数件含まれる場合はJSON配列で返してください\n- 前後に余計な文字・説明・コードブロックは一切不要です\n- categoryはポケモンカード関連→pokeca、スニーカー・靴関連→sneaker、それ以外→other\n- 日時はYYYY-MM-DDTHH:MM形式。日付はわかるが時間が書いていない場合、start_dateは00:00、end_dateは23:59を使うこと。result_dateは日付のみ（YYYY-MM-DD形式）でもOK。完全に不明なら空文字\n- prefectureは「東京都」「全国」「オンライン」などの形式
- noteは応募条件・注意事項のみ30文字以内で簡潔に（長い説明文は不要）\n- 情報が全くない場合のみ[]を返す\n\n1件の場合：\n{\"series\":\"\",\"store\":\"\",\"prefecture\":\"\",\"category\":\"pokeca\",\"start_date\":\"\",\"end_date\":\"\",\"result_date\":\"\",\"lottery_url\":\"\",\"note\":\"\"}\n\n複数件の場合：\n[{\"series\":\"\",\"store\":\"\",\"prefecture\":\"\",\"category\":\"pokeca\",\"start_date\":\"\",\"end_date\":\"\",\"result_date\":\"\",\"lottery_url\":\"\",\"note\":\"\"},{\"series\":\"...\"}]";

    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    $res = wp_remote_post($gemini_url, [
        'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => $image_mime, 'data' => $image_data]],
                    ['text' => $prompt],
                ]
            ]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8192],
        ]),
    ]);

    if (is_wp_error($res)) { echo json_encode(['success'=>false,'error'=>$res->get_error_message()]); exit; }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$raw) { echo json_encode(['success'=>false,'error'=>'AIレスポンス空','debug'=>$body]); exit; }
    $raw  = preg_replace('/```json|```/', '', $raw);
    $raw  = trim($raw);
    $data = json_decode($raw, true);
    if (!$data && substr($raw,0,1)==='[') {
        $last = strrpos($raw,'}');
        if ($last!==false) $data = json_decode(substr($raw,0,$last+1).']', true);
    }
    if (!$data && substr($raw,0,1)==='{') { $data = json_decode($raw.'}', true); }
    if (!$data || $data === []) { echo json_encode(['success'=>false,'error'=>'画像から抽選情報が見つかりませんでした','raw'=>substr($raw,0,200)]); exit; }
    if (isset($data[0])) {
        echo json_encode(['success'=>true,'type'=>'multi','results'=>$data]);
    } else {
        echo json_encode(['success'=>true,'type'=>'single','data'=>$data]);
    }
    exit;
}

// URL取得→AI解析
if (($_POST['action_type'] ?? '') === 'ai_fetch_parse') {
    header('Content-Type: application/json; charset=utf-8');
    $fetch_url = esc_url_raw($_POST['fetch_url'] ?? '');
    if (!$fetch_url) { echo json_encode(['success'=>false,'error'=>'URLなし']); exit; }

    // ページ取得
    $page_res = wp_remote_get($fetch_url, ['timeout'=>10,'user-agent'=>'Mozilla/5.0']);
    if (is_wp_error($page_res)) {
        echo json_encode(['success'=>false,'error'=>'ページ取得失敗：'.$page_res->get_error_message()]); exit;
    }
    $html = wp_remote_retrieve_body($page_res);
    if (!$html) { echo json_encode(['success'=>false,'error'=>'ページ内容が空です']); exit; }

    // OGP・meta・本文を抽出
    $og_title = $og_desc = $meta_desc = '';
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_title = html_entity_decode($m[1]);
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_desc = html_entity_decode($m[1]);
    if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $meta_desc = html_entity_decode($m[1]);

    // メインコンテンツ部分を優先して抽出
    $main_html = $html;
    // article, main, .entry-content, .post-content などを優先
    foreach (['/<article[^>]*>(.*?)<\/article>/is', '/<main[^>]*>(.*?)<\/main>/is',
              '/<div[^>]+class="[^"]*entry-content[^"]*"[^>]*>(.*?)<\/div>/is',
              '/<div[^>]+class="[^"]*post-content[^"]*"[^>]*>(.*?)<\/div>/is'] as $pat) {
        if (preg_match($pat, $html, $m)) { $main_html = $m[1]; break; }
    }
    $body_text = strip_tags(preg_replace('/<(script|style|nav|header|footer)[^>]*>.*?<\/\1>/is', '', $main_html));
    $body_text = preg_replace('/\s+/', ' ', $body_text);
    $body_text = mb_substr(trim($body_text), 0, 15000);

    $page_info = "URL：{$fetch_url}\n";
    if ($og_title)  $page_info .= "タイトル：{$og_title}\n";
    if ($og_desc)   $page_info .= "説明：{$og_desc}\n";
    elseif ($meta_desc) $page_info .= "説明：{$meta_desc}\n";
    $page_info .= "本文：{$body_text}";

    $today      = jst_date('Y年m月d日', time());
    $prompt_tpl = '以下のWebページから抽選・予約受付中・受付予定の情報を【全て漏れなく】抽出してJSON配列で返してください。\n今日の日付：{today}\n\n{text}\n\n【重要】\n- ページ内の全ての抽選・予約情報を抽出すること（1件だけでなく全件）\n- 必ずJSON配列で返す（1件でも配列）\n- 先着販売・在庫あり・再販は除く\n- 前後に余計な文字・説明・コードブロックは一切不要\n- categoryはポケモンカード関連→pokeca、スニーカー・靴関連→sneaker、それ以外→other\n- 日時はYYYY-MM-DDTHH:MM形式。日付はわかるが時間が書いていない場合、start_dateは00:00、end_dateは23:59を使うこと。result_dateは日付のみ（YYYY-MM-DD形式）でもOK。完全に不明なら空文字\n- storeはショップ・サイト名\n- lottery_urlは応募ページのURL（不明なら空文字）\n- prefectureはオンラインなら「オンライン」、実店舗なら都道府県名、不明なら「オンライン」\n- 情報が全くない場合のみ[]を返す\n\n[{"series":"商品名","store":"店舗名","prefecture":"オンライン","category":"other","start_date":"","end_date":"","result_date":"","lottery_url":"","note":""},{"series":"次の商品名","store":"..."}]';
    $prompt = str_replace(['{today}','{text}'], [$today, $page_info], $prompt_tpl);

    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    $res = wp_remote_post($gemini_url, [
        'timeout' => 60,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8192],
        ]),
    ]);
    if (is_wp_error($res)) { echo json_encode(['success'=>false,'error'=>$res->get_error_message()]); exit; }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$raw) { echo json_encode(['success'=>false,'error'=>'AIレスポンス空']); exit; }
    $raw  = preg_replace('/```json|```/', '', $raw);
    $raw  = trim($raw);
    $data = json_decode($raw, true);
    if (!$data && substr($raw,0,1)==='[') {
        $last = strrpos($raw,'}');
        if ($last!==false) $data = json_decode(substr($raw,0,$last+1).']', true);
    }
    if (!$data) { echo json_encode(['success'=>false,'error'=>'JSON解析失敗','raw'=>substr($raw,0,500)]); exit; }
    if ($data === [] || (is_array($data) && count($data)===0)) {
        echo json_encode(['success'=>false,'error'=>'ページから抽選情報が見つかりませんでした']); exit;
    }
    if (isset($data[0])) {
        echo json_encode(['success'=>true,'type'=>'multi','results'=>$data]);
    } else {
        echo json_encode(['success'=>true,'type'=>'single','data'=>$data]);
    }
    exit;
}

// AI解析リクエスト（1件・複数件対応）
if (($_POST['action_type'] ?? '') === 'ai_parse') {
    header('Content-Type: application/json; charset=utf-8');
    $text = sanitize_textarea_field($_POST['text'] ?? '');
    $xurl = esc_url_raw($_POST['xurl'] ?? '');
    if (!$text) { echo json_encode(['success'=>false,'error'=>'テキストなし']); exit; }

    // 本文内のURLを抽出してページ内容を取得
    $extra_content = '';
    preg_match_all('/https?:\/\/[^\s\x{3000}-\x{9FFF}「」【】（）]+/u', $text . ' ' . $xurl, $url_matches);
    $fetched_urls = [];
    foreach (array_unique($url_matches[0]) as $found_url) {
        $found_url = rtrim($found_url, '.,)');
        // XやTwitterのURLはスキップ
        if (preg_match('/twitter\.com|x\.com/i', $found_url)) continue;
        if (count($fetched_urls) >= 2) break; // 最大2件
        $page_res = wp_remote_get($found_url, ['timeout'=>8,'user-agent'=>'Mozilla/5.0']);
        if (is_wp_error($page_res)) continue;
        $html = wp_remote_retrieve_body($page_res);
        if (!$html) continue;
        // OGPとmeta descriptionを抽出
        $og_title = $og_desc = $meta_desc = '';
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_title = $m[1];
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $og_desc = $m[1];
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/i', $html, $m)) $meta_desc = $m[1];
        // ページ本文テキストも少し取得
        $body_text = strip_tags($html);
        $body_text = preg_replace('/\s+/', ' ', $body_text);
        $body_text = mb_substr(trim($body_text), 0, 500);
        $extra_content .= "\n\n【追加情報 from {$found_url}】\n";
        if ($og_title)  $extra_content .= "タイトル：{$og_title}\n";
        if ($og_desc)   $extra_content .= "説明：{$og_desc}\n";
        elseif ($meta_desc) $extra_content .= "説明：{$meta_desc}\n";
        if ($body_text) $extra_content .= "本文抜粋：{$body_text}";
        $fetched_urls[] = $found_url;
    }

    $today      = jst_date('Y年m月d日', time());
    $prompt_tpl = '以下のテキスト（XのポストやWebサイトのコピペなど）から抽選情報を抽出してください。
今日の日付：{today}
参照URL：{url}

テキスト：
{text}

【重要】
- 1件だけの場合はJSON1つ、複数件含まれる場合はJSON配列で返してください
- 前後に余計な文字・説明・コードブロックは一切不要です
- categoryはポケモンカード関連→pokeca、スニーカー・靴関連→sneaker、それ以外→other
- 日時はYYYY-MM-DDTHH:MM形式。日付はわかるが時間が書いていない場合、start_dateは00:00、end_dateは23:59を使うこと。result_dateは日付のみ（YYYY-MM-DD形式）でもOK。完全に不明なら空文字
- prefectureは「東京都」「全国」「オンライン」などの形式
- noteは応募条件・注意事項のみ30文字以内で簡潔に（長い説明文は不要）

1件の場合：
{"series":"","store":"","prefecture":"","category":"pokeca","start_date":"","end_date":"","result_date":"","lottery_url":"","note":""}

複数件の場合：
[{"series":"","store":"","prefecture":"","category":"pokeca","start_date":"","end_date":"","result_date":"","lottery_url":"","note":""},{"series":"..."}]';
    $prompt     = str_replace(['{today}','{url}','{text}'], [$today, $xurl ?: '不明', $text . $extra_content], $prompt_tpl);

    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    $res = wp_remote_post($gemini_url, [
        'timeout' => 30,
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8192],
        ]),
    ]);

    if (is_wp_error($res)) { echo json_encode(['success'=>false,'error'=>$res->get_error_message()]); exit; }
    $status_code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!$raw) { echo json_encode(['success'=>false,'error'=>'AIレスポンス空','debug'=>['status'=>$status_code,'body'=>$body]]); exit; }
    $raw  = preg_replace('/```json|```/', '', $raw);
    $raw  = trim($raw);
    $data = json_decode($raw, true);
    // 途中で切れた場合、]で閉じて再試行
    if (!$data) {
        $fixed = $raw;
        if (substr(trim($raw), 0, 1) === '[') {
            // 配列の場合：最後の完全なオブジェクトまで切り詰めて閉じる
            $last_brace = strrpos($raw, '}');
            if ($last_brace !== false) {
                $fixed = substr($raw, 0, $last_brace + 1) . ']';
                $data  = json_decode($fixed, true);
            }
        }
    }
    if (!$data) { echo json_encode(['success'=>false,'error'=>'JSON解析失敗','raw'=>substr($raw,0,300)]); exit; }
    if (isset($data[0])) {
        echo json_encode(['success'=>true,'type'=>'multi','results'=>$data]);
    } else {
        echo json_encode(['success'=>true,'type'=>'single','data'=>$data]);
    }
    exit;
}

// AI一括解析リクエスト
if (($_POST['action_type'] ?? '') === 'ai_bulk_parse') {
    header('Content-Type: application/json; charset=utf-8');
    $texts = $_POST['texts'] ?? [];
    if (empty($texts)) { echo json_encode(['success'=>false,'error'=>'テキストなし']); exit; }

    $today      = jst_date('Y年m月d日', time());
    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    $prompt_tpl = '以下のテキスト（XのポストやWebサイトのコピペなど）から抽選情報を抽出してください。
今日の日付：{today}
参照URL：{url}

テキスト：
{text}

【重要】
- 1件だけの場合はJSON1つ、複数件含まれる場合はJSON配列で返してください
- 前後に余計な文字・説明・コードブロックは一切不要です
- categoryはポケモンカード関連→pokeca、スニーカー・靴関連→sneaker、それ以外→other
- 日時はYYYY-MM-DDTHH:MM形式。日付はわかるが時間が書いていない場合、start_dateは00:00、end_dateは23:59を使うこと。result_dateは日付のみ（YYYY-MM-DD形式）でもOK。完全に不明なら空文字
- prefectureは「東京都」「全国」「オンライン」などの形式
- noteは応募条件・注意事項のみ30文字以内で簡潔に（長い説明文は不要）

1件の場合：
{"series":"","store":"","prefecture":"","category":"pokeca","start_date":"","end_date":"","result_date":"","lottery_url":"","note":""}

複数件の場合：
[{"series":"","store":"","prefecture":"","category":"pokeca","start_date":"","end_date":"","result_date":"","lottery_url":"","note":""},{"series":"..."}]';
    $results    = [];

    foreach ($texts as $text) {
        $text = sanitize_textarea_field($text);
        if (!trim($text)) continue;
        $prompt = str_replace(['{today}','{url}','{text}'], [$today, '不明', $text], $prompt_tpl);
        $res = wp_remote_post($gemini_url, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 8192],
            ]),
        ]);
        if (is_wp_error($res)) { $results[] = null; continue; }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        $raw  = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $raw  = preg_replace('/```json|```/', '', $raw);
        $raw  = trim($raw);
        $data = json_decode($raw, true);
        if (!$data && substr($raw, 0, 1) === '[') {
            $last_brace = strrpos($raw, '}');
            if ($last_brace !== false) {
                $data = json_decode(substr($raw, 0, $last_brace + 1) . ']', true);
            }
        }
        if (is_array($data) && isset($data[0])) {
            foreach ($data as $item) { $results[] = $item; }
        } else {
            $results[] = $data ?: null;
        }
        usleep(300000);
    }

    echo json_encode(['success'=>true,'results'=>$results]);
    exit;
}
$message = '';
// リダイレクト後のメッセージ復元
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated')       $message = '✅ 更新しました';
    if ($_GET['msg'] === 'deleted')       $message = '🗑 削除しました';
    if ($_GET['msg'] === 'show_new_post') $message = 'show_new_post';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('lottery_admin_action')) {
    $action = $_POST['action_type'] ?? '';

    if ($action === 'save') {
        $id       = intval($_POST['post_id'] ?? 0);
        $series   = sanitize_text_field($_POST['series'] ?? '');
        $store    = sanitize_text_field($_POST['store'] ?? '');
        $pref     = sanitize_text_field($_POST['prefecture'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'other');
        $start    = sanitize_text_field($_POST['start_date'] ?? '');
        $end      = sanitize_text_field($_POST['end_date'] ?? '');
        $result_raw = sanitize_text_field($_POST['result_date'] ?? '');
        // date型（YYYY-MM-DD）の場合はT00:00を付加
        $result = $result_raw ? (strlen($result_raw) === 10 ? $result_raw.'T00:00' : $result_raw) : '';
        $url_raw  = trim($_POST['lottery_url'] ?? '');
        if ($url_raw && !preg_match('/^https?:\/\//i', $url_raw)) $url_raw = 'https://' . $url_raw;
        $url      = esc_url_raw($url_raw);
        $note     = sanitize_textarea_field($_POST['note'] ?? '');
        $thumb_id = intval($_POST['thumbnail_id'] ?? 0);

        if ($id) {
            update_post_meta($id, 'series',      $series);
            update_post_meta($id, 'store',       $store);
            update_post_meta($id, 'prefecture',  $pref);
            update_post_meta($id, 'category',    $category);
            update_post_meta($id, 'start_date',  $start);
            update_post_meta($id, 'end_date',    $end);
            update_post_meta($id, 'result_date', $result);
            update_post_meta($id, 'lottery_url', $url);
            update_post_meta($id, 'note',        $note);
            if ($thumb_id) set_post_thumbnail($id, $thumb_id);
            wp_redirect(add_query_arg('msg', 'updated', get_permalink()));
            exit;
        } else {
            $new_id = wp_insert_post(['post_type'=>'lottery','post_title'=>$series?:'無題','post_status'=>'publish']);
            if ($new_id && !is_wp_error($new_id)) {
                update_post_meta($new_id, 'series',      $series);
                update_post_meta($new_id, 'store',       $store);
                update_post_meta($new_id, 'prefecture',  $pref);
                update_post_meta($new_id, 'category',    $category);
                update_post_meta($new_id, 'start_date',  $start);
                update_post_meta($new_id, 'end_date',    $end);
                update_post_meta($new_id, 'result_date', $result);
                update_post_meta($new_id, 'lottery_url', $url);
                update_post_meta($new_id, 'note',        $note);
                if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);
                $cat_emojis = ['pokeca'=>'🃏','sneaker'=>'👟','other'=>'🎮'];
                $cat_tags   = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
                $emoji      = $cat_emojis[$category] ?? '🎮';
                $tags       = $cat_tags[$category] ?? '#抽選情報';
                $start_str  = $start ? jst_date('n/j H:i', jst_strtotime($start)) : '未定';
                $end_str    = $end   ? jst_date('n/j H:i', jst_strtotime($end))   : '未定';
                $link       = get_permalink($new_id);
                // 自動投稿せず投稿文を生成してセッションに保存
                $xtext = "🆕 新着抽選情報\n\n{$emoji} {$series}\n🏪 {$store}\n📅 応募開始：{$start_str}\n⏰ 応募締切：{$end_str}\n\n詳細・応募はこちら👇\n{$link}\n\n{$tags}";
                if (!session_id()) session_start();
                $_SESSION['new_post_text'] = $xtext;
                wp_redirect(add_query_arg('msg', 'show_new_post', get_permalink()));
                exit;
            }
        }

    } elseif ($action === 'delete') {
        $id = intval($_POST['post_id'] ?? 0);
        if ($id) {
            wp_delete_post($id, true);
            wp_redirect(add_query_arg('msg', 'deleted', get_permalink()));
            exit;
        }

    } elseif ($action === 'manual_summary') {
        $manual_cat   = sanitize_text_field($_POST['manual_cat'] ?? 'pokeca');
        $cat_names    = ['pokeca'=>'🃏ポケカ', 'sneaker'=>'👟スニーカー', 'other'=>'🎮その他'];
        $cat_tags     = ['pokeca'=>'#ポケカ #抽選情報', 'sneaker'=>'#スニーカー #抽選情報', 'other'=>'#抽選情報'];
        $cat_urls     = ['pokeca'=>home_url('/pokeca/'), 'sneaker'=>home_url('/sneaker/'), 'other'=>home_url('/other/')];
        $mposts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish',
            'meta_query'=>[['key'=>'category','value'=>$manual_cat,'compare'=>'=']]]);
        $now_m = time();
        $urgent_m = []; $normal_m = []; $upcoming_m = [];
        foreach ($mposts as $mp) {
            $end_m   = get_post_meta($mp->ID, 'end_date', true);
            $start_m = get_post_meta($mp->ID, 'start_date', true);
            $end_ts_m   = $end_m   ? jst_strtotime($end_m)   : 0;
            $start_ts_m = $start_m ? jst_strtotime($start_m) : 0;
            if ($end_ts_m && $now_m > $end_ts_m) continue;
            $series_m = get_post_meta($mp->ID, 'series', true) ?: $mp->post_title;
            $store_m  = get_post_meta($mp->ID, 'store', true)  ?: '';
            $end_str_m = $end_ts_m ? jst_date('n/j H:i', $end_ts_m) : '未定';
            $lot_url_m = get_post_meta($mp->ID, 'lottery_url', true);
            $url_line_m = $lot_url_m ? "\n  👉 {$lot_url_m}" : "";
            if ($end_ts_m && ($end_ts_m - $now_m) < 86400)
                $urgent_m[]   = "・{$series_m}｜{$store_m}\n　⚡締切間近 締切:{$end_str_m}{$url_line_m}";
            elseif ($start_ts_m && $now_m < $start_ts_m)
                $upcoming_m[] = "・{$series_m}｜{$store_m}\n　🔜近日開始 締切:{$end_str_m}{$url_line_m}";
            else
                $normal_m[]   = "・{$series_m}｜{$store_m}\n　✅受付中 締切:{$end_str_m}{$url_line_m}";
        }
        $active_m = array_merge($urgent_m, $normal_m, $upcoming_m);
        if (!empty($active_m)) {
            $list_m  = implode("\n", $active_m);
            $name_m  = $cat_names[$manual_cat] ?? '🎮その他';
            $tag_m   = $cat_tags[$manual_cat]  ?? '#抽選情報';
            $url_m   = $cat_urls[$manual_cat]  ?? home_url('/');
            $tags_m2 = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報 #抽選 #当選','sneaker'=>'#スニーカー #スニーカー抽選 #Nike #Jordan #抽選情報 #抽選 #当選','other'=>'#抽選情報 #抽選 #当選'];
            $t_m2 = $tags_m2[$manual_cat] ?? '#抽選情報 #抽選 #当選';
            $xtext_m = "【{$name_m} 抽選まとめ】\n\n{$list_m}\n\n一覧はこちら👇\n{$url_m}\n\n{$t_m2}";
            $_SESSION['summary_text'] = $xtext_m;
            $message = 'show_summary';
        } else {
            $message = '⚠️ 投稿できる抽選情報がありません';
        }
    }

    // 個別文章生成
    if ($action === 'single_text') {
        $pid = intval($_POST['single_id'] ?? 0);
        if ($pid) {
            $series_s = get_post_meta($pid,'series',true) ?: '';
            $store_s  = get_post_meta($pid,'store',true)  ?: '';
            $cat_s    = get_post_meta($pid,'category',true) ?: 'other';
            $start_s  = get_post_meta($pid,'start_date',true) ?: '';
            $end_s    = get_post_meta($pid,'end_date',true)   ?: '';
            $app_url_s  = get_post_meta($pid,'lottery_url',true) ?: get_permalink($pid);
            $cat_urls_s   = ['pokeca'=>home_url('/pokeca/'),'sneaker'=>home_url('/sneaker/'),'other'=>home_url('/other/')];
            $list_url_s   = $cat_urls_s[$cat_s] ?? home_url('/');
            $cat_emojis_s = ['pokeca'=>'🃏','sneaker'=>'👟','other'=>'🎮'];
            $cat_tags_s   = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
            $emoji_s  = $cat_emojis_s[$cat_s] ?? '🎮';
            $start_str_s = $start_s ? jst_date('n/j H:i', jst_strtotime($start_s)) : '未定';
            $end_str_s   = $end_s   ? jst_date('n/j H:i', jst_strtotime($end_s))   : '未定';
            $extra_tags  = lottery_build_tags($cat_tags_s[$cat_s], [$series_s]);
            $xtext_s = "{$emoji_s} 新着抽選情報\n\n{$series_s}\n🏪 {$store_s}\n📅 開始：{$start_str_s}\n⏰ 締切：{$end_str_s}\n\n👉 {$app_url_s}\n\n一覧はこちら👇\n{$list_url_s}\n\n{$extra_tags}";
            $_SESSION['single_text'] = $xtext_s;
            $_SESSION['single_id']   = $pid;
            $message = 'show_single';
        }
    }

    // 24時間以内締切まとめ
    if ($action === 'urgent_summary') {
        $now_u = time();
        $posts_u = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
        $cats_u = ['pokeca'=>[],'sneaker'=>[],'other'=>[]];
        $cat_names_u = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
        $cat_tags_u  = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
        $cat_urls_u  = ['pokeca'=>home_url('/pokeca/'),'sneaker'=>home_url('/sneaker/'),'other'=>home_url('/other/')];
        foreach ($posts_u as $pu) {
            $end_u = get_post_meta($pu->ID,'end_date',true);
            if (!$end_u) continue;
            $end_ts_u = jst_strtotime($end_u);
            if ($end_ts_u <= $now_u) continue; // 終了済みスキップ
            if (($end_ts_u - $now_u) > 86400) continue; // 24時間超スキップ
            $cat_u    = get_post_meta($pu->ID,'category',true) ?: 'other';
            $series_u = get_post_meta($pu->ID,'series',true)   ?: $pu->post_title;
            $store_u  = get_post_meta($pu->ID,'store',true)    ?: '';
            $end_str_u= jst_date('n/j H:i', $end_ts_u);
            $lot_url_u = get_post_meta($pu->ID,'lottery_url',true);
            $cats_u[$cat_u][] = "・{$series_u}｜{$store_u} ⏰{$end_str_u}締切" . ($lot_url_u ? "\n  👉 {$lot_url_u}" : "");
        }
        $result_texts_u = [];
        foreach ($cats_u as $cat_u => $lines_u) {
            if (empty($lines_u)) continue;
            $list_u   = implode("
", $lines_u);
            $result_texts_u[] = "【{$cat_names_u[$cat_u]} 締切間近⚡】

{$list_u}

一覧はこちら👇
{$cat_urls_u[$cat_u]}
{$cat_tags_u[$cat_u]}";
        }
        if (!empty($result_texts_u)) {
            $_SESSION['summary_text'] = implode("

---

", $result_texts_u);
            $message = 'show_summary';
        } else {
            $message = 'show_summary';
            $_SESSION['summary_text'] = '';
        }
    }

    // 本日追加分まとめ
    if ($action === 'today_summary') {
        $now_t    = time();
        $jst_t    = $now_t + 9 * 3600;
        $today_s  = date('Y-m-d 00:00:00', $jst_t - 9*3600);
        $today_e  = date('Y-m-d 23:59:59', $jst_t - 9*3600);
        $posts_t  = get_posts([
            'post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish',
            'date_query'=>[['after'=>$today_s,'before'=>$today_e,'inclusive'=>true]],
        ]);
        $cats_t = ['pokeca'=>[],'sneaker'=>[],'other'=>[]];
        $cat_names_t = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
        $cat_tags_t  = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
        $cat_urls_t  = ['pokeca'=>home_url('/pokeca/'),'sneaker'=>home_url('/sneaker/'),'other'=>home_url('/other/')];
        foreach ($posts_t as $pt) {
            $cat_t    = get_post_meta($pt->ID,'category',true) ?: 'other';
            $series_t = get_post_meta($pt->ID,'series',true)   ?: $pt->post_title;
            $store_t  = get_post_meta($pt->ID,'store',true)    ?: '';
            $end_t    = get_post_meta($pt->ID,'end_date',true);
            $end_str_t= $end_t ? jst_date('n/j H:i', jst_strtotime($end_t)) : '未定';
            $lot_url_t = get_post_meta($pt->ID,'lottery_url',true);
            $cats_t[$cat_t][] = "・{$series_t}｜{$store_t} 締切:{$end_str_t}" . ($lot_url_t ? "\n  👉 {$lot_url_t}" : "");
        }
        $result_texts_t = [];
        foreach ($cats_t as $cat_t => $lines_t) {
            if (empty($lines_t)) continue;
            $list_t   = implode("
", $lines_t);
            $result_texts_t[] = "【{$cat_names_t[$cat_t]} 本日追加の新着抽選🆕】

{$list_t}

一覧はこちら👇
{$cat_urls_t[$cat_t]}
{$cat_tags_t[$cat_t]}";
        }
        if (!empty($result_texts_t)) {
            $_SESSION['summary_text'] = implode("

---

", $result_texts_t);
            $message = 'show_summary';
        } else {
            $message = 'show_summary';
            $_SESSION['summary_text'] = '';
        }
    }

    // 本日の発表まとめ
    if ($action === 'result_summary') {
        $jst_now   = time() + 9 * 3600;
        $today_ymd = date('Y-m-d', $jst_now);
        $all_posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish']);
        $lines = [];
        foreach ($all_posts as $rp) {
            $result_date = get_post_meta($rp->ID, 'result_date', true);
            if (!$result_date) continue;
            $result_ts = jst_strtotime($result_date);
            if (date('Y-m-d', $result_ts) !== $today_ymd) continue;
            $series_r = get_post_meta($rp->ID, 'series', true) ?: $rp->post_title;
            $store_r  = get_post_meta($rp->ID, 'store', true)  ?: '';
            $time_r   = jst_date('H:i', $result_ts);
            $lines[]  = "・{$series_r}｜{$store_r} 発表:{$time_r}";
        }
        if (!empty($lines)) {
            $list_r = implode("\n", $lines);
            $_SESSION['summary_text'] = "🏆 本日の抽選発表まとめ\n\n{$list_r}\n\n結果はサイトでチェック👇\n" . home_url('/') . "\n\n#ポケカ抽選 #スニーカー抽選 #抽選サーチ";
        } else {
            $_SESSION['summary_text'] = '';
        }
        $message = 'show_summary';
    }

    // ロック解除
    if ($action === 'clear_lock') {
        $lock_key = sanitize_text_field($_POST['lock_key'] ?? '');
        if ($lock_key && strpos($lock_key, 'lottery_') === 0) {
            delete_option($lock_key);
            $message = '✅ ロックを解除しました';
        }
    }

    // 一括登録
    if ($action === 'bulk_save') {
        $bulk_cat     = sanitize_text_field($_POST['bulk_cat'] ?? 'other');
        $items_series = $_POST['item_series'] ?? [];
        $items_store  = $_POST['item_store']  ?? [];
        $items_pref   = $_POST['item_pref']   ?? [];
        $items_start  = $_POST['item_start']  ?? [];
        $items_end    = $_POST['item_end']    ?? [];
        $items_result = $_POST['item_result'] ?? [];
        $items_url    = $_POST['item_url']    ?? [];
        $items_note   = $_POST['item_note']   ?? [];
        $items_thumb  = $_POST['item_thumb']  ?? [];

        $cat_labels = ['pokeca'=>'🃏ポケカ','sneaker'=>'👟スニーカー','other'=>'🎮その他'];
        $cat_tags   = ['pokeca'=>'#ポケカ #ポケモンカード #ポケカ抽選 #抽選情報','sneaker'=>'#スニーカー #スニーカー抽選 #抽選情報','other'=>'#抽選情報 #限定グッズ'];
        $cat_url    = ['pokeca'=>home_url('/pokeca/'),'sneaker'=>home_url('/sneaker/'),'other'=>home_url('/other/')][$bulk_cat] ?? home_url('/');

        $saved_lines = [];
        $saved_count = 0;
        foreach ($items_series as $i => $series) {
            $series = sanitize_text_field($series);
            $store  = sanitize_text_field($items_store[$i]  ?? '');
            $pref   = sanitize_text_field($items_pref[$i]   ?? '');
            $start  = sanitize_text_field($items_start[$i]  ?? '');
            $end    = sanitize_text_field($items_end[$i]    ?? '');
            $result_raw2 = sanitize_text_field($items_result[$i] ?? '');
            $result = $result_raw2 ? (strlen($result_raw2) === 10 ? $result_raw2.'T00:00' : $result_raw2) : '';
            $url_raw = trim($items_url[$i] ?? '');
            if ($url_raw && !preg_match('/^https?:\/\//i', $url_raw)) $url_raw = 'https://' . $url_raw;
            $url    = esc_url_raw($url_raw);
            $note   = sanitize_text_field($items_note[$i]   ?? '');
            if (!$series) continue;



            $pid = wp_insert_post([
                'post_type'   => 'lottery',
                'post_title'  => $series . ($store ? '｜'.$store : ''),
                'post_status' => 'publish',
            ]);
            if ($pid && !is_wp_error($pid)) {
                update_post_meta($pid, 'series',      $series);
                update_post_meta($pid, 'category',    $bulk_cat);
                update_post_meta($pid, 'store',       $store);
                update_post_meta($pid, 'prefecture',  $pref);
                update_post_meta($pid, 'start_date',  $start);
                update_post_meta($pid, 'end_date',    $end);
                update_post_meta($pid, 'result_date', $result);
                update_post_meta($pid, 'lottery_url', $url);
                update_post_meta($pid, 'note',        $note);
                $thumb_id = intval($items_thumb[$i] ?? 0);
                if ($thumb_id) set_post_thumbnail($pid, $thumb_id);
                $end_str  = $end  ? jst_date('n/j H:i', jst_strtotime($end))  : '未定';
                $pref_str = $pref ? "({$pref})" : '';
                $store_str = $store ? "｜{$store}{$pref_str}" : '';
                $saved_lines[] = "・{$series}{$store_str} 締切:{$end_str}" . ($url ? " 👉{$url}" : '');
                $saved_count++;
            }
        }

        if ($saved_count > 0) {
            $list_text = implode("\n", $saved_lines);
            $tags      = $cat_tags[$bulk_cat] ?? '#抽選情報';
            $cat_label = $cat_labels[$bulk_cat] ?? '🎮その他';
            $_SESSION['bulk_text'] = "【{$cat_label} 新着抽選情報まとめ🆕】\n\n{$list_text}\n\n一覧はこちら👇\n{$cat_url}\n{$tags}";
            $message = 'bulk_saved';
        } else {
            $message = 'bulk_error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php wp_enqueue_media(); wp_head(); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>抽選管理｜抽選サーチ</title>
<style>
:root{--bg:#0a0e1a;--surface:#111827;--surface2:#1a2235;--border:#1e2d45;--text:#e8f0fe;--muted:#6b7fa3;--accent:#00d4ff;--red:#ff4444;--green:#00e676;--yellow:#ffd600;--font:'Noto Sans JP',sans-serif;}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
body{background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh;padding-bottom:80px;}
.adm-header{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.adm-logo{font-size:16px;font-weight:900;letter-spacing:1px;color:var(--accent);}
.adm-logo span{color:var(--text);}
.site-btn{font-size:11px;color:var(--muted);border:1px solid var(--border);padding:5px 10px;border-radius:6px;}
.msg{margin:12px 16px;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:700;background:var(--surface2);border:1px solid var(--green);color:var(--green);}
.tabs{display:flex;background:var(--surface);border-bottom:1px solid var(--border);}
.tab-btn{flex:1;padding:12px 8px;font-size:13px;font-weight:700;color:var(--muted);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;font-family:var(--font);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.wrap{padding:16px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:16px;}
.form-label{font-size:11px;color:var(--muted);margin-bottom:6px;}
.form-row{margin-bottom:14px;}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.inp{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:11px 14px;color:var(--text);font-size:14px;font-family:var(--font);outline:none;}
.inp:focus{border-color:var(--accent);}
.sel{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:11px 14px;color:var(--text);font-size:14px;font-family:var(--font);outline:none;-webkit-appearance:none;}
.btn-primary{width:100%;background:linear-gradient(135deg,var(--accent),#0090cc);color:#000;font-weight:900;font-size:16px;padding:14px;border-radius:10px;border:none;cursor:pointer;font-family:var(--font);}
.btn-secondary{width:100%;background:var(--surface2);color:var(--muted);font-size:14px;padding:12px;border-radius:10px;border:1px solid var(--border);cursor:pointer;font-family:var(--font);margin-top:8px;}
.btn-summary{flex:1;padding:14px 8px;border-radius:10px;border:none;font-size:13px;font-weight:900;cursor:pointer;font-family:var(--font);}
.list-item{background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:8px;overflow:hidden;}
.list-top{padding:12px 14px;display:flex;align-items:center;gap:10px;}
.list-emoji{font-size:22px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:8px;flex-shrink:0;}
.list-info{flex:1;min-width:0;}
.list-series{font-size:13px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.list-store{font-size:11px;color:var(--muted);margin-top:2px;}
.list-dates{padding:0 14px 10px;display:flex;gap:12px;font-size:11px;color:var(--muted);}
.list-dates span{color:var(--text);font-weight:700;margin-left:4px;}
.list-actions{display:flex;border-top:1px solid var(--border);}
.btn-edit{flex:1;padding:10px;background:none;border:none;color:var(--accent);font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font);}
.btn-del{flex:1;padding:10px;background:none;border:none;color:var(--red);font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font);border-left:1px solid var(--border);}
.badge{font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;margin-top:4px;display:inline-block;}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;display:none;align-items:center;justify-content:center;padding:20px;}
.modal-bg.show{display:flex;}
.modal-box{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;width:100%;max-width:320px;}
.modal-btns{display:flex;gap:10px;margin-top:20px;}
.modal-cancel{flex:1;padding:12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;cursor:pointer;font-family:var(--font);}
.modal-confirm{flex:1;padding:12px;background:var(--red);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:var(--font);}
</style>
<script>
function showTab(t){
  ['add','list','post','bulk'].forEach(function(n){
    var p = document.getElementById('pane-'+n);
    var b = document.getElementById('tab-'+n);
    if(p) p.style.display = t===n?'block':'none';
    if(b) b.classList.toggle('active',t===n);
  });
}
function editPost(id,series,store,pref,cat,start,end,result,url,note,tid,turl){
  document.getElementById('f-id').value=id;
  document.getElementById('f-series').value=series;
  document.getElementById('f-store').value=store;
  document.getElementById('f-pref').value=pref;
  document.getElementById('f-cat').value=cat;
  document.getElementById('f-start').value=start;
  document.getElementById('f-end').value=end;
  document.getElementById('f-url').value=url;
  document.getElementById('f-note').value=note;
  document.getElementById('f-tid').value=tid||0;
  if(turl){document.getElementById('f-timg').src=turl;document.getElementById('f-tprev').style.display='block';}
  else{document.getElementById('f-tprev').style.display='none';}
  document.getElementById('f-title').textContent='✏️ 編集';
  document.getElementById('f-submit').textContent='更新する';
  document.getElementById('f-cancel').style.display='block';
  showTab('add');window.scrollTo({top:0,behavior:'smooth'});
}
function cancelEdit(){
  document.getElementById('f-id').value=0;
  document.getElementById('f-tid').value=0;
  document.getElementById('f-tprev').style.display='none';
  document.getElementById('add-form').reset();
  document.getElementById('f-title').textContent='📝 新規登録';
  document.getElementById('f-submit').textContent='登録する';
  document.getElementById('f-cancel').style.display='none';
}
function confirmDelete(id,series){
  document.getElementById('del-id').value=id;
  document.getElementById('del-text').textContent='「'+series+'」を削除しますか？';
  document.getElementById('del-modal').classList.add('show');
}
function closeModal(){document.getElementById('del-modal').classList.remove('show');}
var mediaUploader;
function openMedia(){
  if(mediaUploader){mediaUploader.open();return;}
  mediaUploader=wp.media({title:'画像を選択',button:{text:'使用する'},multiple:false});
  mediaUploader.on('select',function(){
    var a=mediaUploader.state().get('selection').first().toJSON();
    document.getElementById('f-tid').value=a.id;
    document.getElementById('f-timg').src=a.url;
    document.getElementById('f-tprev').style.display='block';
  });
  mediaUploader.open();
}
// URLフィールドのhttps自動補完
document.addEventListener('DOMContentLoaded', function() {
  // 通常登録フォームのURL
  var fUrl = document.getElementById('f-url');
  if (fUrl) {
    fUrl.addEventListener('blur', function() {
      var v = fUrl.value.trim();
      if (v && !/^https?:\/\//i.test(v)) fUrl.value = 'https://' + v;
    });
  }
  // 一括登録フォームのURL（初期枠・動的追加枠どちらも対応）
  document.addEventListener('blur', function(e) {
    if (e.target && e.target.name === 'item_url[]') {
      var v = e.target.value.trim();
      if (v && !/^https?:\/\//i.test(v)) e.target.value = 'https://' + v;
    }
  }, true);
});
</script>
</head>
<body>

<div class="adm-header">
  <div class="adm-logo">抽選<span>管理</span></div>
  <a href="<?php echo home_url('/'); ?>" class="site-btn">サイトを見る →</a>
</div>

<?php if ($message): ?>
<div class="msg"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<div class="tabs">
  <button class="tab-btn active" id="tab-add" onclick="showTab('add')">＋ 登録・編集</button>
  <button class="tab-btn" id="tab-bulk" onclick="showTab('bulk')">📦 一括登録</button>
  <button class="tab-btn" id="tab-list" onclick="showTab('list')">📋 一覧</button>
  <button class="tab-btn" id="tab-post" onclick="showTab('post')">📤 まとめ投稿</button>
</div>

<!-- 登録・編集フォーム -->
<div id="pane-add" class="wrap">

  <!-- AI自動入力 -->
  <div class="card" style="border-color:#a78bfa;margin-bottom:12px;">
    <div style="font-size:14px;font-weight:900;color:#a78bfa;margin-bottom:8px;">🤖 AI自動入力</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">画像・URL・テキストを組み合わせて入力→まとめて解析</div>

    <div class="form-row">
      <div class="form-label">🖼️ 画像（スクショ・写真）</div>
      <div style="border:2px dashed var(--border);border-radius:10px;padding:12px;text-align:center;cursor:pointer;position:relative;" id="ai-img-drop">
        <input type="file" id="ai-img-input" accept="image/*" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
        <div id="ai-img-preview" style="display:none;margin-bottom:6px;">
          <img id="ai-img-thumb" src="" style="max-height:100px;max-width:100%;border-radius:6px;object-fit:contain;">
        </div>
        <div id="ai-img-label" style="font-size:12px;color:var(--muted);">📎 タップして画像を選択</div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-label">🔗 URL（店舗サイト・応募ページなど）</div>
      <input type="text" id="ai-fetch-url" class="inp" placeholder="https://livepocket.jp/e/xxx など">
    </div>

    <div class="form-row">
      <div class="form-label">📝 テキスト（Xのポスト本文など）</div>
      <textarea id="ai-text" class="inp" rows="4" placeholder="ポスト本文をここに貼り付けてください" style="resize:vertical;"></textarea>
    </div>

    <input type="hidden" id="ai-x-url" value="">
    <button type="button" id="ai-parse-btn" style="width:100%;padding:12px;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;font-weight:900;font-size:14px;border:none;border-radius:10px;cursor:pointer;">✨ まとめて解析してフォームに入力</button>
    <div id="ai-status" style="font-size:12px;color:var(--muted);margin-top:8px;text-align:center;display:none;"></div>
  </div>

  <form method="post" id="add-form">
    <?php wp_nonce_field('lottery_admin_action'); ?>
    <input type="hidden" name="action_type" value="save">
    <input type="hidden" name="post_id" value="0" id="f-id">
    <div class="card">
      <div style="font-size:16px;font-weight:900;margin-bottom:16px;" id="f-title">📝 新規登録</div>
      <div class="form-row">
        <div class="form-label">カテゴリ</div>
        <select name="category" class="sel" id="f-cat">
          <option value="pokeca">🃏 ポケカ</option>
          <option value="sneaker">👟 スニーカー</option>
          <option value="other">🎮 その他</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-label">シリーズ名・商品名 *</div>
        <input type="text" name="series" class="inp" id="f-series" placeholder="例：夜明けのトリオ" required>
      </div>
      <div class="form-row">
        <div class="form-label">販売店舗</div>
        <input type="text" name="store" class="inp" id="f-store" placeholder="例：ポケモンセンターオンライン">
      </div>
      <div class="form-row">
        <div class="form-label">都道府県</div>
        <select name="prefecture" class="sel" id="f-pref" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);font-family:var(--font);font-size:14px;">
          <option value="">未設定</option>
          <option value="全国">🗾 全国（複数店舗）</option>
          <option value="オンライン">🌐 オンライン</option>
          <optgroup label="北海道・東北">
            <option value="北海道">北海道</option><option value="青森県">青森県</option><option value="岩手県">岩手県</option><option value="宮城県">宮城県</option><option value="秋田県">秋田県</option><option value="山形県">山形県</option><option value="福島県">福島県</option>
          </optgroup>
          <optgroup label="関東">
            <option value="茨城県">茨城県</option><option value="栃木県">栃木県</option><option value="群馬県">群馬県</option><option value="埼玉県">埼玉県</option><option value="千葉県">千葉県</option><option value="東京都">東京都</option><option value="神奈川県">神奈川県</option>
          </optgroup>
          <optgroup label="中部">
            <option value="新潟県">新潟県</option><option value="富山県">富山県</option><option value="石川県">石川県</option><option value="福井県">福井県</option><option value="山梨県">山梨県</option><option value="長野県">長野県</option><option value="岐阜県">岐阜県</option><option value="静岡県">静岡県</option><option value="愛知県">愛知県</option><option value="三重県">三重県</option>
          </optgroup>
          <optgroup label="近畿">
            <option value="滋賀県">滋賀県</option><option value="京都府">京都府</option><option value="大阪府">大阪府</option><option value="兵庫県">兵庫県</option><option value="奈良県">奈良県</option><option value="和歌山県">和歌山県</option>
          </optgroup>
          <optgroup label="中国・四国">
            <option value="鳥取県">鳥取県</option><option value="島根県">島根県</option><option value="岡山県">岡山県</option><option value="広島県">広島県</option><option value="山口県">山口県</option><option value="徳島県">徳島県</option><option value="香川県">香川県</option><option value="愛媛県">愛媛県</option><option value="高知県">高知県</option>
          </optgroup>
          <optgroup label="九州・沖縄">
            <option value="福岡県">福岡県</option><option value="佐賀県">佐賀県</option><option value="長崎県">長崎県</option><option value="熊本県">熊本県</option><option value="大分県">大分県</option><option value="宮崎県">宮崎県</option><option value="鹿児島県">鹿児島県</option><option value="沖縄県">沖縄県</option>
          </optgroup>
        </select>
      </div>
      <div class="form-row-2">
        <div><div class="form-label">応募開始</div><input type="datetime-local" name="start_date" class="inp" id="f-start" style="color-scheme:dark;"></div>
        <div><div class="form-label">応募締切</div><input type="datetime-local" name="end_date" class="inp" id="f-end" style="color-scheme:dark;"></div>
      </div>
      <div class="form-row">
        <div class="form-label">🏆 抽選発表日（終日・任意）</div>
        <input type="date" name="result_date" class="inp" id="f-result" style="color-scheme:dark;">
      </div>
      <div class="form-row">
        <div class="form-label">アイキャッチ画像</div>
        <div id="f-tprev" style="display:none;margin-bottom:8px;"><img id="f-timg" src="" style="width:100%;max-height:140px;object-fit:contain;border-radius:8px;background:var(--surface2);"></div>
        <input type="hidden" name="thumbnail_id" id="f-tid" value="">
        <button type="button" class="btn-secondary" style="margin-top:0;" onclick="openMedia()">📷 画像を選択</button>
      </div>
      <div class="form-row">
        <div class="form-label">応募URL</div>
        <input type="text" name="lottery_url" class="inp" id="f-url" placeholder="https://...">
      </div>
      <div class="form-row">
        <div class="form-label">備考</div>
        <textarea name="note" class="inp" id="f-note" rows="2" placeholder="備考があれば" style="resize:vertical;"></textarea>
      </div>
      <button type="submit" class="btn-primary" id="f-submit">登録する</button>
      <button type="button" class="btn-secondary" id="f-cancel" style="display:none;" onclick="cancelEdit()">キャンセル</button>
    </div>
  </form>
</div>

<!-- 一覧 -->
<div id="pane-list" style="display:none;" class="wrap">
<div style="padding:12px 14px 4px;">
  <input type="text" id="admin-search" placeholder="🔍 店舗名・シリーズで検索..." oninput="adminSearch()" style="width:100%;background:#1a1f2e;border:1px solid #30363d;border-radius:8px;padding:10px 14px;color:#e6edf3;font-size:14px;box-sizing:border-box;outline:none;">
  <div style="display:flex;gap:8px;margin-top:8px;">
    <button onclick="filterDeadline('today')" id="filter-today" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#ff4444;font-size:12px;font-weight:700;cursor:pointer;">⏰ 今日締切</button>
    <button onclick="filterDeadline('tomorrow')" id="filter-tomorrow" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#ffd600;font-size:12px;font-weight:700;cursor:pointer;">📅 明日締切</button>
    <button onclick="filterDeadline('all')" id="filter-all" style="flex:1;padding:8px;border-radius:8px;border:1px solid #00d4ff44;background:rgba(0,212,255,.1);color:#00d4ff;font-size:12px;font-weight:700;cursor:pointer;">全て表示</button>
  </div>
  <div style="display:flex;gap:8px;margin-top:6px;">
    <button onclick="filterDeadline('no-pref')" id="filter-no-pref" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#a78bfa;font-size:12px;font-weight:700;cursor:pointer;">📍 都道府県未設定</button>
    <button onclick="filterDeadline('no-result')" id="filter-no-result" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#f5c518;font-size:12px;font-weight:700;cursor:pointer;">🏆 発表日未設定</button>
  </div>
  <div id="admin-search-count" style="font-size:11px;color:#6b7fa3;margin-top:6px;padding-left:2px;"></div>
  <div style="display:flex;gap:8px;margin-top:8px;">
    <button onclick="showTab('post')" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#ff9f43;font-size:12px;font-weight:700;cursor:pointer;">🌅 朝まとめ文</button>
    <button onclick="showTab('post')" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#a78bfa;font-size:12px;font-weight:700;cursor:pointer;">🌆 夜まとめ文</button>
    <button onclick="showTab('post')" style="flex:1;padding:8px;border-radius:8px;border:1px solid #30363d;background:#1a1f2e;color:#58a6ff;font-size:12px;font-weight:700;cursor:pointer;">🌙 追加まとめ文</button>
  </div>
</div>
<script>
var activeFilter = 'all';
function adminSearch() {
  var q = document.getElementById('admin-search').value.trim().toLowerCase();
  var items = document.querySelectorAll('.list-item');
  var count = 0;
  var now = new Date();
  var todayStr = now.toISOString().slice(0,10);
  var tomorrow = new Date(now); tomorrow.setDate(tomorrow.getDate()+1);
  var tomorrowStr = tomorrow.toISOString().slice(0,10);
  items.forEach(function(item) {
    var text = (item.dataset.store + ' ' + item.dataset.series).toLowerCase();
    var endTs = item.dataset.endts ? parseInt(item.dataset.endts) : 0;
    var endDate = endTs ? new Date(endTs*1000).toISOString().slice(0,10) : '';
    var matchQ = !q || text.indexOf(q) !== -1;
    var matchF = true;
    if (activeFilter === 'today')     matchF = endDate === todayStr;
    if (activeFilter === 'tomorrow')  matchF = endDate === tomorrowStr;
    if (activeFilter === 'no-pref')   matchF = !item.dataset.pref;
    if (activeFilter === 'no-result') matchF = !item.dataset.result;
    var show = matchQ && matchF;
    item.style.display = show ? '' : 'none';
    if (show) count++;
  });
  var label = q ? count+'件ヒット' : (activeFilter!=='all' ? count+'件' : '');
  document.getElementById('admin-search-count').textContent = label;
}
function filterDeadline(type) {
  activeFilter = type;
  var allTypes = ['today','tomorrow','all','no-pref','no-result'];
  allTypes.forEach(function(t) {
    var btn = document.getElementById('filter-'+t);
    if (!btn) return;
    var colors = {
      'today':     {on:'rgba(255,68,68,.2)',    border:'#ff4444'},
      'tomorrow':  {on:'rgba(255,214,0,.2)',     border:'#ffd600'},
      'all':       {on:'rgba(0,212,255,.15)',    border:'#00d4ff'},
      'no-pref':   {on:'rgba(167,139,250,.2)',   border:'#a78bfa'},
      'no-result': {on:'rgba(245,197,24,.2)',    border:'#f5c518'},
    };
    btn.style.background  = t===type ? (colors[t]?.on || '#1a1f2e') : '#1a1f2e';
    btn.style.borderColor = t===type ? (colors[t]?.border || '#30363d') : '#30363d';
  });
  adminSearch();
}
</script>
<?php
$posts = get_posts(['post_type'=>'lottery','numberposts'=>-1,'post_status'=>'publish','orderby'=>'date','order'=>'DESC']);
$cat_emojis = ['pokeca'=>'🃏','sneaker'=>'👟','other'=>'🎮'];
if (empty($posts)):
?>
  <div style="text-align:center;padding:40px;color:var(--muted);">まだ登録されていません</div>
<?php else: foreach ($posts as $p):
  $series  = get_post_meta($p->ID, 'series', true)     ?: $p->post_title;
  $store   = get_post_meta($p->ID, 'store', true)      ?: '';
  $pref    = get_post_meta($p->ID, 'prefecture', true) ?: '';
  $result  = get_post_meta($p->ID, 'result_date', true) ?: '';
  $cat     = get_post_meta($p->ID, 'category', true)   ?: 'other';
  $start   = get_post_meta($p->ID, 'start_date', true) ?: '';
  $end     = get_post_meta($p->ID, 'end_date', true)   ?: '';
  $url     = get_post_meta($p->ID, 'lottery_url', true)?: '';
  $note    = get_post_meta($p->ID, 'note', true)       ?: '';
  $emoji   = $cat_emojis[$cat] ?? '🎮';
  $now     = time();
  $end_ts  = $end   ? jst_strtotime($end)   : 0;
  $start_ts= $start ? jst_strtotime($start) : 0;
  if ($end_ts && $now > $end_ts)            { $sl='終了';       $sc='background:rgba(255,255,255,.05);color:#6b7fa3;'; }
  elseif ($start_ts && $now < $start_ts)    { $sl='近日開始';   $sc='background:rgba(255,214,0,.1);color:#ffd600;'; }
  elseif ($end_ts && ($end_ts-$now)<86400)  { $sl='⚡締切間近'; $sc='background:rgba(255,68,68,.1);color:#ff4444;'; }
  else                                       { $sl='受付中';     $sc='background:rgba(0,212,255,.1);color:#00d4ff;'; }
  $sl_local = $start ? date('Y-m-d\TH:i', jst_strtotime($start)) : '';
  $el_local = $end   ? date('Y-m-d\TH:i', jst_strtotime($end))   : '';
  $tid2  = get_post_thumbnail_id($p->ID);
  $turl2 = $tid2 ? wp_get_attachment_image_url($tid2, 'medium') : '';
?>
  <div class="list-item" data-store="<?php echo esc_attr($store); ?>" data-series="<?php echo esc_attr($series); ?>" data-endts="<?php echo $end_ts; ?>" data-pref="<?php echo esc_attr($pref); ?>" data-result="<?php echo esc_attr($result); ?>">
    <div class="list-top">
      <div class="list-emoji"><?php echo $emoji; ?></div>
      <div class="list-info">
        <div class="list-series"><?php echo esc_html($series); ?></div>
        <?php if ($result): ?>
          <div style="font-size:10px;color:#f5c518;margin-bottom:2px;">🏆 発表: <?php echo date('m/d', jst_strtotime($result)); ?></div>
        <?php else: ?>
          <div style="font-size:10px;color:#6b7fa3;margin-bottom:2px;">🏆 発表日: 未設定</div>
        <?php endif; ?>
        <div class="list-store"><?php echo esc_html($store); ?><?php if ($pref): ?> <span style="font-size:10px;background:rgba(167,139,250,.1);color:#a78bfa;border:1px solid rgba(167,139,250,.25);border-radius:4px;padding:1px 5px;"><?php echo esc_html($pref); ?></span><?php endif; ?></div>
        <span class="badge" style="<?php echo $sc; ?>"><?php echo $sl; ?></span>
      </div>
    </div>
    <div class="list-dates">
      <div>開始<span><?php echo $start ? jst_date('n/j H:i', jst_strtotime($start)) : '未定'; ?></span></div>
      <div>締切<span><?php echo $end   ? jst_date('n/j H:i', jst_strtotime($end))   : '未定'; ?></span></div>
    </div>
    <div class="list-actions">
      <button class="btn-edit" onclick="editPost(<?php echo $p->ID; ?>,'<?php echo esc_js($series); ?>','<?php echo esc_js($store); ?>','<?php echo esc_js($pref); ?>','<?php echo $cat; ?>','<?php echo $sl_local; ?>','<?php echo $el_local; ?>','<?php echo esc_js($result); ?>','<?php echo esc_js($url); ?>','<?php echo esc_js($note); ?>',<?php echo $tid2?:0; ?>,'<?php echo esc_js($turl2); ?>')">✏️ 編集</button>
      <button class="btn-edit" style="color:#f5c518;border-left:1px solid var(--border);" onclick="genSingleText(<?php echo $p->ID; ?>)">📝 文章</button>
      <button class="btn-del" onclick="confirmDelete(<?php echo $p->ID; ?>,'<?php echo esc_js($series); ?>')">🗑 削除</button>
    </div>
  </div>
<?php endforeach; endif; ?>

<!-- 個別文章生成モーダル -->
<div id="single-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:300;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#111827;border:1px solid #1e2d45;border-radius:16px;padding:20px;width:100%;max-width:360px;">
    <div style="font-weight:900;color:#f5c518;margin-bottom:12px;">📝 投稿文生成</div>
    <textarea id="single-output" readonly style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:12px;border-radius:8px;font-size:13px;line-height:1.8;min-height:180px;resize:vertical;font-family:monospace;box-sizing:border-box;"></textarea>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button onclick="copySingleText()" style="flex:1;background:#f5c518;color:#000;border:none;padding:10px;border-radius:8px;font-weight:900;cursor:pointer;">📋 コピー</button>
      <button onclick="document.getElementById('single-modal').style.display='none'" style="flex:1;background:#1a2235;color:#6b7fa3;border:1px solid #1e2d45;padding:10px;border-radius:8px;cursor:pointer;">閉じる</button>
    </div>
  </div>
</div>
<form id="single-form" method="post" style="display:none;">
  <?php wp_nonce_field('lottery_admin_action'); ?>
  <input type="hidden" name="action_type" value="single_text">
  <input type="hidden" name="single_id" id="single-id-input" value="">
</form>
<script>
function genSingleText(pid) {
  document.getElementById('single-id-input').value = pid;
  document.getElementById('single-form').submit();
}
function copySingleText() {
  var ta = document.getElementById('single-output');
  navigator.clipboard.writeText(ta.value).then(function(){
    alert('コピーしました！');
  }).catch(function(){ ta.select(); document.execCommand('copy'); });
}
</script>
</div>

<!-- まとめ投稿 -->
<!-- 一括登録 -->
<div id="pane-bulk" style="display:none;" class="wrap">

  <!-- AI一括解析 -->
  <div class="card" style="border-color:#a78bfa;margin-bottom:12px;">
    <div style="font-size:14px;font-weight:900;color:#a78bfa;margin-bottom:8px;">🤖 AI一括解析</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px;">複数のXポスト本文を貼り付け→AI解析→フォームに自動入力します<br>各ポストを <strong style="color:#fff;">---</strong> （ハイフン3つ）で区切ってください</div>
    <textarea id="bulk-ai-text" class="inp" rows="8" placeholder="ポスト1の本文&#10;---&#10;ポスト2の本文&#10;---&#10;ポスト3の本文" style="resize:vertical;font-size:13px;"></textarea>
    <button type="button" id="bulk-ai-btn" style="width:100%;padding:12px;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#fff;font-weight:900;font-size:14px;border:none;border-radius:10px;cursor:pointer;margin-top:10px;">✨ AI一括解析してフォームに入力</button>
    <div id="bulk-ai-status" style="font-size:12px;color:var(--muted);margin-top:8px;text-align:center;display:none;"></div>
  </div>

<?php if (($message??'')=='bulk_saved' && !empty($_SESSION['bulk_text'])): ?>
<div class="card" style="background:#0f2a1a;border-color:#3fb950;margin-bottom:16px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <strong style="color:#3fb950;">✅ 一括登録完了！</strong>
    <button id="copy-bulk-btn" style="background:#3fb950;color:#000;border:none;padding:6px 14px;border-radius:6px;font-weight:900;cursor:pointer;font-size:12px;">📋 コピー</button>
  </div>
  <textarea id="bulk-out" readonly style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:12px;border-radius:8px;font-size:13px;line-height:1.8;min-height:200px;resize:vertical;font-family:monospace;box-sizing:border-box;"><?php echo esc_textarea($_SESSION['bulk_text']); ?></textarea>
  <?php unset($_SESSION['bulk_text']); ?>
</div>
<?php elseif(($message??'')=='bulk_error'): ?>
<div class="card" style="background:#2a1a0f;border-color:#f0883e;">
  <strong style="color:#f0883e;">⚠️ シリーズ名が入力されていません</strong>
</div>
<?php endif; ?>

<div class="card">
  <div style="font-size:16px;font-weight:900;margin-bottom:4px;">📦 一括登録</div>
  <div style="font-size:12px;color:var(--muted);margin-bottom:16px;">同じカテゴリの抽選を複数まとめて登録＋X投稿文を自動生成</div>

  <form method="post" action="<?php echo esc_url(get_permalink()); ?>" id="bulk-form">
    <?php wp_nonce_field('lottery_admin_action'); ?>
    <input type="hidden" name="action_type" value="bulk_save">

    <div class="form-row">
      <div class="form-label">カテゴリ（全件共通）</div>
      <select name="bulk_cat" class="sel">
        <option value="pokeca">🃏 ポケカ</option>
        <option value="sneaker">👟 スニーカー</option>
        <option value="other">🎮 その他</option>
      </select>
    </div>

    <div style="border-top:1px solid var(--border);margin:14px 0;"></div>
    <div style="font-size:13px;font-weight:900;margin-bottom:12px;">抽選リスト</div>

    <div id="bulk-items">
      <?php
      $PREF_OPT = '<option value="">都道府県</option><option value="全国">🗾 全国</option><option value="オンライン">🌐 オンライン</option>';
      $PREF_OPT .= '<option value="北海道">北海道</option><option value="青森県">青森県</option><option value="岩手県">岩手県</option><option value="宮城県">宮城県</option><option value="秋田県">秋田県</option><option value="山形県">山形県</option><option value="福島県">福島県</option><option value="茨城県">茨城県</option><option value="栃木県">栃木県</option><option value="群馬県">群馬県</option><option value="埼玉県">埼玉県</option><option value="千葉県">千葉県</option><option value="東京都">東京都</option><option value="神奈川県">神奈川県</option><option value="新潟県">新潟県</option><option value="富山県">富山県</option><option value="石川県">石川県</option><option value="福井県">福井県</option><option value="山梨県">山梨県</option><option value="長野県">長野県</option><option value="岐阜県">岐阜県</option><option value="静岡県">静岡県</option><option value="愛知県">愛知県</option><option value="三重県">三重県</option><option value="滋賀県">滋賀県</option><option value="京都府">京都府</option><option value="大阪府">大阪府</option><option value="兵庫県">兵庫県</option><option value="奈良県">奈良県</option><option value="和歌山県">和歌山県</option><option value="鳥取県">鳥取県</option><option value="島根県">島根県</option><option value="岡山県">岡山県</option><option value="広島県">広島県</option><option value="山口県">山口県</option><option value="徳島県">徳島県</option><option value="香川県">香川県</option><option value="愛媛県">愛媛県</option><option value="高知県">高知県</option><option value="福岡県">福岡県</option><option value="佐賀県">佐賀県</option><option value="長崎県">長崎県</option><option value="熊本県">熊本県</option><option value="大分県">大分県</option><option value="宮崎県">宮崎県</option><option value="鹿児島県">鹿児島県</option><option value="沖縄県">沖縄県</option>';
      for($bi=0;$bi<3;$bi++):
      ?>
      <div class="b-item" style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:900;color:var(--accent);margin-bottom:10px;" class="b-num">No.<?php echo $bi+1;?></div>
        <div class="form-row"><div class="form-label">シリーズ名</div>
        <input type="text" name="item_series[]" class="inp" placeholder="例：リザードンex SAR"></div>
        <div class="form-row"><div class="form-label">店舗名</div>
        <input type="text" name="item_store[]" class="inp" placeholder="例：ヨドバシカメラ"></div>
        <div class="form-row"><div class="form-label">都道府県</div>
        <select name="item_pref[]" class="sel"><?php echo $PREF_OPT;?></select></div>
        <div class="form-row"><div class="form-label">応募開始</div>
        <input type="datetime-local" name="item_start[]" class="inp" style="color-scheme:dark;"></div>
        <div class="form-row"><div class="form-label">締切</div>
        <input type="datetime-local" name="item_end[]" class="inp" style="color-scheme:dark;"></div>
        <div class="form-row"><div class="form-label">発表日（終日）</div>
        <input type="date" name="item_result[]" class="inp" style="color-scheme:dark;"></div>
        <div class="form-row"><div class="form-label">応募URL</div>
        <input type="text" name="item_url[]" class="inp" placeholder="https://..."></div>
        <div class="form-row" style="margin-bottom:0;"><div class="form-label">備考</div>
        <input type="text" name="item_note[]" class="inp" placeholder="例：フォロー&リポスト必須"></div>
        <div class="form-row" style="margin-bottom:0;margin-top:10px;">
          <div class="form-label">アイキャッチ画像</div>
          <input type="hidden" name="item_thumb[]" class="item-thumb-id" value="">
          <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
            <img class="item-thumb-img" src="" style="display:none;width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
            <button type="button" class="btn-media" style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);border-radius:8px;padding:8px 12px;cursor:pointer;font-size:12px;">📷 画像を選択</button>
            <button type="button" class="btn-media-del" style="display:none;background:none;border:none;color:#ff4444;cursor:pointer;font-size:12px;">✕ 削除</button>
          </div>
        </div>
      </div>
      <?php endfor; ?>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <button type="button" id="btn-add-item" style="flex:1;padding:10px;background:var(--surface2);border:1px dashed #555;color:#aaa;border-radius:8px;font-size:13px;cursor:pointer;">＋ 追加</button>
      <button type="button" id="btn-del-item" style="flex:1;padding:10px;background:var(--surface2);border:1px solid #555;color:#ff6b6b;border-radius:8px;font-size:13px;cursor:pointer;">－ 削除</button>
    </div>

    <button type="submit" class="btn-primary">📦 一括登録 ＆ 投稿文生成</button>
  </form>
</div>

<script>
var makeItem; // グローバル公開
var initMediaButtons; // グローバル公開
(function() {
  var PREF_OPT = '<option value="">都道府県</option><option value="全国">🗾 全国</option><option value="オンライン">🌐 オンライン</option>' + '<option value="北海道">北海道</option><option value="青森県">青森県</option><option value="岩手県">岩手県</option><option value="宮城県">宮城県</option><option value="秋田県">秋田県</option><option value="山形県">山形県</option><option value="福島県">福島県</option><option value="茨城県">茨城県</option><option value="栃木県">栃木県</option><option value="群馬県">群馬県</option><option value="埼玉県">埼玉県</option><option value="千葉県">千葉県</option><option value="東京都">東京都</option><option value="神奈川県">神奈川県</option><option value="新潟県">新潟県</option><option value="富山県">富山県</option><option value="石川県">石川県</option><option value="福井県">福井県</option><option value="山梨県">山梨県</option><option value="長野県">長野県</option><option value="岐阜県">岐阜県</option><option value="静岡県">静岡県</option><option value="愛知県">愛知県</option><option value="三重県">三重県</option><option value="滋賀県">滋賀県</option><option value="京都府">京都府</option><option value="大阪府">大阪府</option><option value="兵庫県">兵庫県</option><option value="奈良県">奈良県</option><option value="和歌山県">和歌山県</option><option value="鳥取県">鳥取県</option><option value="島根県">島根県</option><option value="岡山県">岡山県</option><option value="広島県">広島県</option><option value="山口県">山口県</option><option value="徳島県">徳島県</option><option value="香川県">香川県</option><option value="愛媛県">愛媛県</option><option value="高知県">高知県</option><option value="福岡県">福岡県</option><option value="佐賀県">佐賀県</option><option value="長崎県">長崎県</option><option value="熊本県">熊本県</option><option value="大分県">大分県</option><option value="宮崎県">宮崎県</option><option value="鹿児島県">鹿児島県</option><option value="沖縄県">沖縄県</option>';

  makeItem = function(n) {
    var d = document.createElement('div');
    d.className = 'b-item';
    d.style.cssText = 'background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;';
    d.innerHTML = '<div class="b-num" style="font-size:12px;font-weight:900;color:var(--accent);margin-bottom:10px;">No.'+n+'</div>'
      +'<div class="form-row"><div class="form-label">シリーズ名</div><input type="text" name="item_series[]" class="inp" placeholder="例：リザードンex SAR"></div>'
      +'<div class="form-row"><div class="form-label">店舗名</div><input type="text" name="item_store[]" class="inp" placeholder="例：ヨドバシカメラ"></div>'
      +'<div class="form-row"><div class="form-label">都道府県</div><select name="item_pref[]" class="sel">'+PREF_OPT+'</select></div>'
      +'<div class="form-row"><div class="form-label">応募開始</div><input type="datetime-local" name="item_start[]" class="inp" style="color-scheme:dark;"></div>'
      +'<div class="form-row"><div class="form-label">締切</div><input type="datetime-local" name="item_end[]" class="inp" style="color-scheme:dark;"></div>'
      +'<div class="form-row"><div class="form-label">発表日（終日）</div><input type="date" name="item_result[]" class="inp" style="color-scheme:dark;"></div>'
      +'<div class="form-row"><div class="form-label">応募URL</div><input type="text" name="item_url[]" class="inp" placeholder="https://..."></div>'
      +'<div class="form-row" style="margin-bottom:0;"><div class="form-label">備考</div><input type="text" name="item_note[]" class="inp" placeholder="例：フォロー&リポスト必須"></div>'
      +'<div class="form-row" style="margin-bottom:0;margin-top:10px;"><div class="form-label">アイキャッチ画像</div>'
      +'<input type="hidden" name="item_thumb[]" class="item-thumb-id" value="">'
      +'<div style="display:flex;align-items:center;gap:8px;margin-top:4px;">'
      +'<img class="item-thumb-img" src="" style="display:none;width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">'
      +'<button type="button" class="btn-media" style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);border-radius:8px;padding:8px 12px;cursor:pointer;font-size:12px;">📷 画像を選択</button>'
      +'<button type="button" class="btn-media-del" style="display:none;background:none;border:none;color:#ff4444;cursor:pointer;font-size:12px;">✕ 削除</button>'
      +'</div></div>';
    return d;
  }

  function renumber() {
    document.querySelectorAll('#bulk-items .b-num').forEach(function(el,i){ el.textContent='No.'+(i+1); });
  }

  initMediaButtons = function(itemEl) {
    var btn    = itemEl.querySelector('.btn-media');
    var delBtn = itemEl.querySelector('.btn-media-del');
    var imgEl  = itemEl.querySelector('.item-thumb-img');
    var tidEl  = itemEl.querySelector('.item-thumb-id');
    if (!btn) return;

    btn.addEventListener('click', function() {
      var uploader = wp.media({title:'画像を選択', button:{text:'使用する'}, multiple:false});
      uploader.on('select', function() {
        var att = uploader.state().get('selection').first().toJSON();
        if (tidEl) tidEl.value = att.id;
        if (imgEl) { imgEl.src = att.url; imgEl.style.display = 'block'; }
        if (delBtn) delBtn.style.display = 'inline';
      });
      uploader.open();
    });

    if (delBtn) {
      delBtn.addEventListener('click', function() {
        if (tidEl) tidEl.value = '';
        if (imgEl) { imgEl.src = ''; imgEl.style.display = 'none'; }
        delBtn.style.display = 'none';
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    // 初期枠のメディアボタン初期化（各枠ごとに）
    document.querySelectorAll('#bulk-items .b-item').forEach(function(item) {
      initMediaButtons(item);
    });

    var addBtn = document.getElementById('btn-add-item');
    var delBtn = document.getElementById('btn-del-item');
    var copyBtn = document.getElementById('copy-bulk-btn');

    if (addBtn) {
      addBtn.addEventListener('click', function() {
        var items = document.querySelectorAll('#bulk-items .b-item');
        var newItem = makeItem(items.length+1);
        document.getElementById('bulk-items').appendChild(newItem);
        initMediaButtons(newItem);
      });
    }
    if (delBtn) {
      delBtn.addEventListener('click', function() {
        var items = document.querySelectorAll('#bulk-items .b-item');
        if (items.length > 1) { items[items.length-1].remove(); renumber(); }
      });
    }
    if (copyBtn) {
      copyBtn.addEventListener('click', function() {
        var ta = document.getElementById('bulk-out');
        if (!ta) return;
        navigator.clipboard.writeText(ta.value).then(function(){
          copyBtn.textContent = '✅ コピーしました！';
          setTimeout(function(){ copyBtn.textContent = '📋 コピー'; }, 2000);
        }).catch(function(){ ta.select(); document.execCommand('copy'); alert('コピーしました！'); });
      });
    }

    // AI一括解析
    var bulkAiBtn = document.getElementById('bulk-ai-btn');
    if (bulkAiBtn) {
      bulkAiBtn.addEventListener('click', function() {
        var raw = (document.getElementById('bulk-ai-text').value || '').trim();
        if (!raw) { alert('ポストの本文を貼り付けてください'); return; }

        var texts = raw.split(/\n---\n|\n-{3,}\n/).map(function(t){ return t.trim(); }).filter(Boolean);
        if (!texts.length) { alert('テキストが見つかりません'); return; }

        var status = document.getElementById('bulk-ai-status');
        status.style.display = 'block';
        status.style.color = 'var(--muted)';
        status.textContent = '🤖 AI解析中... (' + texts.length + '件)';
        bulkAiBtn.disabled = true;

        var formData = new FormData();
        formData.append('action_type', 'ai_bulk_parse');
        texts.forEach(function(t) { formData.append('texts[]', t); });

        fetch('<?php echo esc_url(get_permalink()); ?>', { method:'POST', body:formData })
        .then(function(r) {
          return r.text().then(function(txt) {
            try { return JSON.parse(txt); }
            catch(e) { throw new Error('サーバー応答：' + txt.substring(0, 300)); }
          });
        })
        .then(function(res) {
          if (!res.success) throw new Error(res.error || '解析失敗');
          var results = res.results || [];
          if (!results.length) throw new Error('AIが0件返しました。テキストを確認してください');

          // 既存の枠より多い場合は枠を追加
          while (document.querySelectorAll('#bulk-items .b-item').length < results.length) {
            var cur = document.querySelectorAll('#bulk-items .b-item').length;
            document.getElementById('bulk-items').appendChild(makeItem(cur + 1));
          }

          // 各枠に値をセット
          results.forEach(function(d, i) {
            if (!d) return;
            var item = document.querySelectorAll('#bulk-items .b-item')[i];
            if (!item) return;
            var set = function(name, val) {
              var el = item.querySelector('[name="' + name + '[]"]');
              if (el && val) el.value = val;
            };
            set('item_series', d.series);
            set('item_store',  d.store);
            set('item_start',  d.start_date);
            set('item_end',    d.end_date);
            set('item_result', d.result_date);
            var itemUrl = d.lottery_url || '';
            if (itemUrl && !/^https?:\/\//i.test(itemUrl)) itemUrl = 'https://' + itemUrl;
            set('item_url', itemUrl);
            set('item_note',   d.note);
            // 都道府県
            if (d.prefecture) {
              var sel = item.querySelector('[name="item_pref[]"]');
              if (sel) { for(var j=0;j<sel.options.length;j++){ if(sel.options[j].value===d.prefecture){sel.selectedIndex=j;break;} } }
            }
          });

          renumber();
          status.style.color = '#3fb950';
          status.textContent = '✅ ' + results.filter(Boolean).length + '件入力しました！内容を確認して登録してください';
          bulkAiBtn.disabled = false;
        })
        .catch(function(e) {
          status.style.color = '#ff4444';
          status.textContent = '❌ ' + e.message;
          bulkAiBtn.disabled = false;
        });
      });
    }
  });
})();
</script>
</div>

<div id="pane-post"
 style="display:none;" class="wrap">
<?php if (($message??'')==='show_single' && !empty($_SESSION['single_text'])): ?>
<div class="card" style="background:#0f1f0a;border-color:#f5c518;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
    <strong style="color:#f5c518;">📝 個別投稿文</strong>
    <button onclick="(function(){var t=document.getElementById('single-out-ta');navigator.clipboard.writeText(t.value).then(function(){alert('コピーしました！');}).catch(function(){t.select();document.execCommand('copy');});})();" style="background:#f5c518;color:#000;border:none;padding:6px 14px;border-radius:6px;font-weight:900;cursor:pointer;font-size:12px;">📋 コピー</button>
  </div>
  <textarea id="single-out-ta" readonly style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:12px;border-radius:8px;font-size:13px;line-height:1.8;min-height:180px;resize:vertical;font-family:monospace;box-sizing:border-box;"><?php echo esc_textarea($_SESSION['single_text']); ?></textarea>
  <?php unset($_SESSION['single_text'],$_SESSION['single_id']); ?>
</div>
<?php endif; ?>
  <div class="card">
    <div style="font-size:16px;font-weight:900;margin-bottom:6px;">📋 まとめ文章を生成</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:16px;">ボタンを押すとX投稿用の文章が生成されます</div>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <div style="font-size:11px;color:var(--muted);margin-bottom:2px;">📂 カテゴリ別まとめ</div>
      <?php foreach (['pokeca'=>['🃏 ポケカ','#3b82f6'], 'sneaker'=>['👟 スニーカー','#10b981'], 'other'=>['🎮 その他','#8b5cf6']] as $cat_key=>$cat_info): ?>
      <form method="post">
        <?php wp_nonce_field('lottery_admin_action'); ?>
        <input type="hidden" name="action_type" value="manual_summary">
        <input type="hidden" name="manual_cat" value="<?php echo $cat_key; ?>">
        <button type="submit" class="btn-summary" style="background:<?php echo $cat_info[1]; ?>22;border:1px solid <?php echo $cat_info[1]; ?>44;color:<?php echo $cat_info[1]; ?>;">
          <?php echo $cat_info[0]; ?> 文章を生成
        </button>
      </form>
      <?php endforeach; ?>
      <div style="border-top:1px solid var(--border);margin:4px 0;"></div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:2px;">⚡ 特集まとめ</div>
      <form method="post">
        <?php wp_nonce_field('lottery_admin_action'); ?>
        <input type="hidden" name="action_type" value="urgent_summary">
        <button type="submit" class="btn-summary" style="background:#ff444422;border:1px solid #ff444444;color:#ff4444;">
          ⚡ 24時間以内締切の抽選まとめ
        </button>
      </form>
      <form method="post">
        <?php wp_nonce_field('lottery_admin_action'); ?>
        <input type="hidden" name="action_type" value="today_summary">
        <button type="submit" class="btn-summary" style="background:#f5c51822;border:1px solid #f5c51844;color:#f5c518;">
          🆕 本日追加した抽選まとめ
        </button>
      </form>
      <form method="post">
        <?php wp_nonce_field('lottery_admin_action'); ?>
        <input type="hidden" name="action_type" value="result_summary">
        <button type="submit" class="btn-summary" style="background:#a78bfa22;border:1px solid #a78bfa44;color:#a78bfa;">
          🏆 本日の抽選発表まとめ
        </button>
      </form>
    </div>
  </div>
  <?php if (isset($_SESSION['summary_text']) && ($message ?? '') === 'show_summary'): ?>
  <div class="card" style="background:#0f2a1a;border-color:#3fb950;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <strong style="color:#3fb950;">✅ 文章が生成されました</strong>
      <button onclick="copyText()" style="background:#3fb950;color:#000;border:none;padding:6px 14px;border-radius:6px;font-weight:900;cursor:pointer;font-size:12px;">📋 コピー</button>
    </div>
    <textarea id="summary-output" readonly style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:12px;border-radius:8px;font-size:13px;line-height:1.8;min-height:200px;resize:vertical;font-family:monospace;"><?php echo esc_textarea($_SESSION['summary_text']); ?></textarea>
    <div style="font-size:11px;color:var(--muted);margin-top:8px;">↑ この文章をXに貼り付けて投稿してください</div>
    <?php unset($_SESSION['summary_text']); ?>
  </div>
  <?php elseif (($message ?? '') === 'show_summary'): ?>
  <div class="card" style="background:#2a1a0f;border-color:#f0883e;">
    <strong style="color:#f0883e;">⚠️ 投稿できる抽選情報がありません</strong>
  </div>
  <?php endif; ?>

  <?php if (!session_id()) session_start(); ?>
  <?php if (($message ?? '') === 'show_new_post' && !empty($_SESSION['new_post_text'])): ?>
  <div class="card" style="background:#0f1f2a;border-color:#3b9eff;margin-top:12px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <strong style="color:#3b9eff;">✅ 登録完了！投稿文をコピーしてXに貼り付けてください</strong>
      <button onclick="copyNewPost()" id="copy-new-btn" style="background:#3b9eff;color:#000;border:none;padding:6px 14px;border-radius:6px;font-weight:900;cursor:pointer;font-size:12px;">📋 コピー</button>
    </div>
    <textarea id="new-post-output" readonly style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:12px;border-radius:8px;font-size:13px;line-height:1.8;min-height:160px;resize:vertical;font-family:monospace;box-sizing:border-box;"><?php echo esc_textarea($_SESSION['new_post_text']); ?></textarea>
    <div style="font-size:11px;color:var(--muted);margin-top:6px;">↑ コピーして <a href="https://x.com" target="_blank" style="color:#3b9eff;">X（Twitter）</a> に貼り付けてください</div>
    <?php unset($_SESSION['new_post_text']); ?>
  </div>
  <script>
  function copyNewPost() {
    var ta = document.getElementById('new-post-output');
    navigator.clipboard.writeText(ta.value).then(function() {
      var btn = document.getElementById('copy-new-btn');
      btn.textContent = '✅ コピーしました！';
      setTimeout(function(){ btn.textContent = '📋 コピー'; }, 2000);
    }).catch(function() {
      ta.select(); document.execCommand('copy'); alert('コピーしました！');
    });
  }
  </script>
  <?php endif; ?>

  <div class="card" style="font-size:12px;color:var(--muted);line-height:2;">
    <strong style="color:var(--text);display:block;margin-bottom:8px;">🤖 自動投稿スケジュール</strong>
    🌅 毎朝8時 → 今日締切の抽選まとめ（全カテゴリ1投稿）<br>
    🌆 毎夜20時 → 明日締切の抽選まとめ（全カテゴリ1投稿）<br>
    🌙 毎夜23時 → 今日追加した抽選まとめ（全カテゴリ1投稿）<br>
    🔁 3時間ごと → カテゴリ別ローテーション投稿<br>
    ⚡ 10分ごと → 締切24時間前アラート
    <div style="border-top:1px solid var(--border);margin:10px 0 8px;"></div>
    <strong style="color:var(--text);display:block;margin-bottom:6px;">🔓 二重実行ロック解除</strong>
    <div style="font-size:11px;margin-bottom:8px;">Cronを手動実行しても投稿されない場合はロックを解除してください</div>
    <?php
    $today_jst = date('Y-m-d', time()+9*3600);
    $locks = [
        'lottery_morning_lock_'.$today_jst => '朝まとめ',
        'lottery_evening_lock_'.$today_jst => '夜まとめ',
        'lottery_night_lock_'.$today_jst   => '夜23時まとめ',
    ];
    foreach ($locks as $key => $label):
        $locked = get_option($key);
    ?>
    <form method="post" style="display:inline;margin-right:6px;">
      <?php wp_nonce_field('lottery_admin_action'); ?>
      <input type="hidden" name="action_type" value="clear_lock">
      <input type="hidden" name="lock_key" value="<?php echo esc_attr($key); ?>">
      <button type="submit" style="background:<?php echo $locked ? 'rgba(255,68,68,.15)' : 'rgba(255,255,255,.05)'; ?>;border:1px solid <?php echo $locked ? '#ff4444' : 'var(--border)'; ?>;color:<?php echo $locked ? '#ff4444' : 'var(--muted)'; ?>;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:11px;">
        <?php echo $locked ? '🔒 '.$label.'ロック中 → 解除' : '✅ '.$label; ?>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- 削除確認モーダル -->
<div class="modal-bg" id="del-modal">
  <div class="modal-box">
    <div style="font-size:16px;font-weight:900;margin-bottom:8px;">🗑 削除確認</div>
    <div style="font-size:13px;color:var(--muted);line-height:1.6;" id="del-text"></div>
    <form method="post">
      <?php wp_nonce_field('lottery_admin_action'); ?>
      <input type="hidden" name="action_type" value="delete">
      <input type="hidden" name="post_id" id="del-id" value="">
      <div class="modal-btns">
        <button type="button" class="modal-cancel" onclick="closeModal()">キャンセル</button>
        <button type="submit" class="modal-confirm">削除する</button>
      </div>
    </form>
  </div>
</div>

<script>
function copyText() {
  var ta = document.getElementById('summary-output');
  ta.select();
  ta.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(ta.value).then(function() {
    var btn = event.target;
    btn.textContent = '✅ コピーしました！';
    setTimeout(function(){ btn.textContent = '📋 コピー'; }, 2000);
  }).catch(function() {
    document.execCommand('copy');
    alert('コピーしました！');
  });
}
</script>
<?php wp_footer(); ?>
<script>
(function() {
  function applyToForm(d, xurl) {
    if (d.series)      document.getElementById('f-series').value = d.series;
    if (d.store)       document.getElementById('f-store').value  = d.store;
    if (d.start_date)  document.getElementById('f-start').value  = d.start_date;
    if (d.end_date)    document.getElementById('f-end').value    = d.end_date;
    if (d.result_date) document.getElementById('f-result').value = d.result_date;
    if (d.note)        document.getElementById('f-note').value   = d.note;
    var appUrl = d.lottery_url || xurl || '';
    if (appUrl && !/^https?:\/\//i.test(appUrl)) appUrl = 'https://' + appUrl;
    if (appUrl) document.getElementById('f-url').value = appUrl;
    if (d.category) {
      var catSel = document.getElementById('f-cat');
      for (var i=0;i<catSel.options.length;i++) { if(catSel.options[i].value===d.category){catSel.selectedIndex=i;break;} }
    }
    if (d.prefecture) {
      var prefSel = document.getElementById('f-pref');
      for (var j=0;j<prefSel.options.length;j++) { if(prefSel.options[j].value===d.prefecture){prefSel.selectedIndex=j;break;} }
    }
  }

  function applyMultiToBulk(results) {
    showTab('bulk');
    while (document.querySelectorAll('#bulk-items .b-item').length < results.length) {
      var cur = document.querySelectorAll('#bulk-items .b-item').length;
      var newItem = makeItem(cur+1);
      document.getElementById('bulk-items').appendChild(newItem);
      initMediaButtons(newItem);
    }
    results.forEach(function(d,i) {
      if (!d) return;
      var item = document.querySelectorAll('#bulk-items .b-item')[i];
      if (!item) return;
      var set = function(name,val){ var el=item.querySelector('[name="'+name+'[]"]'); if(el&&val) el.value=val; };
      set('item_series',d.series); set('item_store',d.store);
      set('item_start',d.start_date); set('item_end',d.end_date);
      set('item_result',d.result_date); set('item_note',d.note);
      var u=d.lottery_url||''; if(u&&!/^https?:\/\//i.test(u)) u='https://'+u; set('item_url',u);
      if (d.prefecture) { var sel=item.querySelector('[name="item_pref[]"]'); if(sel){for(var j=0;j<sel.options.length;j++){if(sel.options[j].value===d.prefecture){sel.selectedIndex=j;break;}}} }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    var status = document.getElementById('ai-status');

    // 画像選択プレビュー
    var imgInput = document.getElementById('ai-img-input');
    var imgBtn   = document.getElementById('ai-img-btn');
    var imgThumb = document.getElementById('ai-img-thumb');
    var imgPrev  = document.getElementById('ai-img-preview');
    var imgLabel = document.getElementById('ai-img-label');
    var currentImageData = null;
    var currentImageMime = null;

    if (imgInput) {
      imgInput.addEventListener('change', function() {
        var file = imgInput.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
          currentImageData = e.target.result;
          currentImageMime = file.type;
          imgThumb.src = e.target.result;
          imgPrev.style.display = 'block';
          imgLabel.textContent = file.name;
          imgBtn.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }

    if (imgBtn) {
      imgBtn.addEventListener('click', function() {
        if (!currentImageData) { alert('画像を選択してください'); return; }
        status.style.display = 'block';
        status.style.color = 'var(--muted)';
        status.textContent = '🖼️ 画像を解析中...';
        imgBtn.disabled = true;

        var fd = new FormData();
        fd.append('action_type', 'ai_image_parse');
        fd.append('image_data', currentImageData);
        fd.append('image_mime', currentImageMime);

        fetch('<?php echo esc_url(get_permalink()); ?>', {method:'POST', body:fd})
        .then(function(r){ return r.text().then(function(t){ try{return JSON.parse(t);}catch(e){throw new Error('サーバー応答：'+t.substring(0,200));} }); })
        .then(function(res) {
          if (!res.success) throw new Error((res.error||'失敗')+(res.raw?'／'+res.raw:''));
          if (res.type==='multi' && res.results && res.results.length>0) {
            applyMultiToBulk(res.results);
            status.style.color='#f5c518';
            status.textContent='✅ '+res.results.length+'件検出！一括登録タブに移動しました';
          } else {
            applyToForm(res.data||{}, '');
            status.style.color='#3fb950';
            status.textContent='✅ 入力しました！内容を確認して登録してください';
          }
          imgBtn.disabled = false;
        })
        .catch(function(e){ status.style.color='#ff4444'; status.textContent='❌ '+e.message; imgBtn.disabled=false; });
      });
    }

    // URL取得ボタン
    var fetchBtn = document.getElementById('ai-fetch-btn');
    if (fetchBtn) {
      fetchBtn.addEventListener('click', function() {
        var url = (document.getElementById('ai-fetch-url').value || '').trim();
        if (!url) { alert('URLを入力してください'); return; }
        if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
        status.style.display = 'block';
        status.style.color = 'var(--muted)';
        status.textContent = '🔍 ページを取得中...';
        fetchBtn.disabled = true;
        var fd = new FormData();
        fd.append('action_type','ai_fetch_parse');
        fd.append('fetch_url', url);
        fetch('<?php echo esc_url(get_permalink()); ?>', {method:'POST',body:fd})
        .then(function(r){ return r.text().then(function(t){ try{return JSON.parse(t);}catch(e){throw new Error('サーバー応答：'+t.substring(0,200));} }); })
        .then(function(res) {
          if (!res.success) throw new Error((res.error||'失敗')+(res.raw?'／'+res.raw:''));
          if (res.type==='multi' && res.results && res.results.length>0) {
            applyMultiToBulk(res.results);
            status.style.color='#f5c518';
            status.textContent='✅ '+res.results.length+'件検出！一括登録タブに移動しました';
          } else {
            applyToForm(res.data||{}, url);
            status.style.color='#3fb950';
            status.textContent='✅ 入力しました！内容を確認して登録してください';
          }
          fetchBtn.disabled = false;
        })
        .catch(function(e){ status.style.color='#ff4444'; status.textContent='❌ '+e.message; fetchBtn.disabled=false; });
      });
    }

    var btn = document.getElementById('ai-parse-btn');
    if (!btn) return;
    btn.addEventListener('click', function() {
      var text     = (document.getElementById('ai-text').value || '').trim();
      var fetchUrl = (document.getElementById('ai-fetch-url').value || '').trim();
      if (fetchUrl && !/^https?:\/\//i.test(fetchUrl)) fetchUrl = 'https://' + fetchUrl;

      if (!text && !fetchUrl && !currentImageData) {
        alert('画像・URL・テキストのいずれかを入力してください'); return;
      }

      status.style.display = 'block';
      status.style.color = 'var(--muted)';
      var parts = [];
      if (currentImageData) parts.push('画像');
      if (fetchUrl) parts.push('URL');
      if (text) parts.push('テキスト');
      status.textContent = '🤖 ' + parts.join('＋') + 'を解析中...';
      btn.disabled = true;

      var fd = new FormData();
      fd.append('action_type', 'ai_combined_parse');
      if (text) fd.append('text', text);
      if (fetchUrl) fd.append('fetch_url', fetchUrl);
      if (currentImageData) { fd.append('image_data', currentImageData); fd.append('image_mime', currentImageMime||'image/jpeg'); }

      fetch('<?php echo esc_url(get_permalink()); ?>', {method:'POST', body:fd})
      .then(function(r){ return r.text().then(function(t){ try{return JSON.parse(t);}catch(e){throw new Error('サーバー応答：'+t.substring(0,200));} }); })
      .then(function(res) {
        if (!res.success) throw new Error((res.error||'解析失敗')+(res.raw?'／'+res.raw:''));
        if (res.type==='multi' && res.results && res.results.length>0) {
          applyMultiToBulk(res.results);
          status.style.color='#f5c518';
          status.textContent='✅ '+res.results.length+'件検出！一括登録タブに移動しました';
        } else {
          applyToForm(res.data||{}, fetchUrl);
          status.style.color='#3fb950';
          status.textContent='✅ 入力しました！内容を確認して登録してください';
        }
        btn.disabled = false;
      })
      .catch(function(e){ status.style.color='#ff4444'; status.textContent='❌ 解析に失敗しました：'+e.message; btn.disabled=false; });
    });
  });
})();
</script>
</body>
</html>
