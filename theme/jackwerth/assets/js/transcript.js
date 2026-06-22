/* Follow-along transcript: fetches a sermon's word-timed transcript and syncs
   word highlighting to the player. Tap any word to seek there. */
(function () {
  'use strict';

  // ---- reading text-size control (scales transcript + Scripture; saved across visits) ----
  (function () {
    var KEY = 'jw-read-size', MIN = 0.8, MAX = 2.0, STEP = 0.15;
    function getv() { var v = parseFloat(localStorage.getItem(KEY)); return (v >= MIN && v <= MAX) ? v : 1; }
    function applyv(v) { document.documentElement.style.setProperty('--jw-read-size', v); }
    applyv(getv());
    document.addEventListener('click', function (e) {
      var inc = e.target.closest('[data-ts-inc]'), dec = e.target.closest('[data-ts-dec]');
      if (!inc && !dec) return;
      var v = getv(); v = inc ? Math.min(MAX, v + STEP) : Math.max(MIN, v - STEP);
      v = Math.round(v * 100) / 100;
      try { localStorage.setItem(KEY, v); } catch (err) {}
      applyv(v);
    });
  })();

  var panel = document.querySelector('[data-transcript]');
  if (!panel) return;
  var id   = panel.getAttribute('data-transcript');
  var wrap = document.querySelector('[data-transcript-wrap]');
  var base = window.JW_HOME || '/';
  var words = [], spans = null, cur = -1;

  fetch(base + 'wp-content/jw-data/transcripts/' + encodeURIComponent(id) + '.json')
    .then(function (r) { if (!r.ok) throw 0; return r.json(); })
    .then(render)
    .catch(function () { /* no transcript yet — leave the fallback prose visible */ });

  function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  function render(data) {
    words = data.words || [];
    if (!words.length) return;
    var html = '';
    for (var i = 0; i < words.length; i++) {
      html += '<span class="tw" data-i="' + i + '" data-s="' + words[i].s + '">' + esc(words[i].w) + '</span>';
    }
    panel.innerHTML = html;
    spans = panel.getElementsByClassName('tw');
    if (wrap) wrap.hidden = false;
    var fb = document.querySelector('[data-prose-fallback]'); if (fb) fb.style.display = 'none';

    panel.addEventListener('click', function (e) {
      var t = e.target.closest('.tw'); if (!t || !window.jwAudio) return;
      window.jwAudio.currentTime = parseFloat(t.getAttribute('data-s'));
      if (window.jwAudio.paused) { var p = window.jwAudio.play(); if (p && p.catch) p.catch(function () {}); }
    });
  }

  // chain into the player's progress hook so highlighting follows playback
  var prev = window.jwOnProgress;
  window.jwOnProgress = function (t, item) {
    if (prev) { try { prev(t, item); } catch (e) {} }
    if (!words.length) return;
    var i = locate(t);
    if (i !== cur) {
      if (cur >= 0 && spans[cur]) spans[cur].classList.remove('current');
      cur = i;
      if (i >= 0 && spans[i]) { spans[i].classList.add('current'); follow(spans[i]); }
    }
  };

  function locate(t) {                       // largest index with start <= t (binary search)
    var lo = 0, hi = words.length - 1, ans = -1;
    while (lo <= hi) { var m = (lo + hi) >> 1; if (words[m].s <= t) { ans = m; lo = m + 1; } else hi = m - 1; }
    return ans;
  }

  var lastF = 0;
  function follow(el) {                       // keep the current word centered within the panel
    var now = Date.now(); if (now - lastF < 350) return; lastF = now;
    var pr = panel.getBoundingClientRect(), er = el.getBoundingClientRect();
    if (er.top < pr.top + 48 || er.bottom > pr.bottom - 48) {
      panel.scrollTop += (er.top - pr.top) - panel.clientHeight / 2 + er.height / 2;
    }
  }
})();
