#!/usr/bin/env bash
# Import any transcribed sermons into WordPress (searchable post body) and publish
# the site so the follow-along transcripts go live. Safe to run repeatedly while the
# batch transcription accumulates — or once at the end.
#
# Usage:  ./scripts/publish-transcripts.sh
set -euo pipefail
cd "$(dirname "$0")/.."

n=$(ls data/transcripts/*.json 2>/dev/null | wc -l | tr -d ' ')
echo "Transcripts ready: ${n} / 641"
[ "$n" -gt 0 ] || { echo "Nothing to publish yet."; exit 0; }

echo "Importing transcript text into WordPress…"
docker exec jw-wpcli wp eval-file wp-content/jw-scripts/import-transcript-json.php
echo "Publishing…"
./scripts/publish.sh
echo "Done — ${n} sermons now have follow-along transcripts live."
