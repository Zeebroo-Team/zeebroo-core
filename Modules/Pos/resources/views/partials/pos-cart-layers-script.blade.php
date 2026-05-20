@once
<script>
window.posCartKey = window.posCartKey || function (productId, layerId) {
    return String(productId) + ':' + (layerId != null && layerId !== '' ? String(layerId) : 'fifo');
};

window.posParseProductLayers = function (source) {
    if (!source) return [];
    if (Array.isArray(source)) return source;
    if (typeof source === 'string') {
        const raw = source.trim();
        if (!raw) return [];
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }
    return [];
};

window.posLayersFromButton = function (btn, catalog) {
    if (!btn) return [];
    let layers = window.posParseProductLayers(btn.getAttribute('data-product-layers'));
    if (layers.length) return layers;
    const id = parseInt(btn.dataset.productId, 10);
    if (catalog && catalog[id] && Array.isArray(catalog[id].layers)) {
        return catalog[id].layers;
    }
    return [];
};

window.posBuildCartLineFromButton = function (btn, layer, catalog) {
    const layers = window.posLayersFromButton(btn, catalog);
    const picked = layer || (layers.length ? layers[0] : null);
    const id = parseInt(btn.dataset.productId, 10);
    const layerId = picked ? parseInt(picked.id, 10) : null;
    const stock = picked ? parseFloat(picked.quantity_remaining) : parseFloat(btn.dataset.stock) || 0;
    const unitPrice = picked ? parseFloat(picked.unit_sell_price) : parseFloat(btn.dataset.unitPrice) || 0;

    return {
        cartKey: window.posCartKey(id, layerId),
        id: id,
        layerId: layerId,
        layerLabel: picked ? (picked.label || ('Batch #' + picked.id)) : '',
        name: btn.dataset.productName || 'Product',
        sku: btn.dataset.productSku || '',
        unitPrice: unitPrice,
        quantity: 0,
        stock: stock,
    };
};

window.posAddCartLine = function (cart, line, delta) {
    delta = delta == null ? 1 : delta;
    if (!line || !line.cartKey) return false;
    const existing = cart.get(line.cartKey);
    const maxStock = parseFloat(line.stock) || 0;
    if (existing) {
        if (existing.quantity + delta > maxStock) return false;
        existing.quantity += delta;
        return true;
    }
    if (maxStock <= 0 || delta <= 0) return false;
    cart.set(line.cartKey, Object.assign({}, line, { quantity: delta }));
    return true;
};

window.posAddProductFromButton = async function (btn, cart, catalog) {
    if (!btn || btn.disabled) return false;
    const layers = window.posLayersFromButton(btn, catalog);
    let layer = null;
    if (layers.length > 1) {
        const pick = window.posPickStockLayer;
        if (typeof pick !== 'function') {
            console.error('POS stock picker not initialized');
            return false;
        }
        layer = await pick({
            id: parseInt(btn.dataset.productId, 10),
            name: btn.dataset.productName || '',
        }, layers);
        if (!layer) return false;
    } else if (layers.length === 1) {
        layer = layers[0];
    }
    const line = window.posBuildCartLineFromButton(btn, layer, catalog);
    return window.posAddCartLine(cart, line, 1);
};
</script>
@endonce
