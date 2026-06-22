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
            '<svg viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-10-9.2C.6 9 1.6 5.7 4.6 5c1.9-.4 3.6.5 4.4 2 .8-1.5 2.5-2.4 4.4-2 3 .7 4 4 2.6 6.8C19.5 16.4 12 21 12 21z"/></svg>' +
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
  }

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
