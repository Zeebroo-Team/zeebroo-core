{{-- Fabric.js letterhead renderer for invoices. Requires: $letterheadCanvasJson (nullable) --}}
@if($letterheadCanvasJson)
<canvas id="lhRenderCanvas" width="794" height="1123"
        style="position:absolute;left:-9999px;top:0;pointer-events:none;visibility:hidden;"></canvas>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
<script>
(function () {
    var LH_W = 794, LH_H = 1123;
    var HEADER_H_DEFAULT = 115, HEADER_H_MIN = 60, HEADER_H_MAX = 500, FOOTER_CUTOFF = 900;

    /**
     * The invoice header banner is only ever the top cluster of the
     * letterhead design (logo/name/contact strip, etc.) — a page-bottom
     * footer band some designs also carry (positioned near FOOTER_CUTOFF..
     * LH_H) is intentionally never printed on invoices. Rather than
     * assuming every design's top cluster is exactly 115px tall (true only
     * for the auto-generated wizard layout), measure how far the actual
     * top-cluster content extends so hand-built or AI-generated designs
     * with a taller banner (e.g. a contact strip under the logo band)
     * aren't cropped off.
     */
    function computeHeaderHeight(fc) {
        var maxBottom = 0;
        fc.getObjects().forEach(function (o) {
            // getBoundingRect() resolves each object's actual canvas-space box
            // regardless of its origin/rotation/scale, unlike reading .top/.height
            // directly (hand-drawn shapes aren't guaranteed left/top origin).
            var box = typeof o.getBoundingRect === 'function'
                ? o.getBoundingRect()
                : { top: o.top || 0, height: (o.height || 0) * (o.scaleY || 1) };
            if (box.top >= FOOTER_CUTOFF) return;
            var bottom = box.top + box.height;
            if (bottom > maxBottom) maxBottom = bottom;
        });
        if (!maxBottom) return HEADER_H_DEFAULT;
        return Math.min(Math.max(Math.ceil(maxBottom), HEADER_H_MIN), HEADER_H_MAX);
    }

    function swapZone(imgId, fallbackId, url) {
        var img = document.getElementById(imgId);
        var fb  = document.getElementById(fallbackId);
        if (img) { img.src = url; img.style.display = 'block'; }
        if (fb)  { fb.style.display = 'none'; }
    }

    /**
     * Letterhead images are baked into canvas_json with whatever absolute
     * host was current when the design was saved (e.g. "localhost:8000").
     * If the page is later viewed from a different host string pointing at
     * the same app (e.g. "127.0.0.1:8000", a LAN IP, or a different domain
     * after a deploy), the browser treats the image as cross-origin and
     * blocks the canvas export since the storage disk sends no CORS
     * headers. Rewriting to the current origin keeps it same-origin so the
     * export always succeeds, regardless of which host it was saved under.
     */
    function sameOriginUrl(url) {
        try {
            var u = new URL(url, window.location.href);
            if (u.protocol !== 'http:' && u.protocol !== 'https:') return url;
            u.protocol = window.location.protocol;
            u.host = window.location.host;
            return u.href;
        } catch (e) {
            return url;
        }
    }

    function doExport(fc) {
        var opts = { format: 'png', multiplier: 1, enableRetinaScaling: false, left: 0, top: 0, width: LH_W, height: computeHeaderHeight(fc) };
        try {
            swapZone('lhHeaderImg', 'lhBizFallback', fc.toDataURL(opts));
        } catch (e) {
            fc.getObjects('image').forEach(function (o) { fc.remove(o); });
            fc.renderAll();
            try {
                swapZone('lhHeaderImg', 'lhBizFallback', fc.toDataURL(opts));
            } catch (e2) {
                // keep HTML fallback
            }
        }
    }

    var el = document.getElementById('lhRenderCanvas');
    if (!el || !window.fabric) return;

    var fc = new fabric.Canvas(el, {
        width: LH_W, height: LH_H,
        selection: false, renderOnAddRemove: false,
        enableRetinaScaling: false,
    });

    fc.loadFromJSON(@json($letterheadCanvasJson), function () {
        var imgObjs = fc.getObjects('image');
        var remaining = imgObjs.length;

        if (remaining === 0) {
            fc.renderAll();
            doExport(fc);
            return;
        }

        imgObjs.forEach(function (imgObj) {
            var src = '';
            try { src = imgObj.getSrc(); } catch (e) {}
            if (!src) {
                if (--remaining === 0) { fc.renderAll(); doExport(fc); }
                return;
            }
            imgObj.setSrc(sameOriginUrl(src), function () {
                if (--remaining === 0) { fc.renderAll(); doExport(fc); }
            }, { crossOrigin: 'anonymous' });
        });
    });
})();
</script>
@endif
