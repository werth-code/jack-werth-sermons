#!/usr/bin/env python3
"""
Repair sermon audio URLs that 404.

We originally built each MP3 URL as `title + ".mp3"`, which is right for ~90% of
items but wrong where the uploaded filename differs from the metadata title. This
looks up the ACTUAL file name from archive.org's metadata API for any URL that
404s, and rewrites data/sermons.json. Transient (429/503/timeout) failures are
left alone — their URLs are fine, archive.org was just rate-limiting.

Usage:  python3 fix-audio-urls.py [path-to-backup-log]   # log narrows the candidate set
        python3 fix-audio-urls.py                          # check ALL 641
"""
import json, os, re, sys, time, urllib.request, urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
CAT  = os.path.join(HERE, "..", "data", "sermons.json")

def head(url):
    try:
        req = urllib.request.Request(url, method="HEAD", headers={"User-Agent": "Mozilla/5.0"})
        return urllib.request.urlopen(req, timeout=30).status
    except urllib.error.HTTPError as e:
        return e.code
    except Exception:
        return 0  # timeout / connection — treat as transient

def metadata(ident):
    req = urllib.request.Request(f"https://archive.org/metadata/{ident}", headers={"User-Agent": "Mozilla/5.0"})
    return json.load(urllib.request.urlopen(req, timeout=30))

cat = json.load(open(CAT))
candidates = cat
if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
    log = open(sys.argv[1], encoding="utf-8", errors="ignore").read()
    failed = set(re.findall(r"! failed: (.+)\.mp3", log))
    candidates = [c for c in cat if c["title"] in failed]

print(f"Checking {len(candidates)} URL(s)…")
fixed = ok = throttled = 0
unresolved = []
for c in candidates:
    st = head(c["audio_mp3"])
    if st == 200:
        ok += 1; continue
    if st in (429, 503, 0):
        throttled += 1; continue              # URL is fine — just rate-limited
    if st == 404:
        try:
            m = metadata(c["identifier"])
        except Exception:
            unresolved.append((c["title"], "metadata fetch failed")); continue
        files = m.get("files", [])
        mp3 = next((f["name"] for f in files if f.get("name", "").lower().endswith(".mp3")), None)
        ogg = next((f["name"] for f in files if f.get("name", "").lower().endswith(".ogg")), None)
        if mp3:
            c["audio_mp3"] = f"https://archive.org/download/{c['identifier']}/{urllib.parse.quote(mp3)}"
            if ogg:
                c["audio_ogg"] = f"https://archive.org/download/{c['identifier']}/{urllib.parse.quote(ogg)}"
            fixed += 1
        else:
            unresolved.append((c["title"], "no mp3 in metadata"))
    time.sleep(0.25)

json.dump(cat, open(CAT, "w"), indent=2, ensure_ascii=False)
print(f"\nfixed={fixed}  already-ok={ok}  throttled(retry later)={throttled}  unresolved={len(unresolved)}")
for t, why in unresolved[:15]:
    print("  ?", why, "—", t)
