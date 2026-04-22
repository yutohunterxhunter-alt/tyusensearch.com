"""
X（Twitter）内部APIクライアント
XのウェブアプリがブラウザでAI使っている内部エンドポイントを直接叩く自作実装。
公式SDKや外部サービスは一切不使用。
"""
import time
import requests

# XのWebアプリに埋め込まれている公開Bearerトークン
_BEARER = (
    'AAAAAAAAAAAAAAAAAAAAANRILgAAAAAAnNwIzUejRCOuH5E6I8xnZz4puTs'
    '%3D1Zv7ttfk8LF81IUq16cHjhLTvJu4FA33AGWWjCpTnA'
)

_SEARCH_URL     = 'https://twitter.com/i/api/2/search/adaptive.json'
_GUEST_TOKEN_URL = 'https://api.twitter.com/1.1/guest/activate.json'

_BASE_HEADERS = {
    'Authorization':  f'Bearer {_BEARER}',
    'User-Agent':     (
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        'AppleWebKit/537.36 (KHTML, like Gecko) '
        'Chrome/124.0.0.0 Safari/537.36'
    ),
    'Accept':          '*/*',
    'Accept-Language': 'ja,en-US;q=0.9,en;q=0.8',
    'Referer':         'https://twitter.com/search',
    'Origin':          'https://twitter.com',
    'x-twitter-client-language': 'ja',
    'x-twitter-active-user':     'yes',
}


class XClient:
    """
    Xの内部Webエンドポイントを使ったツイート検索クライアント。

    使い方:
        # ゲストトークン（非ログイン）モード
        client = XClient()

        # ログイン済みCookieモード（より安定・取得量多い）
        client = XClient(auth_token='xxx', ct0='yyy')

        tweets = client.search('ポケカ 抽選', count=20)
    """

    def __init__(self, auth_token: str = '', ct0: str = ''):
        self._session = requests.Session()
        self._session.headers.update(_BASE_HEADERS)
        self._auth_token = auth_token
        self._ct0 = ct0

        if auth_token and ct0:
            self._setup_cookie_auth()
        else:
            self._setup_guest_auth()

    # ----------------------------------------------------------
    # 認証セットアップ
    # ----------------------------------------------------------

    def _setup_cookie_auth(self):
        """ログイン済みCookieでの認証（推奨）"""
        self._session.headers['x-csrf-token'] = self._ct0
        self._session.headers['x-twitter-auth-type'] = 'OAuth2Session'
        self._session.cookies.set('auth_token', self._auth_token, domain='.twitter.com')
        self._session.cookies.set('ct0',        self._ct0,        domain='.twitter.com')

    def _setup_guest_auth(self):
        """ゲストトークンでの認証（非ログイン）"""
        try:
            resp = self._session.post(_GUEST_TOKEN_URL, timeout=10)
            resp.raise_for_status()
            token = resp.json().get('guest_token', '')
            if token:
                self._session.headers['x-guest-token'] = token
                self._session.cookies.set('gt', token, domain='.twitter.com')
        except Exception as e:
            print(f'[XClient] ゲストトークン取得失敗: {e}')

    # ----------------------------------------------------------
    # 検索
    # ----------------------------------------------------------

    def search(self, query: str, count: int = 20) -> list[dict]:
        """
        キーワードでツイートを検索して返す。

        Returns:
            list of {'id', 'text', 'url', 'created_at'}
        """
        params = {
            'q':                  f'{query} lang:ja',
            'count':              min(count, 100),
            'tweet_mode':         'extended',
            'result_type':        'recent',
            'include_entities':   'true',
            'include_user_entities': 'false',
        }

        for attempt in range(3):
            try:
                resp = self._session.get(_SEARCH_URL, params=params, timeout=20)

                if resp.status_code == 429:
                    wait = 60 * (attempt + 1)
                    print(f'[XClient] レート制限。{wait}秒待機...')
                    time.sleep(wait)
                    continue

                if resp.status_code == 401:
                    print('[XClient] 認証エラー。auth_token / ct0 を確認してください。')
                    return []

                resp.raise_for_status()
                return self._parse(resp.json())

            except requests.RequestException as e:
                print(f'[XClient] リクエスト失敗 (試行{attempt+1}/3): {e}')
                time.sleep(5)

        return []

    # ----------------------------------------------------------
    # パース
    # ----------------------------------------------------------

    def _parse(self, data: dict) -> list[dict]:
        """レスポンスJSONからツイートリストを取り出す"""
        results = []
        raw = data.get('globalObjects', {}).get('tweets', {})

        for tweet_id, tw in raw.items():
            text = tw.get('full_text') or tw.get('text', '')

            # リツイート除外
            if text.startswith('RT @'):
                continue
            # 広告除外
            if tw.get('possibly_sensitive') and not tw.get('lang') == 'ja':
                continue

            results.append({
                'id':         tweet_id,
                'text':       text,
                'url':        f'https://twitter.com/i/web/status/{tweet_id}',
                'created_at': tw.get('created_at', ''),
            })

        return results
