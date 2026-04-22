import os
import json
import time
import requests
from datetime import datetime, timedelta, timezone

# ============================================================
# 設定（.envファイルまたは環境変数から読み込む）
# ============================================================
RAPIDAPI_KEY      = os.environ.get('RAPIDAPI_KEY', '')
RAPIDAPI_HOST     = os.environ.get('RAPIDAPI_HOST', 'twitter154.p.rapidapi.com')
GEMINI_API_KEY    = os.environ.get('GEMINI_API_KEY', '')
WP_SITE_URL       = os.environ.get('WP_SITE_URL', 'https://tyusensearch.com')
WP_USERNAME       = os.environ.get('WP_USERNAME', '')
WP_APP_PASSWORD   = os.environ.get('WP_APP_PASSWORD', '')

JST = timezone(timedelta(hours=9))

# 検索キーワード（カテゴリーごと）
SEARCH_QUERIES = [
    {'query': 'ポケカ 抽選',           'category': 'pokeca'},
    {'query': 'ポケモンカード 抽選',   'category': 'pokeca'},
    {'query': 'ポケカ くじ',           'category': 'pokeca'},
    {'query': 'スニーカー 抽選',       'category': 'sneaker'},
    {'query': 'ナイキ 抽選',           'category': 'sneaker'},
    {'query': 'ジョーダン 抽選',       'category': 'sneaker'},
    {'query': 'アディダス 抽選',       'category': 'sneaker'},
    {'query': 'フィギュア 抽選',       'category': 'other'},
    {'query': 'アニメ くじ 抽選',      'category': 'other'},
    {'query': 'グッズ 抽選 応募',      'category': 'other'},
]


# ============================================================
# RapidAPI でツイートを検索
# ============================================================
def fetch_tweets(query: str, count: int = 20) -> list:
    url = f'https://{RAPIDAPI_HOST}/search/tweet'
    headers = {
        'x-rapidapi-key':  RAPIDAPI_KEY,
        'x-rapidapi-host': RAPIDAPI_HOST,
    }
    params = {
        'query':    query,
        'limit':    count,
        'language': 'ja',
        'section':  'latest',
    }
    try:
        resp = requests.get(url, headers=headers, params=params, timeout=15)
        resp.raise_for_status()
        data = resp.json()
        return data.get('results', [])
    except Exception as e:
        print(f'[RapidAPI ERROR] {query}: {e}')
        return []


# ============================================================
# Gemini でツイートから抽選情報を抽出
# ============================================================
def extract_lottery_info(tweet_text: str, hint_category: str) -> dict | None:
    url = (
        'https://generativelanguage.googleapis.com/v1beta/'
        f'models/gemini-1.5-flash:generateContent?key={GEMINI_API_KEY}'
    )
    prompt = f"""
以下のツイートから抽選・くじ情報を抽出してください。
抽選・くじの情報が含まれていない場合は null のみ返してください。

ツイート:
{tweet_text}

抽出できた場合は以下のJSONのみ返してください（コードブロック不要）:
{{
  "series":      "シリーズ名・商品名（例: 拡張パック 夜明けの殲撃）",
  "store":       "店舗名（例: ファミリーマート, 不明の場合は空文字）",
  "category":    "{hint_category}",
  "start_date":  "YYYY-MM-DD（不明は null）",
  "end_date":    "YYYY-MM-DD（不明は null）",
  "lottery_url": "応募URL（不明は null）",
  "note":        "備考（当選発表日・注意事項など）",
  "result_date": "YYYY-MM-DD（不明は null）"
}}
"""
    payload = {'contents': [{'parts': [{'text': prompt}]}]}
    try:
        resp = requests.post(url, json=payload, timeout=20)
        resp.raise_for_status()
        text = resp.json()['candidates'][0]['content']['parts'][0]['text'].strip()
        if text.lower() == 'null':
            return None
        text = text.strip('`').removeprefix('json').strip()
        return json.loads(text)
    except Exception as e:
        print(f'[Gemini ERROR] {e}')
        return None


# ============================================================
# WordPress で重複チェック
# ============================================================
def is_duplicate(series: str, store: str) -> bool:
    if not series:
        return True
    url = f'{WP_SITE_URL}/wp-json/wp/v2/lottery'
    try:
        resp = requests.get(url, params={'search': series, 'per_page': 20}, timeout=10)
        for post in resp.json():
            acf = post.get('acf', {})
            if acf.get('series', '').strip() == series.strip():
                if not store or acf.get('store', '').strip() == store.strip():
                    return True
    except Exception as e:
        print(f'[WordPress ERROR] duplicate check: {e}')
    return False


# ============================================================
# WordPress に抽選情報を投稿
# ============================================================
def create_wp_post(info: dict, tweet_url: str) -> bool:
    url = f'{WP_SITE_URL}/wp-json/wp/v2/lottery'
    auth = (WP_USERNAME, WP_APP_PASSWORD)
    title = f"{info.get('series', '')} - {info.get('store', '')}".strip(' -')
    payload = {
        'title':  title,
        'status': 'publish',
        'acf': {
            'series':       info.get('series', ''),
            'store':        info.get('store', ''),
            'category':     info.get('category', 'other'),
            'start_date':   info.get('start_date') or '',
            'end_date':     info.get('end_date') or '',
            'lottery_url':  info.get('lottery_url') or tweet_url,
            'note':         info.get('note', ''),
            'result_date':  info.get('result_date') or '',
        },
    }
    try:
        resp = requests.post(url, json=payload, auth=auth, timeout=15)
        return resp.status_code == 201
    except Exception as e:
        print(f'[WordPress ERROR] create post: {e}')
        return False


# ============================================================
# メイン処理
# ============================================================
def main():
    now = datetime.now(JST).strftime('%Y-%m-%d %H:%M')
    print(f'[{now}] 抽選情報収集 開始')

    added = 0
    skipped = 0

    for item in SEARCH_QUERIES:
        query    = item['query']
        category = item['category']
        print(f'  検索: {query}')

        tweets = fetch_tweets(query)
        for tweet in tweets:
            text      = tweet.get('text', '')
            tweet_url = tweet.get('url', '') or tweet.get('tweet_url', '')

            info = extract_lottery_info(text, category)
            if not info:
                continue

            series = info.get('series', '').strip()
            store  = info.get('store', '').strip()

            if is_duplicate(series, store):
                skipped += 1
                print(f'    スキップ（重複）: {series}')
                continue

            if create_wp_post(info, tweet_url):
                added += 1
                print(f'    追加: {series} / {store}')
            else:
                print(f'    投稿失敗: {series}')

            time.sleep(1)

        time.sleep(2)

    print(f'[完了] 追加: {added}件 / スキップ: {skipped}件')


if __name__ == '__main__':
    main()
