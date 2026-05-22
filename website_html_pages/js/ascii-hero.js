/**
 * ASCII corridor — same implementation as Modules/Theme/resources/views/layouts/auth.blade.php
 * (canvas → <pre>, pointer-following box, corner lines, radial pulse).
 */
(function () {
    var asciiLayer = document.querySelector('.solutions-ascii');
    var pre = document.getElementById('solutionsAsciiPre');
    if (!asciiLayer || !pre) return;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var ascii = '.:-=+*#.@';
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');
    canvas.width = 200;
    canvas.height = 280;

    var BOX_W = 35;
    var BOX_H = 55;
    var BOX_PAD = 30;
    var pointerTargetX = canvas.width / 2;
    var pointerTargetY = canvas.height / 2;
    var followStrength = 0.14;

    var map = function (x, xmax, xmin, tmax, tmin) {
        return ((x - xmin) / (xmax - xmin)) * (tmax - tmin) + tmin;
    };

    function clampBoxXY(tx, ty) {
        var minX = BOX_PAD;
        var minY = BOX_PAD;
        var maxX = canvas.width - BOX_W - BOX_PAD;
        var maxY = canvas.height - BOX_H - BOX_PAD;
        var x = tx - BOX_W / 2;
        var y = ty - BOX_H / 2;
        return {
            x: Math.max(minX, Math.min(maxX, x)),
            y: Math.max(minY, Math.min(maxY, y)),
        };
    }

    function setPointerFromClient(clientX, clientY) {
        var r = asciiLayer.getBoundingClientRect();
        if (r.width < 4 || r.height < 4) return;
        var nx = (clientX - r.left) / r.width;
        var ny = (clientY - r.top) / r.height;
        nx = Math.max(0, Math.min(1, nx));
        ny = Math.max(0, Math.min(1, ny));
        pointerTargetX = nx * canvas.width;
        pointerTargetY = ny * canvas.height;
    }

    var mapTable = new Array(256)
        .fill(0)
        .map(function (_, i) {
            return Math.min(
                ascii.length - 1,
                Math.max(0, Math.floor(map(i, 255, 0, ascii.length - 1, 0)))
            );
        });

    function line(x1, y1, x2, y2) {
        var g = ctx.createLinearGradient(x1, y1, x2, y2);
        g.addColorStop(0, 'rgba(15, 23, 42, 0.42)');
        g.addColorStop(0.5, 'rgba(15, 23, 42, 0.2)');
        g.addColorStop(1, 'rgba(15, 23, 42, 0.06)');
        ctx.strokeStyle = g;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
    }

    function getAsciiOutput() {
        var imgd = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var pix = imgd.data;
        var w = canvas.width;
        var h = canvas.height;
        var out = '';
        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                var idx = (y * w + x) * 4;
                var lum = pix[idx];
                out += ascii[mapTable[lum]];
            }
            if (y < h - 1) out += '\n';
        }
        return out;
    }

    var box = {
        x: canvas.width / 2 - BOX_W / 2,
        y: canvas.height / 2 - BOX_H / 2,
        w: BOX_W,
        h: BOX_H,
    };
    var tick = 0;
    var raf = null;
    var scaleEvery = 0;

    function drawFrame() {
        if (!reduceMotion) {
            tick += 0.025;
        }

        var dest = clampBoxXY(pointerTargetX, pointerTargetY);
        var k = reduceMotion ? 1 : followStrength;
        box.x += (dest.x - box.x) * k;
        box.y += (dest.y - box.y) * k;

        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#94a3b8';
        ctx.fillRect(box.x, box.y, box.w, box.h);

        line(box.x, box.y, 0, 0);
        line(box.x + box.w, box.y, canvas.width, 0);
        line(box.x, box.y + box.h, 0, canvas.height);
        line(box.x + box.w, box.y + box.h, canvas.width, canvas.height);

        var cx = box.x + box.w / 2;
        var cy = box.y + box.h / 2;
        var pulse = reduceMotion ? 22 : 15 + 14 * (0.5 + 0.5 * Math.sin(tick * 1.6));
        var rg = ctx.createRadialGradient(cx, cy, 5, cx, cy, 70);
        rg.addColorStop(0, 'rgba(17, 24, 39, 0.36)');
        rg.addColorStop(0.55, 'rgba(17, 24, 39, 0.14)');
        rg.addColorStop(1, 'rgba(17, 24, 39, 0)');
        ctx.fillStyle = rg;
        ctx.beginPath();
        ctx.arc(cx, cy, pulse, 0, 2 * Math.PI);
        ctx.fill();

        ctx.fillStyle = 'rgba(51, 65, 85, 0.65)';
        ctx.beginPath();
        ctx.arc(pointerTargetX, pointerTargetY, 4, 0, 2 * Math.PI);
        ctx.fill();

        pre.textContent = getAsciiOutput();
        scaleEvery += 1;
        if (scaleEvery % 20 === 0 || scaleEvery < 4) {
            scaleToCover();
        }
    }

    function scaleToCover() {
        pre.style.transform = 'none';
        pre.style.display = 'inline-block';
        var aw = asciiLayer.clientWidth || window.innerWidth;
        var ah = asciiLayer.clientHeight || window.innerHeight;
        if (aw < 8 || ah < 8) return;
        var pw = pre.offsetWidth || 1;
        var ph = pre.offsetHeight || 1;
        var s = Math.max((aw * 1.02) / pw, (ah * 1.02) / ph);
        pre.style.transform = 'scale(' + s + ')';
    }

    function loop() {
        drawFrame();
        raf = window.requestAnimationFrame(loop);
    }

    function stop() {
        if (raf) {
            window.cancelAnimationFrame(raf);
            raf = null;
        }
    }

    function onPointerMove(clientX, clientY) {
        setPointerFromClient(clientX, clientY);
        if (reduceMotion) {
            drawFrame();
            scaleToCover();
        }
    }

    window.addEventListener(
        'mousemove',
        function (e) {
            onPointerMove(e.clientX, e.clientY);
        },
        { passive: true }
    );
    window.addEventListener(
        'touchstart',
        function (e) {
            if (e.touches && e.touches[0]) {
                onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
            }
        },
        { passive: true }
    );
    window.addEventListener(
        'touchmove',
        function (e) {
            if (e.touches && e.touches[0]) {
                onPointerMove(e.touches[0].clientX, e.touches[0].clientY);
            }
        },
        { passive: true }
    );

    if (reduceMotion) {
        tick = 0;
        drawFrame();
        scaleToCover();
        stop();
    } else {
        loop();
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            scaleToCover();
        });
    }

    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(scaleToCover, 80);
    });

    window.addEventListener('beforeunload', stop);

    drawFrame();
    scaleToCover();
})();
