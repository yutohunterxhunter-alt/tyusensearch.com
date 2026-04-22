"""
WordPress REST APIクライアント（自作）
重複チェック・抽選情報の投稿を行う。
"""
import requests


class WPClient:
    def __init__(self, site_url: str, username: str, app_password: str):
        self._base   = site_url.rstrip('/')
        self._auth   = (username, app_password)
        self._session = requests.Session()

    # ----------------------------------------------------------
    # 重複チェック
    # ----------------------------------------------------------

    def is_duplicate(self, series: str, store: str) -> bool:
        """同じシリーズ＋店舗の投稿が既にあれば True"""
        if not series:
            return True
        try:
            resp = self._session.get(
                f'{self._base}/wp-json/wp/v2/lottery',
                params={'search': series, 'per_page': 20},
                timeout=10,
            )
            resp.raise_for_status()
            for post in resp.json():
                acf = post.get('acf', {})
                same_series = acf.get('series', '').strip() == series.strip()
                same_store  = (
                    not store or
                    acf.get('store', '').strip() == store.strip()
                )
                if same_series and same_store:
                    return True
        except Exception as e:
            print(f'[WP] 重複チェックエラー: {e}')
        return False

    # ----------------------------------------------------------
    # 投稿作成
    # ----------------------------------------------------------

    def create_post(self, info: dict, fallback_url: str = '') -> bool:
        """抽選情報をWordPressに投稿する。成功すれば True"""
        series = info.get('series', '').strip()
        store  = info.get('store', '').strip()
        title  = f'{series} - {store}' if store else series

        payload = {
            'title':  title,
            'status': 'publish',
            'acf': {
                'series':      series,
                'store':       store,
                'category':    info.get('category', 'other'),
                'start_date':  info.get('start_date')  or '',
                'end_date':    info.get('end_date')    or '',
                'lottery_url': info.get('lottery_url') or fallback_url,
                'note':        info.get('note', ''),
                'result_date': info.get('result_date') or '',
            },
        }
        try:
            resp = self._session.post(
                f'{self._base}/wp-json/wp/v2/lottery',
                json=payload,
                auth=self._auth,
                timeout=15,
            )
            return resp.status_code == 201
        except Exception as e:
            print(f'[WP] 投稿エラー: {e}')
            return False
