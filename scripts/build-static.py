#!/usr/bin/env python3
"""
Build a static snapshot of the live WordPress site for GitHub Pages.

- Crawls every page from the running local site (http://localhost:8091)
- Downloads all referenced local assets (wp-content / wp-includes)
- Rewrites every localhost URL to the public Pages base
- Builds sermons-index.json and rewires the faceted search to run client-side
- Writes everything to ./docs (GitHub Pages source)

Run:  python3 scripts/build-static.py
"""
import json, os, re, sys, urllib.parse, urllib.request, shutil

LOCAL = "http://localhost:8091"
HOST  = "pastorjackwerth.org"
BASE  = "https://" + HOST
HERE  = os.path.dirname(os.path.abspath(__file__))
OUT   = os.path.join(HERE, "..", "docs")
UA    = {"User-Agent": "Mozilla/5.0 (static-export)"}

def get(url, binary=False):
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read() if binary else r.read().decode("utf-8", "ignore")

def save(path, data):
    full = os.path.join(OUT, path.lstrip("/"))
    os.makedirs(os.path.dirname(full), exist_ok=True)
    mode = "wb" if isinstance(data, (bytes, bytearray)) else "w"
    with open(full, mode, **({} if mode == "wb" else {"encoding": "utf-8"})) as f:
        f.write(data)

def rewrite(text):
    # Cover both plain and JSON-escaped-slash forms of the local URL.
    text = text.replace("http://localhost:8091", BASE)
    text = text.replace("http:\\/\\/localhost:8091", BASE.replace("/", "\\/"))
    text = text.replace("localhost:8091", HOST)
    return text

JW_RE = re.compile(r"var JW = \{.*?\};", re.S)
def process_html(html):
    html = rewrite(html)
    # Point the search at the static JSON index (app.js prefers JW.index over REST).
    html = JW_RE.sub('var JW = {"index":"%s/sermons-index.json","archive":"%s/sermons/"};' % (BASE, BASE), html)
    return html

# ---------------------------------------------------------------- URL list
def slugify(s):
    return re.sub(r"^-|-$", "", re.sub(r"[^a-z0-9]+", "-", (s or "").lower()))

print("Collecting URLs…")
# Home + the sermon archive + EVERY WP page (pulled from the page sitemap, so any page
# added later — like /library/ — is included automatically).
pages = ["/", "/sermons/"]
psm = get(f"{LOCAL}/wp-sitemap-posts-page-1.xml")
for u in re.findall(r"<loc>([^<]+)</loc>", psm):
    p = urllib.parse.urlparse(u).path
    if p not in pages:
        pages.append(p)

# sermon permalinks from the sitemap
sm = get(f"{LOCAL}/wp-sitemap-posts-sermon-1.xml")
sermon_urls = [urllib.parse.urlparse(u).path for u in re.findall(r"<loc>([^<]+)</loc>", sm)]

# book taxonomy pages from the REST index (collect unique book slugs below)
# sitemaps to copy verbatim (rewritten)
sitemaps = ["/wp-sitemap.xml"]
smi = get(f"{LOCAL}/wp-sitemap.xml")
sitemaps += [urllib.parse.urlparse(u).path for u in re.findall(r"<loc>([^<]+)</loc>", smi)]

# ---------------------------------------------------------------- search index
print("Building sermons-index.json from REST…")
index, page = [], 1
while True:
    data = json.loads(get(f"{LOCAL}/wp-json/jw/v1/sermons?page={page}"))
    for it in data["items"]:
        index.append({
            "id": it.get("archive_id", ""),
            "passage": it["passage"], "book": it["book"], "bookSlug": slugify(it["book"]),
            "year": int((it["date"] or "0")[:4] or 0), "date": it["date"],
            "service": it["service"], "serviceSlug": slugify(it["service"]),
            "permalink": rewrite(it["permalink"]), "audio": it["audio"], "excerpt": it["excerpt"],
        })
    if page >= data["pages"]:
        break
    page += 1
book_slugs = sorted({i["bookSlug"] for i in index})
book_urls = [f"/book/{s}/" for s in book_slugs]
save("/sermons-index.json", json.dumps(index, ensure_ascii=False))
print(f"  index: {len(index)} sermons, {len(book_slugs)} books")

# ---------------------------------------------------------------- crawl
all_urls = pages + sermon_urls + book_urls
print(f"Crawling {len(all_urls)} pages…")
assets = set()
# WordPress emits link/script tags with EITHER single or double quotes.
asset_re = re.compile(r'(?:href|src)=(["\'])(http://localhost:8091/(?:wp-content|wp-includes)/[^"\']+)\1')

for i, path in enumerate(all_urls, 1):
    html = get(LOCAL + path)
    for q, m in asset_re.findall(html):
        assets.add(m)
    save(path.rstrip("/") + "/index.html" if path != "/" else "/index.html", process_html(html))
    if i % 100 == 0:
        print(f"  …{i}/{len(all_urls)}")

# sitemaps (rewritten)
for s in sitemaps:
    try:
        save(s, rewrite(get(LOCAL + s)))
    except Exception as e:
        print("  sitemap skip", s, e)

# ---------------------------------------------------------------- assets
print(f"Downloading {len(assets)} assets…")
for a in assets:
    path = urllib.parse.urlparse(a).path  # strip query string
    try:
        save(path, get(a, binary=True))
    except Exception as e:
        print("  asset fail", a, e)

# Follow-along transcripts (word-timed JSON) — served for the highlighting feature.
tdir = os.path.join(HERE, "..", "data", "transcripts")
if os.path.isdir(tdir):
    tn = 0
    for jf in __import__("glob").glob(os.path.join(tdir, "*.json")):
        save("/wp-content/jw-data/transcripts/" + os.path.basename(jf), open(jf, encoding="utf-8").read())
        tn += 1
    print(f"Copied {tn} transcript(s) → docs/transcripts/")

# GitHub Pages: custom domain (must be re-emitted every build since docs/ is wiped) + no Jekyll
save("/CNAME", HOST + "\n")
save("/.nojekyll", "")
print("Done →", os.path.relpath(OUT))
