#!/bin/bash
# Hourly job: if new sermon transcripts have finished, import + publish them so the
# live site updates as transcription progresses. No-ops when nothing new. Self-contained
# (explicit PATH) so it works under launchd's minimal environment.
export PATH="/opt/homebrew/bin:/usr/local/bin:/Users/matthewwerth/miniforge3/bin:/usr/bin:/bin:/usr/sbin:/sbin"

PROJ="/Users/matthewwerth/jackwerth-dev"
LOG="$PROJ/auto-publish.log"
LOCK="$PROJ/.auto-publish.lock"
STATE="$PROJ/.transcripts-published"
cd "$PROJ" || exit 1

log() { echo "$(date '+%Y-%m-%d %H:%M:%S')  $*" >> "$LOG"; }

# one run at a time (clear a stale lock older than 3h)
if [ -e "$LOCK" ]; then
  if [ -n "$(find "$LOCK" -mmin +180 2>/dev/null)" ]; then rm -f "$LOCK"; else log "another run in progress — skip"; exit 0; fi
fi
touch "$LOCK"; trap 'rm -f "$LOCK"' EXIT

# the publish crawls the local WP site + imports via wp-cli, so the containers must be up
if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -q '^jw-wordpress$'; then
  log "WordPress container not running — skip"; exit 0
fi

count=$(ls "$PROJ"/data/transcripts/*.json 2>/dev/null | wc -l | tr -d ' ')
last=$(cat "$STATE" 2>/dev/null || echo 0)
if [ "${count:-0}" -le "${last:-0}" ]; then
  log "no new transcripts (${count} total) — skip"; exit 0
fi

log "new transcripts: ${last} -> ${count}; importing + publishing…"
docker exec jw-wpcli wp eval-file wp-content/jw-scripts/import-transcript-json.php >> "$LOG" 2>&1
if ./scripts/publish.sh >> "$LOG" 2>&1; then
  ls "$PROJ"/data/transcripts/*.json 2>/dev/null | wc -l | tr -d ' ' > "$STATE"   # recount (catch any finished mid-run)
  log "published — $(cat "$STATE") sermons now have transcripts live"
else
  log "publish FAILED — will retry next hour"
fi
