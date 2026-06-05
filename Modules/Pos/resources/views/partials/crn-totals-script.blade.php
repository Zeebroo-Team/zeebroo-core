{{--
    Shared live-total calculator for the "ref" (sale-reference) mode.
    Variables: $formId (string), $mode ('ref' | 'open')
--}}
@if($mode === 'ref')
<script>
(function () {
    var form = document.getElementById('{{ $formId }}');
    if (!form) return;
    function recalc() {
        var totalEl = document.getElementById('crnRunningTotal');
        var inputs  = form.querySelectorAll('.crn-qty-input:not([disabled])');
        var total   = 0;
        inputs.forEach(function (inp) {
            var idx   = inp.getAttribute('data-row');
            var qty   = Math.max(0, parseFloat(inp.value) || 0);
            var max   = parseFloat(inp.getAttribute('data-max')) || 0;
            var price = parseFloat(inp.getAttribute('data-unit-price')) || 0;
            var safe  = Math.min(qty, max);
            var line  = safe * price;
            total    += line;
            var lineEl = document.getElementById('crn-line-' + idx);
            if (lineEl) lineEl.innerHTML = '<strong style="color:var(--text);">' + line.toFixed(2) + '</strong>';
        });
        if (totalEl) totalEl.textContent = total.toFixed(2);
    }
    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('crn-qty-input')) recalc();
    });

    // Validate at least one qty > 0
    form.addEventListener('submit', function (e) {
        var inputs = form.querySelectorAll('.crn-qty-input:not([disabled])');
        var anyPositive = false;
        inputs.forEach(function (inp) {
            var v   = parseFloat(inp.value) || 0;
            var max = parseFloat(inp.getAttribute('data-max')) || 0;
            if (v > max + 0.0005) inp.value = String(max);
            if ((parseFloat(inp.value) || 0) > 0) anyPositive = true;
        });
        if (!anyPositive) {
            e.preventDefault();
            alert('Enter a return quantity greater than 0 for at least one item.');
        }
    });
    recalc();
})();
</script>
@elseif($mode === 'open')
<script>
(function () {
    var form = document.getElementById('{{ $formId }}');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var rows = document.querySelectorAll('#crnOpenBody tr[data-row]');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Add at least one product to process a return.');
            return;
        }
        var allValid = true;
        rows.forEach(function (tr) {
            var pid = tr.querySelector('.crn-pid-inp');
            var qty = tr.querySelector('.crn-qty-input');
            if (!pid || !pid.value) allValid = false;
            if (!qty || (parseFloat(qty.value) || 0) <= 0) allValid = false;
        });
        if (!allValid) {
            e.preventDefault();
            alert('Each row must have a valid product selected and a quantity greater than 0.');
        }
    });
})();
</script>
@endif
