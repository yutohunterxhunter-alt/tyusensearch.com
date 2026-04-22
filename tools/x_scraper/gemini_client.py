"""
Gemini APIクライアント（自作）
ツイートテキストから抽選情報をJSON形式で抽出する。
"""
import json
import requests


_API_URL = (
    'https://generativelanguage.googleapis.com/v1beta/'
    'models/gemini-1.5-flash:generateContent'
)

_PROMPT_TEMPLATE = """
以下のツイートから抽選・くじ情報を抽出してください。
抽選・くじ・応募の情報が一切含まれていない場合は、文字列 null のみ返してください。

ツイート:
{text}

抽選情報がある場合は、以下のJSONのみを返してください（コードブロック・説明文は不要）:
{{
  "series":      "シリーズ名・商品名（例: 拡張パック 夜明けの殲撃）",
  "store":       "店舗名（例: ファミリーマート、不明なら空文字）",
  "category":    "{category}",
  "start_date":  "応募開始日 YYYY-MM-DD（不明はnull）",
  "end_date":    "応募締切日 YYYY-MM-DD（不明はnull）",
  "lottery_url": "応募URL（不明はnull）",
  "note":        "備考（当選発表日・注意事項など、なければ空文字）",
  "result_date": "当選発表日 YYYY-MM-DD（不明はnull）"
}}
"""


class GeminiClient:
    def __init__(self, api_key: str):
        self._api_key = api_key

    def extract_lottery(self, tweet_text: str, hint_category: str) -> dict | None:
        """
        ツイートテキストから抽選情報を抽出する。
        抽選情報がなければ None を返す。
        """
        prompt = _PROMPT_TEMPLATE.format(
            text=tweet_text,
            category=hint_category,
        )
        payload = {
            'contents': [{'parts': [{'text': prompt}]}],
            'generationConfig': {
                'temperature':     0.1,
                'maxOutputTokens': 512,
            },
        }
        try:
            resp = requests.post(
                _API_URL,
                params={'key': self._api_key},
                json=payload,
                timeout=20,
            )
            resp.raise_for_status()
            raw = resp.json()['candidates'][0]['content']['parts'][0]['text'].strip()

            if raw.lower() == 'null':
                return None

            # コードブロックが付いていたら除去
            raw = raw.strip('`').removeprefix('json').strip()
            return json.loads(raw)

        except (json.JSONDecodeError, KeyError):
            return None
        except Exception as e:
            print(f'[Gemini] エラー: {e}')
            return None
