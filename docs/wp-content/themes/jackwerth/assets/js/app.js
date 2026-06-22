/* Jack Werth — UI: header, menu, reveals, and live faceted sermon search. */
(function () {
  'use strict';

  // ---- sticky header shadow ----------------------------------------------
  var head = document.querySelector('.site-head');
  if (head) {
    var onScroll = function () { head.classList.toggle('scrolled', window.scrollY > 10); };
    onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  }

  // ---- mobile menu --------------------------------------------------------
  var toggle = document.querySelector('[data-menu]');
  var nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) { if (e.target.tagName === 'A') nav.classList.remove('open'); });
  }

  // ---- reveal on scroll (scroll-based + guaranteed catch-all) -------------
  // Plain scroll math is more robust than IntersectionObserver here, and a
  // hard timeout guarantees nothing is ever left invisible.
  var revealEls = [].slice.call(document.querySelectorAll('.reveal:not(.in)'));
  function show(n) { n.classList.add('in'); }
  function checkReveal() {
    var h = window.innerHeight || document.documentElement.clientHeight;
    for (var i = revealEls.length - 1; i >= 0; i--) {
      if (revealEls[i].getBoundingClientRect().top < h * 0.92) {
        show(revealEls[i]); revealEls.splice(i, 1);
      }
    }
  }
  checkReveal();
  window.addEventListener('scroll', checkReveal, { passive: true });
  window.addEventListener('resize', checkReveal);
  window.addEventListener('load', function () { setTimeout(checkReveal, 50); });
  setTimeout(function () { revealEls.forEach(show); revealEls.length = 0; }, 2500); // ultimate safety net

  // ---- "Play All": queue every sermon card on the page, in display order ----
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-playall]');
    if (!b) return;
    var items = [].map.call(document.querySelectorAll('.card-grid .scard .play[data-audio]'), function (p) {
      return { audio: p.getAttribute('data-audio'), title: p.getAttribute('data-title'), sub: p.getAttribute('data-sub') };
    });
    if (items.length && window.jwQueue) window.jwQueue(items, 0);
  });

  // ---- contact: reveal success after FormSubmit redirects back with ?sent=1 ----
  var sent = document.querySelector('[data-sent]');
  if (sent && /[?&]sent=1/.test(location.search)) {
    sent.hidden = false;
    var cf = document.querySelector('[data-contact]'); if (cf) cf.style.display = 'none';
    sent.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // ---- live faceted search ------------------------------------------------
  var form = document.querySelector('[data-filter]');
  if (!form || typeof JW === 'undefined') return;

  var results = document.querySelector('[data-results]');
  var countEl = document.querySelector('[data-count]');
  var pager   = document.querySelector('[data-pagination]');
  var fields  = form.querySelectorAll('[data-f]');
  var page = 1, pages = 1, loading = false, pending = false, lastQS = '';

  function filters() {
    var f = {};
    fields.forEach(function (el) { if (el.value) f[el.name] = el.value; });
    return f;
  }
  function qs(obj) {
    return Object.keys(obj).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
  }
  function svgPlay() {
    return '<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>' +
           '<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function card(it) {
    var bookTag = it.book
      ? '<a class="tag" href="' + JW.archive + '?book=' + encodeURIComponent(it.book.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')) + '">' + esc(it.book) + '</a>'
      : '<span class="tag">Sermon</span>';
    var d = it.date ? new Date(it.date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
    var playBtn = it.audio
      ? '<button class="play" aria-label="Play" data-audio="' + esc(it.audio) + '" data-title="' + esc(it.passage) + '" data-sub="' + esc((it.service || '') + ' · ' + d) + '">' + svgPlay() + '</button>'
      : '';
    return '<article class="scard reveal in">' +
        '<div class="top">' + bookTag + '<span class="date">' + esc(d) + '</span></div>' +
        '<h3 class="passage"><a href="' + esc(it.permalink) + '">' + esc(it.passage || it.title) + '</a></h3>' +
        (it.excerpt ? '<p class="excerpt">' + esc(it.excerpt) + '</p>' : '') +
        '<div class="foot"><span class="svc">' + esc(it.service || '') + '</span>' + playBtn + '</div>' +
      '</article>';
  }

  // Data source: live REST (dynamic WP) OR a prebuilt JSON index (static export).
  var INDEX = null, PER = 12;
  function slugify(s) { return (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function withIndex(cb) {
    if (INDEX) return cb(INDEX);
    fetch(JW.index).then(function (r) { return r.json(); })
      .then(function (d) { INDEX = d; cb(d); }).catch(function () { INDEX = []; cb(INDEX); });
  }
  function filterIndex(items, f) {
    var q = (f.q || '').toLowerCase();
    return items.filter(function (it) {
      if (f.book && (it.bookSlug || slugify(it.book)) !== f.book) return false;
      if (f.service && (it.serviceSlug || slugify(it.service)) !== f.service) return false;
      if (f.year && String(it.year || (it.date || '').slice(0, 4)) !== String(f.year)) return false;
      if (q) { var hay = (it.passage + ' ' + it.book + ' ' + (it.excerpt || '') + ' ' + (it.date || '')).toLowerCase(); if (hay.indexOf(q) === -1) return false; }
      return true;
    });
  }

  function finishPage() {
    loading = false; results.classList.remove('is-loading');
    if (pending) { pending = false; page = 1; lastQS = qs(filters()); fetchPage(true); }
  }

  function fetchPage(reset) {
    if (loading) { pending = true; return; }   // a newer filter arrived mid-flight — re-run after this one
    loading = true;
    results.classList.add('is-loading');

    if (JW.index) {                                  // ---- static / client-side ----
      withIndex(function (all) {
        var matched = filterIndex(all, filters());
        pages = Math.max(1, Math.ceil(matched.length / PER));
        var slice = matched.slice((page - 1) * PER, page * PER);
        if (reset) results.innerHTML = '';
        if (reset && !matched.length) {
          results.innerHTML = '<p class="lede">No sermons match those filters. <a href="' + JW.archive + '">Clear search →</a></p>';
        } else {
          results.insertAdjacentHTML('beforeend', slice.map(card).join(''));
        }
        if (countEl) countEl.innerHTML = '<b>' + matched.length.toLocaleString() + '</b> sermon' + (matched.length === 1 ? '' : 's') + ' found';
        renderPager(); finishPage();
      });
      return;
    }

    var f = filters(); f.page = page;                // ---- live WordPress REST ----
    fetch(JW.rest + '?' + qs(f))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        pages = data.pages || 1;
        if (reset) results.innerHTML = '';
        if (reset && !data.items.length) {
          results.innerHTML = '<p class="lede">No sermons match those filters. <a href="' + JW.archive + '">Clear search →</a></p>';
        } else {
          results.insertAdjacentHTML('beforeend', data.items.map(card).join(''));
        }
        if (countEl) countEl.innerHTML = '<b>' + Number(data.total).toLocaleString() + '</b> sermon' + (data.total === 1 ? '' : 's') + ' found';
        renderPager();
      })
      .catch(function () { /* keep server-rendered results on error */ })
      .finally(finishPage);
  }

  function renderPager() {
    if (!pager) return;
    pager.innerHTML = '';
    if (page < pages) {
      var b = document.createElement('button');
      b.className = 'btn btn--ghost'; b.textContent = 'Load more sermons';
      b.addEventListener('click', function () { page++; fetchPage(false); });
      pager.appendChild(b);
    }
  }

  function syncURL() {
    var s = qs(filters());
    history.replaceState(null, '', s ? (location.pathname + '?' + s) : location.pathname);
  }

  function run() {
    var s = qs(filters());
    if (s === lastQS) return;
    lastQS = s; page = 1;
    syncURL();
    fetchPage(true);
  }

  var t;
  form.addEventListener('input', function (e) {
    if (e.target.type === 'search') { clearTimeout(t); t = setTimeout(run, 300); }
    else { run(); }
  });
  form.addEventListener('change', run);
  form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
  var reset = form.querySelector('[data-reset]');
  if (reset) reset.addEventListener('click', function () { fields.forEach(function (el) { el.value = ''; }); run(); });

  // "Play All" on the search archive — queue ALL current matches (not just the loaded page).
  var playAll = document.querySelector('[data-playall-index]');
  if (playAll) playAll.addEventListener('click', function () {
    withIndex(function (all) {
      var items = filterIndex(all, filters()).map(function (it) {
        return { audio: it.audio, title: it.passage, sub: (it.service || '') + ' · ' + (it.date || '') };
      });
      if (items.length && window.jwQueue) window.jwQueue(items, 0);
    });
  });

  // Static export: render from the JSON index on load, honoring any ?book=/?year= in the URL,
  // and replace the server-rendered cards + (now-dead) pagination links.
  if (JW.index) {
    var params = new URLSearchParams(location.search);
    fields.forEach(function (el) { if (params.get(el.name)) el.value = params.get(el.name); });
    page = 1; lastQS = qs(filters()); fetchPage(true);
  }
})();
