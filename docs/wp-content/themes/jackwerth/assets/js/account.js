/* Jack Werth library — accounts: login, heart/save sermons, "My Favorites".
   Talks to Supabase (auth + Postgres with row-level security). Playlists and
   cross-device resume build on this in later passes. */
(function () {
  'use strict';
  if (!window.JW_SB || !window.supabase) { console.warn('[JW] Supabase not loaded'); return; }

  var sb = window.supabase.createClient(JW_SB.url, JW_SB.key);
  var user = null;
  var favs = new Set();          // sermon archive-ids the user has hearted
  window.jwSB = sb;              // exposed for later passes / debugging

  // ------------------------------------------------------------------ utils
  function h(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstElementChild; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  var toastEl;
  function toast(msg, ok) {
    if (!toastEl) { toastEl = h('<div class="jw-toast"></div>'); document.body.appendChild(toastEl); }
    toastEl.textContent = msg; toastEl.className = 'jw-toast show' + (ok === false ? ' err' : '');
    clearTimeout(toast._t); toast._t = setTimeout(function () { toastEl.className = 'jw-toast'; }, 3200);
  }

  // ------------------------------------------------------------------ auth UI
  var modal;
  function buildModal() {
    modal = h(
      '<div class="jw-modal" data-modal hidden>' +
        '<div class="jw-modal-card">' +
          '<button class="jw-modal-x" data-x aria-label="Close">×</button>' +
          '<div class="kicker">The Library</div>' +
          '<h3 data-mtitle>Sign in</h3>' +
          '<p class="jw-modal-sub" data-msub>Save sermons, build playlists, and pick up where you left off — on any device.</p>' +
          '<form data-authform>' +
            '<label data-namewrap hidden>Name<input type="text" name="display_name" autocomplete="name"></label>' +
            '<label>Email<input type="email" name="email" required autocomplete="email"></label>' +
            '<label>Password<input type="password" name="password" required minlength="6" autocomplete="current-password"></label>' +
            '<p class="jw-modal-err" data-err hidden></p>' +
            '<button class="btn" type="submit" data-submit>Sign in</button>' +
          '</form>' +
          '<p class="jw-modal-toggle">' +
            '<span data-toggletext>New here?</span> ' +
            '<button type="button" data-toggle>Create an account</button>' +
          '</p>' +
        '</div>' +
      '</div>');
    document.body.appendChild(modal);
    var mode = 'signin';
    function setMode(m) {
      mode = m;
      modal.querySelector('[data-mtitle]').textContent = m === 'signin' ? 'Sign in' : 'Create an account';
      modal.querySelector('[data-submit]').textContent = m === 'signin' ? 'Sign in' : 'Create account';
      modal.querySelector('[data-namewrap]').hidden = m === 'signin';
      modal.querySelector('[data-toggletext]').textContent = m === 'signin' ? 'New here?' : 'Already have an account?';
      modal.querySelector('[data-toggle]').textContent = m === 'signin' ? 'Create an account' : 'Sign in';
      modal.querySelector('[name=password]').autocomplete = m === 'signin' ? 'current-password' : 'new-password';
      err('');
    }
    function err(msg) { var e = modal.querySelector('[data-err]'); e.textContent = msg || ''; e.hidden = !msg; }
    modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-x]')) closeAuth(); });
    modal.querySelector('[data-toggle]').addEventListener('click', function () { setMode(mode === 'signin' ? 'signup' : 'signin'); });
    modal.querySelector('[data-authform]').addEventListener('submit', async function (e) {
      e.preventDefault();
      var f = e.target, btn = f.querySelector('[data-submit]');
      var email = f.email.value.trim(), pass = f.password.value, name = f.display_name ? f.display_name.value.trim() : '';
      btn.disabled = true; var label = btn.textContent; btn.textContent = '…';
      try {
        if (mode === 'signup') {
          var r = await sb.auth.signUp({ email: email, password: pass, options: { data: { display_name: name } } });
          if (r.error) throw r.error;
          if (!r.data.session) { err(''); toast('Check your email to confirm your account.'); closeAuth(); }
          else { toast('Welcome — your account is ready.'); closeAuth(); }
        } else {
          var s = await sb.auth.signInWithPassword({ email: email, password: pass });
          if (s.error) throw s.error;
          toast('Signed in.'); closeAuth();
        }
      } catch (ex) { err(ex.message || 'Something went wrong.'); }
      finally { btn.disabled = false; btn.textContent = label; }
    });
    modal._setMode = setMode;
  }
  function openAuth(mode) { if (!modal) buildModal(); modal._setMode(mode || 'signin'); modal.hidden = false; document.body.style.overflow = 'hidden'; setTimeout(function () { modal.querySelector('[name=email]').focus(); }, 50); }
  function closeAuth() { if (modal) modal.hidden = true; document.body.style.overflow = ''; }

  // ------------------------------------------------------------------ header account control
  function renderAccount() {
    var c = document.querySelector('[data-account]');
    if (!c) return;
    if (user) {
      var name = (user.user_metadata && user.user_metadata.display_name) || (user.email || '?').split('@')[0];
      c.innerHTML =
        '<div class="acct" data-acct>' +
          '<button class="acct-btn" data-acctbtn aria-haspopup="true">' + esc(name[0].toUpperCase()) + '</button>' +
          '<div class="acct-menu" hidden>' +
            '<div class="acct-name">' + esc(name) + '</div>' +
            '<a href="' + (window.JW_HOME || '/') + 'library/">My Library</a>' +
            '<button type="button" data-signout>Sign out</button>' +
          '</div>' +
        '</div>';
      var menu = c.querySelector('.acct-menu');
      c.querySelector('[data-acctbtn]').addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; });
      c.querySelector('[data-signout]').addEventListener('click', async function () { await sb.auth.signOut(); toast('Signed out.'); });
      document.addEventListener('click', function () { if (menu) menu.hidden = true; });
    } else {
      c.innerHTML = '<button class="acct-signin" data-openauth>Sign in</button>';
    }
  }
  // any "Sign in" trigger on the page opens the auth modal
  document.addEventListener('click', function (e) { if (e.target.closest('[data-openauth]')) { e.preventDefault(); openAuth('signin'); } });

  // ------------------------------------------------------------------ favorites (hearts)
  async function loadFavorites() {
    if (!user) { favs.clear(); reflectHearts(); return; }
    var r = await sb.from('favorites').select('sermon_id');
    favs = new Set((r.data || []).map(function (x) { return x.sermon_id; }));
    reflectHearts();
  }
  function reflectHearts() {
    document.querySelectorAll('[data-heart]').forEach(function (b) {
      var on = favs.has(b.getAttribute('data-sermon'));
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.title = on ? 'Remove from favorites' : 'Save to favorites';
    });
  }
  async function toggleHeart(id, passage, btn) {
    if (!user) { openAuth('signin'); toast('Sign in to save sermons.'); return; }
    var on = favs.has(id);
    btn && btn.classList.toggle('on', !on);                       // optimistic
    try {
      if (on) { var d = await sb.from('favorites').delete().eq('sermon_id', id); if (d.error) throw d.error; favs.delete(id); }
      else { var i = await sb.from('favorites').insert({ user_id: user.id, sermon_id: id, passage: passage }); if (i.error) throw i.error; favs.add(id); toast('Saved to favorites.'); }
    } catch (ex) { btn && btn.classList.toggle('on', on); toast(ex.message || 'Could not update favorite.', false); }
    reflectHearts();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-heart]');
    if (b) { e.preventDefault(); toggleHeart(b.getAttribute('data-sermon'), b.getAttribute('data-passage'), b); }
  });

  // ------------------------------------------------------------------ My Library page (favorites section)
  var indexCache = null;
  function loadIndex() {
    if (indexCache) return Promise.resolve(indexCache);
    var base = (window.JW_HOME || '/');
    return fetch(base + 'sermons-index.json').then(function (r) { return r.json(); })
      .then(function (d) { indexCache = d; return d; }).catch(function () { return []; });
  }
  function cardHTML(it) {
    var d = it.date ? new Date(it.date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
    return '<article class="scard">' +
      '<div class="top"><span class="tag">' + esc(it.book || 'Sermon') + '</span><span class="date">' + esc(d) + '</span></div>' +
      '<h3 class="passage"><a href="' + esc(it.permalink) + '">' + esc(it.passage) + '</a></h3>' +
      '<div class="foot"><span class="svc">' + esc(it.service || '') + '</span>' +
        '<div class="card-actions">' +
          '<button class="heart on" data-heart data-sermon="' + esc(it.id) + '" data-passage="' + esc(it.passage) + '" aria-label="Favorite">' +
            '<svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
          '</button>' +
          (it.audio ? '<button class="play" data-audio="' + esc(it.audio) + '" data-title="' + esc(it.passage) + '" data-sub="' + esc((it.service || '') + ' · ' + d) + '"><svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg><svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg></button>' : '') +
        '</div>' +
      '</div>' +
    '</article>';
  }
  async function renderLibrary() {
    var root = document.querySelector('[data-library]');
    if (!root) return;
    var gate = root.querySelector('[data-loggedout]'), main = root.querySelector('[data-loggedin]');
    if (!user) { if (gate) gate.hidden = false; if (main) main.hidden = true; return; }
    if (gate) gate.hidden = true; if (main) main.hidden = false;
    var favGrid = root.querySelector('[data-favgrid]'), favCount = root.querySelector('[data-favcount]');
    var idx = await loadIndex();
    var byId = {}; idx.forEach(function (it) { byId[it.id] = it; });
    var r = await sb.from('favorites').select('sermon_id, passage, created_at').order('created_at', { ascending: false });
    var rows = r.data || [];
    if (favCount) favCount.textContent = rows.length;
    if (favGrid) {
      if (!rows.length) favGrid.innerHTML = '<p class="lede">No saved sermons yet. Tap the ♥ on any sermon to save it here.</p>';
      else favGrid.innerHTML = rows.map(function (row) { return byId[row.sermon_id] ? cardHTML(byId[row.sermon_id]) : ''; }).join('');
    }
    favs = new Set(rows.map(function (x) { return x.sermon_id; }));
    renderPlaylists(byId);
  }

  // ------------------------------------------------------------------ playlists
  var plTarget = null, plModal = null;

  async function listPlaylists() {
    var r = await sb.from('playlists').select('id, name, created_at').order('created_at', { ascending: true });
    return r.data || [];
  }
  async function createPlaylist(name) {
    var r = await sb.from('playlists').insert({ user_id: user.id, name: name }).select().single();
    if (r.error) { toast(r.error.message, false); return null; }
    return r.data;
  }
  async function addToPlaylist(plId, sermonId, passage) {
    var c = await sb.from('playlist_items').select('position').eq('playlist_id', plId).order('position', { ascending: false }).limit(1);
    var pos = ((c.data && c.data[0]) ? c.data[0].position : 0) + 1;
    var r = await sb.from('playlist_items').insert({ playlist_id: plId, sermon_id: sermonId, passage: passage, position: pos });
    if (r.error) { toast(r.error.message, false); return false; }
    return true;
  }

  function buildPlModal() {
    plModal = h(
      '<div class="jw-modal" data-plmodal hidden>' +
        '<div class="jw-modal-card">' +
          '<button class="jw-modal-x" data-x aria-label="Close">×</button>' +
          '<div class="kicker">Add to Playlist</div>' +
          '<h3 data-plpassage></h3>' +
          '<div class="pl-list" data-pllist></div>' +
          '<form data-plnew><label>New playlist<input name="plname" placeholder="e.g. Sunday Mornings" maxlength="80" autocomplete="off"></label><button class="btn" type="submit">Create &amp; add</button></form>' +
        '</div>' +
      '</div>');
    document.body.appendChild(plModal);
    plModal.addEventListener('click', function (e) { if (e.target === plModal || e.target.closest('[data-x]')) closePl(); });
    plModal.querySelector('[data-plnew]').addEventListener('submit', async function (e) {
      e.preventDefault(); var name = e.target.plname.value.trim(); if (!name) return;
      var pl = await createPlaylist(name);
      if (pl && await addToPlaylist(pl.id, plTarget.id, plTarget.passage)) { toast('Added to "' + name + '".'); closePl(); renderLibrary(); }
    });
  }
  async function openPl(sermonId, passage) {
    if (!user) { openAuth('signin'); toast('Sign in to build playlists.'); return; }
    if (!plModal) buildPlModal();
    plTarget = { id: sermonId, passage: passage };
    plModal.querySelector('[data-plpassage]').textContent = passage;
    plModal.querySelector('[data-plnew]').reset();
    var list = plModal.querySelector('[data-pllist]');
    list.innerHTML = '<p class="jw-muted">Loading…</p>';
    plModal.hidden = false; document.body.style.overflow = 'hidden';
    var pls = await listPlaylists();
    list.innerHTML = pls.length ? '' : '<p class="jw-muted">No playlists yet — name one below.</p>';
    pls.forEach(function (p) {
      var b = h('<button class="pl-pick" type="button">' + esc(p.name) + '</button>');
      b.addEventListener('click', async function () { if (await addToPlaylist(p.id, plTarget.id, plTarget.passage)) { toast('Added to "' + p.name + '".'); closePl(); renderLibrary(); } });
      list.appendChild(b);
    });
  }
  function closePl() { if (plModal) plModal.hidden = true; document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-addplaylist]');
    if (b) { e.preventDefault(); openPl(b.getAttribute('data-sermon'), b.getAttribute('data-passage')); }
  });

  async function renderPlaylists(byId) {
    var wrap = document.querySelector('[data-playlists]');
    if (!wrap) return;
    var pls = await listPlaylists();
    if (!pls.length) { wrap.innerHTML = '<p class="lede">No playlists yet. Tap the ＋ on any sermon to start one.</p>'; return; }
    wrap.innerHTML = '';
    for (var i = 0; i < pls.length; i++) {
      var p = pls[i];
      var it = await sb.from('playlist_items').select('id, sermon_id, passage, position').eq('playlist_id', p.id).order('position', { ascending: true });
      var items = it.data || [];
      var card = h('<div class="pl-card"></div>');
      card.innerHTML =
        '<div class="pl-head"><div><span class="kicker">Playlist</span><h3>' + esc(p.name) + '</h3>' +
          '<span class="pl-count">' + items.length + ' sermon' + (items.length === 1 ? '' : 's') + '</span></div>' +
          '<div class="pl-actions">' +
            (items.length ? '<button class="btn btn--ghost" data-plplay="' + esc(p.id) + '"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M8 5v14l11-7z"/></svg> Play All</button>' : '') +
            '<button class="pl-del" data-pldel="' + esc(p.id) + '">Delete</button>' +
          '</div></div>' +
        '<div class="pl-items">' + (items.length ? items.map(function (x) {
          return '<div class="pl-item"><a href="' + (byId[x.sermon_id] ? esc(byId[x.sermon_id].permalink) : '#') + '">' + esc(x.passage) + '</a>' +
            '<button class="pl-rm" data-plrm="' + esc(x.id) + '" aria-label="Remove">×</button></div>';
        }).join('') : '<p class="jw-muted" style="padding:.4rem 0">Empty — add sermons with the ＋ button.</p>') + '</div>';
      wrap.appendChild(card);
    }
  }
  async function playPlaylist(plId) {
    var idx = await loadIndex(); var byId = {}; idx.forEach(function (it) { byId[it.id] = it; });
    var it = await sb.from('playlist_items').select('sermon_id, passage, position').eq('playlist_id', plId).order('position', { ascending: true });
    var items = (it.data || []).map(function (x) { var s = byId[x.sermon_id]; return s ? { audio: s.audio, title: s.passage, sub: (s.service || '') + ' · ' + (s.date || '') } : null; }).filter(Boolean);
    if (items.length && window.jwQueue) window.jwQueue(items, 0); else toast('This playlist is empty.', false);
  }
  document.addEventListener('click', async function (e) {
    var play = e.target.closest('[data-plplay]'); if (play) { playPlaylist(play.getAttribute('data-plplay')); return; }
    var del = e.target.closest('[data-pldel]'); if (del) { if (confirm('Delete this playlist?')) { await sb.from('playlists').delete().eq('id', del.getAttribute('data-pldel')); renderLibrary(); } return; }
    var rm = e.target.closest('[data-plrm]'); if (rm) { await sb.from('playlist_items').delete().eq('id', rm.getAttribute('data-plrm')); renderLibrary(); return; }
  });

  // reflect heart state on cards inserted later (live search, Play All, related)
  var _rt;
  var mo = new MutationObserver(function () { clearTimeout(_rt); _rt = setTimeout(reflectHearts, 120); });
  if (document.body) mo.observe(document.body, { childList: true, subtree: true });

  // ------------------------------------------------------------------ init
  function onAuth() { renderAccount(); loadFavorites(); renderLibrary(); }
  sb.auth.getSession().then(function (r) { user = r.data.session && r.data.session.user; onAuth(); });
  sb.auth.onAuthStateChange(function (_e, session) { user = session && session.user; onAuth(); });

  window.jwAccount = { open: openAuth, reflect: reflectHearts, get user() { return user; } };
})();
