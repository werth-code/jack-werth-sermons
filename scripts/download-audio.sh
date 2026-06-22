#!/usr/bin/env bash
#
# Download a complete local backup of every Jack Werth sermon MP3 from archive.org.
# The website streams audio directly from archive.org's CDN (free, permanent), so this
# is purely for a redundant offline archive / cold backup. Each file keeps "Jack Werth"
# in its filename, per the capture requirement.
#
# Usage:
#   ./download-audio.sh            # download ALL 641 (~18 GB) into ./audio
#   ./download-audio.sh 10         # download just the first 10 (handy for a test run)
#   DEST=/Volumes/Backup ./download-audio.sh   # download to an external drive
#   SLEEP=5 RATE=3M ./download-audio.sh         # extra-gentle (avoid archive.org rate limits)
#
# Safe to re-run: completed files are skipped, partial files resume (curl -C -).
#
# archive.org rate-limits IPs that pull many files quickly. To stay friendly (and so the
# live site keeps streaming smoothly while this runs), we pause SLEEP seconds between files
# and optionally cap per-file bandwidth with RATE. Tune via env vars.
set -euo pipefail
SLEEP="${SLEEP:-4}"     # seconds to wait between downloads
RATE="${RATE:-0}"       # per-file rate cap for curl (0 = unlimited), e.g. 3M

HERE="$(cd "$(dirname "$0")" && pwd)"
CATALOG="$HERE/../data/sermons.json"
DEST="${DEST:-$HERE/../audio}"
LIMIT="${1:-0}"

[ -f "$CATALOG" ] || { echo "Catalog not found: $CATALOG (run build_catalog.py first)"; exit 1; }
mkdir -p "$DEST"

# Emit "url<TAB>filename" rows from the catalog (filename = original title + .mp3, contains 'Jack Werth').
rows="$(python3 - "$CATALOG" "$LIMIT" <<'PY'
import json, sys
cat = json.load(open(sys.argv[1]))
limit = int(sys.argv[2])
if limit > 0: cat = cat[:limit]
for c in cat:
    print(f"{c['audio_mp3']}\t{c['title']}.mp3")
PY
)"

total="$(printf '%s\n' "$rows" | grep -c . || true)"
echo "Backing up $total sermon MP3s to: $DEST"
echo "(streaming site is unaffected — this is an offline archive)"
echo

i=0; done=0; skipped=0
while IFS=$'\t' read -r url name; do
  [ -z "${url:-}" ] && continue
  i=$((i+1))
  out="$DEST/$name"
  # Skip anything already downloaded (complete sermons are 20-30 MB; failed/partial are absent or tiny).
  if [ -f "$out" ] && [ "$(wc -c < "$out" | tr -d ' ')" -gt 1000000 ]; then
    skipped=$((skipped+1)); printf "[%d/%d] ✓ have   %s\n" "$i" "$total" "$name"; continue
  fi
  printf "[%d/%d] ↓ fetch  %s\n" "$i" "$total" "$name"
  curl -fL -C - --retry 3 --retry-delay 2 --limit-rate "$RATE" -o "$out" "$url" && done=$((done+1)) || echo "   ! failed: $name"
  sleep "$SLEEP"   # be polite to archive.org so it doesn't throttle us (and the live site)
done <<< "$rows"

echo
echo "Done. Downloaded $done, already-present $skipped, of $total."
echo "Total stored: $(du -sh "$DEST" 2>/dev/null | cut -f1)"
echo
echo "To serve a sermon from this local copy instead of archive.org, set its"
echo "_jw_audio_local meta to the filename and place the file in wp uploads/sermons/."
