#!/usr/bin/env bash
# Re-snapshot the live local WordPress site and publish it to GitHub Pages.
# Run this after you add transcripts or new sermons (and re-import), to refresh
# the public site at https://werth-code.github.io/jack-werth-sermons/
#
# Usage:  ./scripts/publish.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "1/3  Snapshotting site → docs/ …"
docker cp scripts/export-mu-plugin.php jw-wordpress:/var/www/html/wp-content/mu-plugins/jw-export.php
rm -rf docs
python3 scripts/build-static.py
docker exec jw-wordpress rm -f /var/www/html/wp-content/mu-plugins/jw-export.php

echo "2/3  Committing …"
git add -A
if git diff --cached --quiet; then echo "     No changes to publish."; exit 0; fi
git commit -q -m "Update static snapshot ($(date +%Y-%m-%d))"

echo "3/3  Pushing …"
git push -q origin main
echo "Published → https://werth-code.github.io/jack-werth-sermons/  (Pages rebuilds in ~1–2 min)"
echo "Tip: hard-refresh (Cmd+Shift+R) to bypass the browser cache."
