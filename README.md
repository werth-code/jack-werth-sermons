# Jack Werth — Sermon Library

A beautiful, SEO-ready WordPress site holding the complete expository preaching
ministry of **Pastor Jack Werth** — 641 verse-by-verse sermons captured from the
Liberty Baptist Church Reformed archive, searchable by date, book of the Bible,
and content, and built to serve pastors preparing to preach and believers going
deeper into Scripture.

**Live site:** https://werth-code.github.io/jack-werth-sermons/ (static snapshot on GitHub Pages; audio streams from archive.org)
Local dev URL: **http://localhost:8091** · Admin: **/wp-admin** — credentials in `CREDENTIALS.local.md` (gitignored, not published)

---

## What was built

| Requirement | How it's met |
|---|---|
| **1. Capture all data + audio (Jack Werth)** | `data/sermons.json` / `.csv` — 641 sermons (guest preachers excluded). Audio streams from archive.org; `scripts/download-audio.sh` makes a full local backup. Every MP3 filename contains "Jack Werth". |
| **2. Beautiful, branded WordPress site** | Custom **"Midnight Study"** theme (`theme/jackwerth`) — charcoal/ivory/brass/oxblood, Fraunces + Newsreader, lamplit reverent aesthetic, custom audio player. |
| **3. Searchable by date / book / content** | Faceted live search at `/sermons/` (keyword, book, year, service) + REST API. Content search lights up as transcripts are added. |
| **4. Good audio storage** | Primary: archive.org CDN (free, permanent, fast). Backup: `download-audio.sh` → local/external drive. Per-sermon override to serve locally. |
| **5. Store large amounts of sermon text** | Transcripts live in `post_content` (fully searchable). Bulk importer: `scripts/import-transcripts.php`. Scales to thousands. |
| **6. SEO-ready** | schema.org JSON-LD (AudioObject, BreadcrumbList, Person, WebSite), Open Graph + Twitter cards, meta descriptions, clean permalinks, XML sitemap (641 URLs), podcast RSS feed, semantic HTML. |
| **7. Sell outlines & books (future)** | Theme is WooCommerce-ready; sermons link to a product via `_jw_product_id` and show a "Get the Outline" CTA. See *Selling resources* below. |
| **8. For pastors & deeper learning** | Messaging, dedicated **For Pastors** page, book-centric IA (study a whole book in order), "go deeper" framing throughout. |

---

## Architecture

Data and presentation are deliberately separated so the catalog survives any redesign:

- **`plugin/jw-sermon-library/`** — the data engine. Registers the `sermon` post type;
  `bible_book` (with OT/NT parents), `sermon_series`, `sermon_speaker`, `service_type`
  taxonomies; all sermon meta; the faceted-search REST endpoint (`/wp-json/jw/v1/sermons`);
  schema.org + Open Graph output; and the podcast feed (`/feed/sermons`).
- **`theme/jackwerth/`** — the "Midnight Study" presentation layer (templates, CSS, JS).

```
jackwerth-dev/
├── docker-compose.yml         # isolated local WP (mariadb + wordpress + wpcli), port 8091
├── data/
│   ├── sermons.json / .csv    # the captured catalog (641 sermons)
│   └── transcripts/           # drop .txt/.md manuscripts here
├── scripts/
│   ├── build_catalog.py       # rebuild catalog from archive.org
│   ├── import-sermons.php      # catalog → WordPress (idempotent)
│   ├── import-transcripts.php  # transcripts → sermon bodies
│   └── download-audio.sh       # full local MP3 backup
├── plugin/jw-sermon-library/  # data engine (mounted into WP)
├── theme/jackwerth/           # Midnight Study theme (mounted into WP)
└── audio/                     # local MP3 backups (gitignore-worthy; large)
```

---

## Running it

```bash
cd ~/jackwerth-dev
docker compose up -d            # start (http://localhost:8091)
docker compose down             # stop
docker compose down -v          # stop + wipe the database (full reset)
```

WordPress core, theme, and plugin auto-mount. To rebuild from an empty DB, re-run the
install + import steps below.

### First-time install (already done once)
```bash
docker exec jw-wpcli wp core install --url=http://localhost:8091 \
  --title="Jack Werth — Expository Sermons" --admin_user=jack \
  --admin_password='<your-password>' --admin_email=matthewwerth@gmail.com --skip-email
docker exec jw-wpcli wp plugin activate jw-sermon-library
docker exec jw-wpcli wp theme activate jackwerth
docker exec jw-wpcli wp rewrite structure '/%postname%/' --hard
```

### Import / refresh the sermons
```bash
python3 scripts/build_catalog.py --refresh        # re-pull from archive.org (optional)
docker exec jw-wpcli wp eval-file wp-content/jw-scripts/import-sermons.php   # idempotent
```

### Add sermon transcripts (later)
1. Put `.txt`/`.md` files in `data/transcripts/`, named by sermon **date** (`2019-02-03.txt`,
   or `2019-02-03-E.txt`) or **archive id** (`2019.02.03.ETitus3.15JackWerth.txt`).
   For bulk, add a `manifest.csv` (`identifier,filename`).
2. `docker exec jw-wpcli wp eval-file wp-content/jw-scripts/import-transcripts.php`

The text becomes the sermon body and is immediately full-text searchable.

### Back up all audio (optional)
```bash
./scripts/download-audio.sh            # all 641 (~18 GB) → ./audio
./scripts/download-audio.sh 10         # just the first 10 (test)
DEST=/Volumes/Backup ./scripts/download-audio.sh   # to an external drive
```

---

## Publishing updates to the live site

The public site is a **static snapshot** on GitHub Pages (WordPress can't run there).
After you add transcripts or new sermons (and re-import), refresh the public site with:

```bash
./scripts/publish.sh        # re-snapshots docs/, commits, pushes; Pages rebuilds in ~1–2 min
```

`scripts/build-static.py` does the snapshot (crawls the local site, rewrites URLs to the
Pages domain, builds the client-side search index). The live faceted search runs entirely
in the browser from `sermons-index.json`; audio streams from archive.org.

> If a page looks stale after publishing, hard-refresh (Cmd+Shift+R) — GitHub Pages and the
> browser cache HTML for a few minutes.

## Selling resources (when ready)

The theme is already WooCommerce-aware. To enable a store:
```bash
docker exec jw-wpcli wp plugin install woocommerce --activate
```
Then create a downloadable product (the outline/book) and set the sermon's
`_jw_product_id` custom field to that product's ID — a styled **"Get the Outline"**
purchase CTA appears on the sermon automatically. The `/store/` page is the storefront.

---

## SEO checklist (implemented)

- ✅ Per-sermon `AudioObject` + `BreadcrumbList` JSON-LD; `Person` + `WebSite` on home
- ✅ Open Graph + Twitter cards + meta descriptions (no plugin required)
- ✅ Clean permalinks `/sermons/{date}-{passage}/`, `/book/{book}/`
- ✅ XML sitemap (`/wp-sitemap.xml`, 641 sermon URLs) + podcast feed (`/feed/sermons`)
- ✅ Fast, semantic, mobile-responsive, accessible (no-JS safe)
- ⬜ Recommended for production: install **Rank Math** or **Yoast** for editorial control,
  set a real domain, add Google Search Console, and a social share image (Customizer → Brand).

## Going to production

This is a localhost-only dev build. To launch: provision WordPress hosting, copy the
`plugin/` and `theme/` folders, import the catalog, point DNS, enable HTTPS, and
(optionally) keep streaming audio from archive.org so you never host 18 GB yourself.
