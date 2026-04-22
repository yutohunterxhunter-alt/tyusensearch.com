"""
抽選サーチ 自動収集スクリプト
Xの内部APIを直接叩いてツイートを取得し、
GeminiでAI抽出してWordPressに自動投稿する。

実行方法:
    python3 scraper.py

cron例（7/12/15/18/21時）:
    0 7,12,15,18,21 * * * cd /path/to/x_scraper && python3 scraper.py >> scraper.log 2>&1
"""
import os
import time
from datetime import datetime, timezone, timedelta
from x_client    import XClient
from gemini_client import GeminiClient
from wp_client   import WPClient

# ============================================================
# 設定（.envファイルを読み込む）
# ============================================================
def _load_env():
    env_path = os.path.join(os.path.dirname(__file__), '.env')
    if not os.path.exists(env_path):
        return
    with open(env_path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            os.environ.setdefault(k.strip(), v.strip())

_load_env()

X_AUTH_TOKEN   = os.environ.get('X_AUTH_TOKEN', '')
X_CT0          = os.environ.get('X_CT0', '')
GEMINI_API_KEY = os.environ.get('GEMINI_API_KEY', '')
WP_SITE_URL    = os.environ.get('WP_SITE_URL', 'https://tyusensearch.com')
WP_USERNAME    = os.environ.get('WP_USERNAME', '')
WP_APP_PASSWORD = os.environ.get('WP_APP_PASSWORD', '')

# 検索キーワード
SEARCH_QUERIES = [
    {'query': 'ポケカ 抽選',          'category': 'pokeca'},
    {'query': 'ポケモンカード 抽選',  'category': 'pokeca'},
    {'query': 'ポケカ くじ 応募',     'category': 'pokeca'},
    {'query': 'スニーカー 抽選',      'category': 'sneaker'},
    {'query': 'ナイキ 抽選',          'category': 'sneaker'},
    {'query': 'ジョーダン 抽選',      'category': 'sneaker'},
    {'query': 'アディダス 抽選',      'category': 'sneaker'},
    {'query': 'フィギュア 抽選 応募', 'category': 'other'},
    {'query': 'アニメ くじ 抽選',     'category': 'other'},
    {'query': 'グッズ 抽選 応募',     'category': 'other'},
]

JST = timezone(timedelta(hours=9))


# ============================================================
# メイン処理
# ============================================================
def main():
    now = datetime.now(JST).strftime('%Y-%m-%d %H:%M')
    print(f'[{now}] ===== 抽選情報収集 開始 =====')

    x  = XClient(auth_token=X_AUTH_TOKEN, ct0=X_CT0)
    ai = GeminiClient(api_key=GEMINI_API_KEY)
    wp = WPClient(
        site_url=WP_SITE_URL,
        username=WP_USERNAME,
        app_password=WP_APP_PASSWORD,
    )

    added   = 0
    skipped = 0
    errors  = 0

    for item in SEARCH_QUERIES:
        query    = item['query']
        category = item['category']
        print(f'\n  検索: 「{query}」')

        tweets = x.search(query, count=20)
        print(f'  取得: {len(tweets)}件')

        for tweet in tweets:
            text      = tweet['text']
            tweet_url = tweet['url']

            info = ai.extract_lottery(text, category)
            if info is None:
                continue

            series = info.get('series', '').strip()
            store  = info.get('store', '').strip()

            if not series:
                continue

            if wp.is_duplicate(series, store):
                skipped += 1
                print(f'  スキップ（重複）: {series}')
                continue

            if wp.create_post(info, fallback_url=tweet_url):
                added += 1
                print(f'  ✓ 追加: {series} / {store or "店舗不明"}')
            else:
                errors += 1
                print(f'  ✗ 投稿失敗: {series}')

            time.sleep(1)

        time.sleep(3)

    now_end = datetime.now(JST).strftime('%Y-%m-%d %H:%M')
    print(f'\n[{now_end}] ===== 完了 =====')
    print(f'  追加: {added}件 / スキップ: {skipped}件 / エラー: {errors}件')


if __name__ == '__main__':
    main()
