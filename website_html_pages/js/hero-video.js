(function () {
    var wrap = document.getElementById('heroVideoWrap');
    var video = document.getElementById('heroVideo');
    var soundBtn = document.getElementById('heroVideoSound');
    var fsBtn = document.getElementById('heroVideoFullscreen');

    if (!wrap || !video) {
        return;
    }

    var loader = document.getElementById('heroVideoLoader');

    function markVideoReady() {
        if (wrap.classList.contains('is-ready')) {
            return;
        }
        wrap.classList.remove('is-loading');
        wrap.classList.add('is-ready');
        if (loader) {
            loader.setAttribute('aria-busy', 'false');
        }
    }

    function tryMarkReady() {
        if (video.readyState >= 2) {
            markVideoReady();
        }
    }

    tryMarkReady();
    video.addEventListener('loadeddata', tryMarkReady);
    video.addEventListener('canplay', markVideoReady, { once: true });
    video.addEventListener('playing', markVideoReady, { once: true });
    video.addEventListener('error', markVideoReady, { once: true });

    function requestFullscreen(el) {
        if (el.requestFullscreen) {
            return el.requestFullscreen();
        }
        if (el.webkitRequestFullscreen) {
            return el.webkitRequestFullscreen();
        }
        if (el.msRequestFullscreen) {
            return el.msRequestFullscreen();
        }
        return Promise.reject(new Error('Fullscreen not supported'));
    }

    function exitFullscreen() {
        if (document.exitFullscreen) {
            return document.exitFullscreen();
        }
        if (document.webkitExitFullscreen) {
            return document.webkitExitFullscreen();
        }
        if (document.msExitFullscreen) {
            return document.msExitFullscreen();
        }
    }

    function getFullscreenElement() {
        return (
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.msFullscreenElement ||
            null
        );
    }

    function isWrapFullscreen() {
        return getFullscreenElement() === wrap;
    }

    if (soundBtn) {
        function updateSoundButton() {
            var muted = video.muted;
            soundBtn.classList.toggle('is-sound-on', !muted);
            soundBtn.setAttribute('aria-pressed', muted ? 'true' : 'false');
            soundBtn.setAttribute('aria-label', muted ? 'Enable sound' : 'Disable sound');
            soundBtn.title = muted ? 'Enable sound' : 'Disable sound';
        }

        updateSoundButton();

        soundBtn.addEventListener('click', function () {
            video.muted = !video.muted;
            if (!video.muted) {
                video.volume = 1;
                if (video.paused) {
                    video.play().catch(function () {});
                }
            }
            updateSoundButton();
        });
    }

    if (fsBtn) {
        function updateFullscreenButton() {
            var active = isWrapFullscreen();
            fsBtn.classList.toggle('is-fullscreen', active);
            fsBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
            fsBtn.setAttribute('aria-label', active ? 'Exit full screen' : 'Full screen');
            fsBtn.title = active ? 'Exit full screen' : 'Full screen';
        }

        updateFullscreenButton();

        fsBtn.addEventListener('click', function () {
            if (isWrapFullscreen()) {
                exitFullscreen();
                return;
            }

            var playPromise = requestFullscreen(wrap);
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    if (video.webkitEnterFullscreen) {
                        video.webkitEnterFullscreen();
                    }
                });
            }
        });

        ['fullscreenchange', 'webkitfullscreenchange', 'MSFullscreenChange'].forEach(function (evt) {
            document.addEventListener(evt, updateFullscreenButton);
        });
    }
})();
