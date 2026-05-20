@once
<script>
window.playPosBeep = (function () {
    const url = @json(asset('pos/beep.wav'));
    let audio = null;

    return function playPosBeep() {
        try {
            if (!audio) {
                audio = new Audio(url);
                audio.preload = 'auto';
            }
            audio.currentTime = 0;
            const playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {});
            }
        } catch (e) {}
    };
})();
</script>
@endonce
