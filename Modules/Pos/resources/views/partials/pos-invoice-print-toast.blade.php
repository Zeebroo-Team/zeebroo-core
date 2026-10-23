{{--
    Invoice receipt mode: open the invoice print page after checkout. This runs on page load
    (no user click), so browsers usually block the popup — when that happens the toast stays
    up with a "Print invoice" link the cashier can click.
--}}
<script>
(function () {
    var url = @json($printInvoiceUrl);
    var popup = null;
    try { popup = window.open(url, '_blank'); } catch (e) {}
    var blocked = !popup || popup.closed;

    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:500;display:flex;align-items:center;gap:12px;background:#16a34a;color:#fff;padding:10px 12px 10px 16px;border-radius:10px;font-size:13px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.25);';
    var msg = document.createElement('span');
    msg.textContent = @json(session('status') ?: 'Invoice created.');
    toast.appendChild(msg);

    if (blocked) {
        var link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.innerHTML = '<i class="fa fa-print" aria-hidden="true"></i> Print invoice';
        link.style.cssText = 'color:#16a34a;background:#fff;padding:6px 10px;border-radius:7px;text-decoration:none;white-space:nowrap;';
        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '&times;';
        close.style.cssText = 'background:none;border:0;color:#fff;font-size:18px;line-height:1;cursor:pointer;padding:0 2px;';
        link.addEventListener('click', function () { setTimeout(function () { toast.remove(); }, 300); });
        close.addEventListener('click', function () { toast.remove(); });
        toast.appendChild(link);
        toast.appendChild(close);
    } else {
        setTimeout(function () { toast.remove(); }, 4000);
    }

    document.body.appendChild(toast);
    document.dispatchEvent(new CustomEvent('pos-clear-cart-and-reset'));
})();
</script>
