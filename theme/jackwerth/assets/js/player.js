/* Jack Werth — audio engine: one sticky player, shared by every play button.
   Uses event delegation so cards loaded via live search work automatically. */
(function () {
  'use strict';

  var audio = new Audio();
  audio.preload = 'none';
  var currentSrc = null;

  // ---- sticky player UI ---------------------------------------------------
  var el = document.createElement('div');
  el.className = 'jw-player';
  el.innerHTML =
    '<div class="inner">' +
      '<button class="play" data-pp aria-label="Play/pause">' +
        '<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>' +
        '<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>' +
      '</button>' +
      '<div class="now"><div class="p" data-title>—</div><div class="s" data-sub></div></div>' +
      '<div class="bar">' +
        '<span class="time" data-cur>0:00</span>' +
        '<div class="seek" data-seek><div class="fill"></div><div class="head"></div></div>' +
        '<span class="time" data-dur>0:00</span>' +
        '<button class="rate" data-rate>1×</button>' +
        '<button class="close" data-close aria-label="Close">×</button>' +
      '</div>' +
    '</div>';
  document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(el); });

  var $ = function (s, r) { return (r || el).querySelector(s); };
  var fmt = function (t) {
    if (!t || isNaN(t)) return '0:00';
    var m = Math.floor(t / 60), s = Math.floor(t % 60);
    return m + ':' + (s < 10 ? '0' : '') + s;
  };

  function setPlayingState(playing) {
    el.classList.toggle('playing', playing);
    $('[data-pp]').classList.toggle('is-playing', playing);
    // reflect on every matching card/feature button
    document.querySelectorAll('.play[data-audio], [data-feature-play]').forEach(function (b) {
      var src = b.getAttribute('data-audio') || (b.closest('[data-feature]') && b.closest('[data-feature]').getAttribute('data-audio'));
      b.classList.toggle('is-playing', playing && src === currentSrc);
    });
  }

  function load(src, title, sub) {
    if (src !== currentSrc) {
      audio.src = src; currentSrc = src;
      $('[data-title]').textContent = title || 'Sermon';
      $('[data-sub]').textContent = sub || '';
    }
    el.classList.add('up');
    audio.play();
  }

  window.jwPlay = function (src, title, sub) {
    if (src === currentSrc && !audio.paused) { audio.pause(); }
    else { load(src, title, sub); }
  };

  // ---- delegation: any play button on the page ---------------------------
  document.addEventListener('click', function (e) {
    var fp = e.target.closest('[data-feature-play]');
    if (fp) {
      var f = fp.closest('[data-feature]');
      if (f) window.jwPlay(f.getAttribute('data-audio'), f.getAttribute('data-title'), f.getAttribute('data-sub'));
      return;
    }
    var b = e.target.closest('.play[data-audio]');
    if (b) { window.jwPlay(b.getAttribute('data-audio'), b.getAttribute('data-title'), b.getAttribute('data-sub')); }
  });

  // ---- transport ----------------------------------------------------------
  $('[data-pp]') && el.addEventListener('click', function (e) {
    if (e.target.closest('[data-pp]')) { audio.paused ? audio.play() : audio.pause(); }
    if (e.target.closest('[data-close]')) { audio.pause(); el.classList.remove('up'); }
    if (e.target.closest('[data-rate]')) {
      var rates = [1, 1.25, 1.5, 1.75, 2, 0.75], i = rates.indexOf(audio.playbackRate);
      audio.playbackRate = rates[(i + 1) % rates.length];
      e.target.closest('[data-rate]').textContent = audio.playbackRate + '×';
    }
  });

  function seekFrom(seekEl, e) {
    var r = seekEl.getBoundingClientRect();
    var x = ((e.touches ? e.touches[0].clientX : e.clientX) - r.left) / r.width;
    if (audio.duration) audio.currentTime = Math.max(0, Math.min(1, x)) * audio.duration;
  }
  el.addEventListener('click', function (e) { var s = e.target.closest('[data-seek]'); if (s) seekFrom(s, e); });

  // feature seek (single page)
  document.addEventListener('click', function (e) {
    var s = e.target.closest('[data-feature-seek]');
    if (s) seekFrom(s, e);
  });

  audio.addEventListener('play',  function () { setPlayingState(true); });
  audio.addEventListener('pause', function () { setPlayingState(false); });
  audio.addEventListener('loadedmetadata', function () { $('[data-dur]').textContent = fmt(audio.duration); });
  audio.addEventListener('timeupdate', function () {
    var pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
    $('[data-seek] .fill').style.width = pct + '%';
    $('[data-seek] .head').style.left = pct + '%';
    $('[data-cur]').textContent = fmt(audio.currentTime);
    // mirror onto feature player if present
    var ff = document.querySelector('[data-feature-seek] .fill');
    if (ff && currentSrc === (document.querySelector('[data-feature]') || {}).getAttribute && currentSrc === document.querySelector('[data-feature]').getAttribute('data-audio')) {
      ff.style.width = pct + '%';
      var fh = document.querySelector('[data-feature-seek] .head'); if (fh) fh.style.left = pct + '%';
      var fc = document.querySelector('[data-feature-cur]'); if (fc) fc.textContent = fmt(audio.currentTime);
      var fd = document.querySelector('[data-feature-dur]'); if (fd) fd.textContent = fmt(audio.duration);
    }
  });
  audio.addEventListener('ended', function () { setPlayingState(false); });
})();
