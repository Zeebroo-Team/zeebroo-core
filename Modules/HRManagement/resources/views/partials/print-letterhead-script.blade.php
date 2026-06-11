{{-- Fabric.js letterhead renderer for HR print views. Requires: $letterheadCanvasJson (nullable) --}}
@if($letterheadCanvasJson)
<canvas id="lhRenderCanvas" width="794" height="1123"
        style="position:fixed;left:-9999px;top:-9999px;pointer-events:none;visibility:hidden;"></canvas>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
<script>
(function () {
    var LH_W    = 794,  LH_H    = 1123;
    var HEADER_H = 115, FOOTER_Y = 1074;

    var printBtn = document.getElementById('lhPrintBtn');
    if (printBtn) { printBtn.disabled = true; printBtn.textContent = 'Preparing…'; }

    function ready() {
        if (printBtn) {
            printBtn.disabled = false;
            printBtn.innerHTML = '&#128438;&nbsp; Print / Save PDF';
        }
    }

    function swapZone(imgId, fallbackId, url) {
        var img = document.getElementById(imgId);
        var fb  = document.getElementById(fallbackId);
        if (img) { img.src = url; img.style.display = 'block'; }
        if (fb)  { fb.style.display = 'none'; }
    }

    function doExport(fc) {
        var opts = { format: 'png', multiplier: 1, enableRetinaScaling: false };
        try {
            swapZone('lhHeaderImg', 'lhHeaderFallback',
                fc.toDataURL(Object.assign({}, opts, { left: 0, top: 0,        width: LH_W, height: HEADER_H })));
            swapZone('lhFooterImg', 'lhFooterFallback',
                fc.toDataURL(Object.assign({}, opts, { left: 0, top: FOOTER_Y, width: LH_W, height: LH_H - FOOTER_Y })));
            ready();
        } catch (e) {
            fc.getObjects('image').forEach(function (o) { fc.remove(o); });
            fc.renderAll();
            try {
                swapZone('lhHeaderImg', 'lhHeaderFallback',
                    fc.toDataURL(Object.assign({}, opts, { left: 0, top: 0,        width: LH_W, height: HEADER_H })));
                swapZone('lhFooterImg', 'lhFooterFallback',
                    fc.toDataURL(Object.assign({}, opts, { left: 0, top: FOOTER_Y, width: LH_W, height: LH_H - FOOTER_Y })));
                ready();
            } catch (e2) {
                ready();
            }
        }
    }

    var el = document.getElementById('lhRenderCanvas');
    if (!el || !window.fabric) { ready(); return; }

    var fc = new fabric.Canvas(el, {
        width: LH_W, height: LH_H,
        selection: false, renderOnAddRemove: false,
        enableRetinaScaling: false,
    });

    fc.loadFromJSON(@json($letterheadCanvasJson), function () {
        var imgObjs   = fc.getObjects('image');
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
            imgObj.setSrc(src, function () {
                if (--remaining === 0) { fc.renderAll(); doExport(fc); }
            }, { crossOrigin: 'anonymous' });
        });
    });
})();
</script>
@endif
