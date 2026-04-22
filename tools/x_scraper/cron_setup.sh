#!/bin/bash
# Xserverのcronに登録するコマンド
# Xserverパネル → cron設定 → 以下を追加してください

# 実行パスを環境に合わせて変更してください
SCRIPT_DIR="/home/アカウント名/tyusensearch.com/tools/x_scraper"

# .envを読み込んでスクリプトを実行
set -a
source "$SCRIPT_DIR/.env"
set +a
python3 "$SCRIPT_DIR/scraper.py" >> "$SCRIPT_DIR/scraper.log" 2>&1

# ============================================================
# Xserverのcron設定（パネルから登録）
# 時刻: 7時, 12時, 15時, 18時, 21時
#
# 0 7  * * * bash /home/アカウント名/tyusensearch.com/tools/x_scraper/cron_setup.sh
# 0 12 * * * bash /home/アカウント名/tyusensearch.com/tools/x_scraper/cron_setup.sh
# 0 15 * * * bash /home/アカウント名/tyusensearch.com/tools/x_scraper/cron_setup.sh
# 0 18 * * * bash /home/アカウント名/tyusensearch.com/tools/x_scraper/cron_setup.sh
# 0 21 * * * bash /home/アカウント名/tyusensearch.com/tools/x_scraper/cron_setup.sh
# ============================================================
