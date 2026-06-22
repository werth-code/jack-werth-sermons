#!/usr/bin/env python3
"""Build the Jack Werth sermon catalog from the archive.org uploader account.

Source of truth: everything uploaded by webmaster@lbref.org to archive.org.
We keep ONLY sermons where Jack Werth is the speaker (per spec), parse the
date / service / book / passage out of the title, and emit a clean JSON + CSV
that drives the WordPress import. Audio streams from archive.org's CDN.

Usage:  python3 build_catalog.py            # uses cached /tmp/ia_all.json if present
        python3 build_catalog.py --refresh  # re-query archive.org
"""
import json, re, sys, urllib.parse, urllib.request, os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT  = os.path.join(HERE, "..", "data")
CACHE = "/tmp/ia_all.json"
SEARCH = ("https://archive.org/advancedsearch.php?q=uploader%3A%28webmaster%40lbref.org%29"
          "&fl%5B%5D=identifier&fl%5B%5D=title&fl%5B%5D=date&fl%5B%5D=item_size"
          "&rows=2000&page=1&output=json")

SERVICE = {'M': 'Morning Service', 'E': 'Evening Service',
           'X': 'Bible Study',     'A': 'Afternoon Service'}

def load_docs(refresh=False):
    if refresh or not os.path.exists(CACHE):
        req = urllib.request.Request(SEARCH, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req) as r:
            open(CACHE, 'wb').write(r.read())
    return json.load(open(CACHE))['response']['docs']

def split_passage(p):
    """'Colossians 4.17-18' -> ('Colossians','4:17-18','4','17-18'); handles '2 Samuel','Song of Solomon'."""
    toks = p.split()
    for i, t in enumerate(toks):
        if re.match(r'^\d+[.:]', t):
            book = ' '.join(toks[:i]).strip()
            ref  = ' '.join(toks[i:]).strip()
            cv   = re.match(r'^(\d+)[.:](.+)$', ref)
            return book, ref.replace('.', ':'), (cv.group(1) if cv else ''), (cv.group(2) if cv else '')
    return p, '', '', ''

def parse_title(t):
    """Return (date, service_code, passage, speaker) or None. Robust to two title formats."""
    m = re.match(r'^(\d{4})\.(\d{2})\.(\d{2})\.([MEXA])\b[\s\-]*(.+)$', t)
    if not m:
        return None
    yr, mo, da, sv, rest = m.groups()
    rest = re.sub(r'\s+-\s+', ' - ', rest.strip())
    if ' - ' in rest:                       # "Colossians 4.17-18 - Jack Werth"
        passage, speaker = rest.rsplit(' - ', 1)
    else:                                    # "Luke 1.26-33 Jack Werth" (speaker = last 2 words)
        parts = rest.rsplit(' ', 2)
        passage, speaker = (parts[0], ' '.join(parts[1:])) if len(parts) == 3 else (rest, '')
    return f"{yr}-{mo}-{da}", sv, passage.strip(), speaker.strip()

def main():
    docs = load_docs('--refresh' in sys.argv)
    cat, skipped = [], []
    for d in docs:
        t = d.get('title', '')
        parsed = parse_title(t)
        if not parsed:
            skipped.append(t); continue
        date, sv, passage, speaker = parsed
        if 'werth' not in speaker.lower():
            continue
        book, ref, ch, vs = split_passage(passage)
        ident = d['identifier']
        enc   = urllib.parse.quote(t)
        cat.append({
            "identifier":  ident,
            "title":       t,
            "date":        date,
            "year":        int(date[:4]),
            "service_code": sv,
            "service":     SERVICE.get(sv, sv),
            "speaker":     speaker or "Jack Werth",
            "book":        book,
            "passage":     f"{book} {ref}".strip(),
            "chapter":     ch,
            "verses":      vs,
            "audio_mp3":   f"https://archive.org/download/{ident}/{enc}.mp3",
            "audio_ogg":   f"https://archive.org/download/{ident}/{enc}.ogg",
            "details_url": f"https://archive.org/details/{ident}",
            "size_bytes":  d.get('item_size', 0),
        })

    cat.sort(key=lambda x: (x['date'], x['service_code']))
    os.makedirs(OUT, exist_ok=True)
    json.dump(cat, open(os.path.join(OUT, "sermons.json"), 'w'), indent=2, ensure_ascii=False)

    import csv
    with open(os.path.join(OUT, "sermons.csv"), 'w', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(cat[0].keys())); w.writeheader(); w.writerows(cat)

    from collections import Counter
    books = Counter(c['book'] for c in cat)
    print(f"Jack Werth sermons captured: {len(cat)}")
    print(f"Skipped (non-Werth or unparseable): {len(skipped)}")
    for s in skipped[:5]:
        print("   skip:", s)
    print(f"Date range: {cat[0]['date']} -> {cat[-1]['date']}")
    print(f"Distinct books: {len(books)}")
    print("Books:", dict(books.most_common()))

if __name__ == "__main__":
    main()
