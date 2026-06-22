/* Jack Werth — audio engine with a continuous-play queue.
   - window.jwPlay(src,title,sub)         single sermon (clears the queue)
   - window.jwQueue([{audio,title,sub}],i) "Play All" / playlist, auto-advances
   Uses event delegation so cards rendered by live search also work.            */
(function () {
  'use strict';

  var audio = new Audio();
  audio.preload = 'none';
  var currentSrc = null;
  var queue = [], qIndex = -1;

  var SVG = {
    play:  '<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
    pause: '<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>',
    prev:  '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h2v12H6zM20 6v12l-9-6z"/></svg>',
    next:  '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 6h2v12h-2zM4 6l9 6-9 6z"/></svg>'
  };

  var el = document.createElement('div');
  el.className = 'jw-player';
  el.innerHTML =
    '<div class="inner">' +
      '<div class="transport">' +
        '<button class="qbtn" data-prev aria-label="Previous">' + SVG.prev + '</button>' +
        '<button class="play" data-pp aria-label="Play/pause">' + SVG.play + SVG.pause + '</button>' +
        '<button class="qbtn" data-next aria-label="Next">' + SVG.next + '</button>' +
      '</div>' +
      '<div class="now"><div class="p" data-title>—</div><div class="s"><span data-sub></span><span class="qpos" data-qpos></span></div></div>' +
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
  function fmt(t) { if (!t || isNaN(t)) return '0:00'; var m = Math.floor(t / 60), s = Math.floor(t % 60); return m + ':' + (s < 10 ? '0' : '') + s; }

  function setPlayingState(playing) {
    el.classList.toggle('playing', playing);
    $('[data-pp]').classList.toggle('is-playing', playing);
    document.querySelectorAll('.play[data-audio], [data-feature-play]').forEach(function (b) {
      var src = b.getAttribute('data-audio') || (b.closest('[data-feature]') && b.closest('[data-feature]').getAttribute('data-audio'));
      b.classList.toggle('is-playing', playing && src === currentSrc);
    });
  }

  function updateQueueUI() {
    var has = qIndex >= 0 && queue.length > 1;
    el.classList.toggle('has-queue', has);
    $('[data-qpos]').textContent = has ? ' · ' + (qIndex + 1) + ' / ' + queue.length : '';
    $('[data-prev]').disabled = !has || qIndex <= 0;
    $('[data-next]').disabled = !has || qIndex >= queue.length - 1;
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

  function playAt(i) {
    if (i < 0 || i >= queue.length) return;
    qIndex = i; var it = queue[i];
    load(it.audio, it.title, it.sub);
    updateQueueUI();
    if (window.jwOnTrack) try { window.jwOnTrack(it, i, queue); } catch (e) {}  // hook for resume-state (Part 2)
  }

  window.jwPlay = function (src, title, sub) {
    queue = []; qIndex = -1; updateQueueUI();            // single play clears the queue
    if (src === currentSrc && !audio.paused) audio.pause(); else load(src, title, sub);
  };
  window.jwQueue = function (items, start) {
    if (!items || !items.length) return;
    queue = items; playAt(Math.max(0, Math.min(start || 0, items.length - 1)));
  };
  window.jwAudio = audio;  // exposed for resume-state seeking (Part 2)

  // ---- single play buttons (cards + inline feature) ----------------------
  document.addEventListener('click', function (e) {
    var fp = e.target.closest('[data-feature-play]');
    if (fp) { var f = fp.closest('[data-feature]'); if (f) window.jwPlay(f.getAttribute('data-audio'), f.getAttribute('data-title'), f.getAttribute('data-sub')); return; }
    var b = e.target.closest('.play[data-audio]');
    if (b) { window.jwPlay(b.getAttribute('data-audio'), b.getAttribute('data-title'), b.getAttribute('data-sub')); }
  });

  // ---- transport ----------------------------------------------------------
  function seekFrom(seekEl, e) {
    var r = seekEl.getBoundingClientRect();
    var x = ((e.touches ? e.touches[0].clientX : e.clientX) - r.left) / r.width;
    if (audio.duration) audio.currentTime = Math.max(0, Math.min(1, x)) * audio.duration;
  }
  el.addEventListener('click', function (e) {
    if (e.target.closest('[data-pp]')) { audio.paused ? audio.play() : audio.pause(); }
    if (e.target.closest('[data-prev]')) { if (qIndex > 0) playAt(qIndex - 1); }
    if (e.target.closest('[data-next]')) { if (qIndex < queue.length - 1) playAt(qIndex + 1); }
    if (e.target.closest('[data-close]')) { audio.pause(); el.classList.remove('up'); }
    if (e.target.closest('[data-rate]')) {
      var rates = [1, 1.25, 1.5, 1.75, 2, 0.75], i = rates.indexOf(audio.playbackRate);
      audio.playbackRate = rates[(i + 1) % rates.length];
      e.target.closest('[data-rate]').textContent = audio.playbackRate + '×';
    }
    var s = e.target.closest('[data-seek]'); if (s) seekFrom(s, e);
  });
  document.addEventListener('click', function (e) { var s = e.target.closest('[data-feature-seek]'); if (s) seekFrom(s, e); });

  audio.addEventListener('play',  function () { setPlayingState(true); });
  audio.addEventListener('pause', function () { setPlayingState(false); });
  audio.addEventListener('loadedmetadata', function () { $('[data-dur]').textContent = fmt(audio.duration); });
  audio.addEventListener('timeupdate', function () {
    var pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
    $('[data-seek] .fill').style.width = pct + '%';
    $('[data-seek] .head').style.left = pct + '%';
    $('[data-cur]').textContent = fmt(audio.currentTime);
    var feat = document.querySelector('[data-feature]');
    if (feat && currentSrc === feat.getAttribute('data-audio')) {
      var ff = document.querySelector('[data-feature-seek] .fill'); if (ff) ff.style.width = pct + '%';
      var fh = document.querySelector('[data-feature-seek] .head'); if (fh) fh.style.left = pct + '%';
      var fc = document.querySelector('[data-feature-cur]'); if (fc) fc.textContent = fmt(audio.currentTime);
      var fd = document.querySelector('[data-feature-dur]'); if (fd) fd.textContent = fmt(audio.duration);
    }
    if (window.jwOnProgress) try { window.jwOnProgress(audio.currentTime, queue[qIndex]); } catch (e) {}  // resume hook (Part 2)
  });
  audio.addEventListener('ended', function () {
    if (qIndex >= 0 && qIndex < queue.length - 1) playAt(qIndex + 1);   // auto-advance the queue
    else setPlayingState(false);
  });
})();
