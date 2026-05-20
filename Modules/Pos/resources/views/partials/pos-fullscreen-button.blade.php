@once
<style>
.pos-fullscreen-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;min-height:34px;padding:7px 10px;font-size:14px;font-weight:700;border-radius:8px;border:1px solid var(--border);background:color-mix(in srgb,var(--card) 90%,transparent);color:var(--text);cursor:pointer;box-sizing:border-box;}
.pos-fullscreen-btn:hover{border-color:color-mix(in srgb,var(--primary) 40%,var(--border));}
.pos-fullscreen-btn:focus-visible{outline:2px solid color-mix(in srgb,var(--primary) 55%,transparent);outline-offset:2px;}
.pos-fullscreen-btn.is-active{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 12%,transparent);}
body.pos-walking-active .pos-fullscreen-btn{min-width:30px;min-height:30px;padding:6px 8px;font-size:13px;border-radius:8px;}
</style>
@endonce

<button
    type="button"
    class="pos-fullscreen-btn"
    id="pos-fullscreen-btn"
    title="Full screen"
    aria-label="Full screen"
    aria-pressed="false"
>
    <i class="fa fa-expand" data-pos-fs-icon aria-hidden="true"></i>
</button>

@once
<script>
(function () {
    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function requestFullscreen() {
        const el = document.documentElement;
        if (el.requestFullscreen) {
            return el.requestFullscreen();
        }
        if (el.webkitRequestFullscreen) {
            return el.webkitRequestFullscreen();
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
        return Promise.reject(new Error('Fullscreen not supported'));
    }

    function updateFullscreenButton(btn) {
        if (!btn) return;
        const on = isFullscreen();
        const icon = btn.querySelector('[data-pos-fs-icon]');
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Exit full screen (Esc)' : 'Full screen';
        btn.setAttribute('aria-label', btn.title);
        if (icon) {
            icon.className = on ? 'fa fa-compress' : 'fa fa-expand';
        }
    }

    window.initPosFullscreenButton = function () {
        const btn = document.getElementById('pos-fullscreen-btn');
        if (!btn || btn.dataset.posFsBound === '1') {
            updateFullscreenButton(btn);
            return;
        }
        btn.dataset.posFsBound = '1';

        btn.addEventListener('click', function () {
            const toggle = isFullscreen() ? exitFullscreen() : requestFullscreen();
            if (toggle && typeof toggle.catch === 'function') {
                toggle.catch(function () {});
            }
        });

        ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (evt) {
            document.addEventListener(evt, function () {
                updateFullscreenButton(btn);
            });
        });

        updateFullscreenButton(btn);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initPosFullscreenButton);
    } else {
        window.initPosFullscreenButton();
    }
})();
</script>
@endonce
