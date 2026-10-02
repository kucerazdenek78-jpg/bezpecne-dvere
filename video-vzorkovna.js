/* Video vzorkovny – hlavní banner stránky Kontakt (YouTube IFrame API, ztlumené, ve smyčce) */
(function () {
  var el = document.querySelector('.kv-video[data-yt]');
  if (!el || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
  var id = el.getAttribute('data-yt');
  function restart(p) { try { p.seekTo(0, true); p.playVideo(); } catch (e) {} }
  function start() {
    var host = document.createElement('div');
    el.appendChild(host);
    var guard = null;
    new YT.Player(host, {
      host: 'https://www.youtube-nocookie.com',
      videoId: id,
      playerVars: { autoplay: 1, mute: 1, controls: 0, loop: 1, playlist: id, playsinline: 1, rel: 0, modestbranding: 1, disablekb: 1, iv_load_policy: 3, fs: 0 },
      events: {
        onReady: function (e) { e.target.mute(); e.target.playVideo(); },
        onStateChange: function (e) {
          if (e.data === YT.PlayerState.ENDED) restart(e.target);
          if (e.data === YT.PlayerState.PLAYING && !guard) {
            guard = setInterval(function () {
              try { var d = e.target.getDuration(); if (d > 1 && e.target.getCurrentTime() >= d - 0.35) restart(e.target); } catch (x) {}
            }, 250);
          }
        }
      }
    });
  }
  if (window.YT && YT.Player) { start(); return; }
  var prev = window.onYouTubeIframeAPIReady;
  window.onYouTubeIframeAPIReady = function () { if (prev) prev(); start(); };
  var t = document.createElement('script'); t.src = 'https://www.youtube.com/iframe_api'; document.head.appendChild(t);
})();
