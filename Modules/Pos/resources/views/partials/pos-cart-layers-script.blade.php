@once
<script>
window.posCartKey = window.posCartKey || function (productId, layerId) {
    return String(productId) + ':' + (layerId != null && layerId !== '' ? String(layerId) : 'fifo');
};

// Resolves a configured warranty duration string (e.g. "1 Year", "30 Days", "Lifetime")
// into a { type, date } pair, mirroring the desktop app's warranty resolver.
window.posResolveWarrantyFromDuration = function (duration) {
    var d = String(duration || '').trim();
    if (!d || d.toLowerCase() === 'lifetime') return { type: 'lifetime', date: null };
    var m = d.match(/^(\d+(?:\.\d+)?)\s*(day|days|week|weeks|month|months|year|years)$/i);
    if (m) {
        var n = parseFloat(m[1]);
        var unit = m[2].toLowerCase();
        var exp = new Date();
        if (unit.indexOf('day') === 0) exp.setDate(exp.getDate() + Math.round(n));
        else if (unit.indexOf('week') === 0) exp.setDate(exp.getDate() + Math.round(n * 7));
        else if (unit.indexOf('month') === 0) exp.setMonth(exp.getMonth() + Math.round(n));
        else if (unit.indexOf('year') === 0) exp.setFullYear(exp.getFullYear() + Math.round(n));
        return { type: 'date', date: exp.toISOString().split('T')[0] };
    }
    return { type: 'lifetime', date: null };
};

// Resolves the warranty {warrantyType, warrantyDate} for a product being added to
// the cart — auto-computed from a configured duration, or prompted via the shared
// service/warranty picker modal when no duration is preset. Returns null if the
// product has no warranty configured, or if the user cancels the prompt.
window.posResolveProductWarranty = async function (catalogEntry, productName) {
    if (!catalogEntry || !catalogEntry.has_warranty) return {};
    if (catalogEntry.warranty_duration) {
        var resolved = window.posResolveWarrantyFromDuration(catalogEntry.warranty_duration);
        return { warrantyType: resolved.type, warrantyDate: resolved.date };
    }
    if (typeof window.posPickServiceDetails !== 'function') return {};
    var result = await window.posPickServiceDetails({
        serviceName: productName || '',
        hasWarranty: true,
        customRequirementEnabled: false,
        customRequirementFields: '[]',
    });
    if (!result) return null; // cancelled
    return { warrantyType: result.warrantyType, warrantyDate: result.warrantyDate };
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

window.posLayersDifferInPrice = function (layers) {
    if (!layers || layers.length < 2) return false;
    var first = Number(layers[0].unit_sell_price || 0).toFixed(2);
    for (var i = 1; i < layers.length; i++) {
        if (Number(layers[i].unit_sell_price || 0).toFixed(2) !== first) return true;
    }
    return false;
};

window.posBuildCartLineFromButton = function (btn, layer, catalog, useFifo) {
    var id = parseInt(btn.dataset.productId, 10);
    var picked = useFifo ? null : (layer || null);
    var layerId = picked ? parseInt(picked.id, 10) : null;
    var stock = picked ? parseFloat(picked.quantity_remaining) : parseFloat(btn.dataset.stock) || 0;
    var unitPrice = picked ? parseFloat(picked.unit_sell_price) : parseFloat(btn.dataset.unitPrice) || 0;

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
    let useFifo = false;

    if (layers.length > 1) {
        if (window.posLayersDifferInPrice(layers)) {
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
        } else {
            useFifo = true;
        }
    } else if (layers.length === 1) {
        layer = layers[0];
    }

    const line = window.posBuildCartLineFromButton(btn, layer, catalog, useFifo);
    return window.posAddCartLine(cart, line, 1);
};

window.posCartKeyWithUnit = function (productId, layerId, unitId) {
    var base = String(productId) + ':' + (layerId != null ? String(layerId) : 'fifo');
    return base + ':u' + (unitId != null ? String(unitId) : '0');
};

window.posAddProductWithUnit = async function (btn, cart, catalog, currencySuffix) {
    if (!btn || btn.disabled) return false;
    var productId = parseInt(btn.dataset.productId, 10);
    var catalogEntry = catalog[productId] || {};
    var sellingUnits = catalogEntry.selling_units || window.posParseProductLayers(btn.getAttribute('data-selling-units')) || [];
    var layers = window.posLayersFromButton(btn, catalog);

    var chosenUnit = null;
    if (sellingUnits.length > 0) {
        chosenUnit = await window.posPickSellingUnit(
            { id: productId, name: btn.dataset.productName || '', unit: catalogEntry.unit || '', unit_sell_price: parseFloat(btn.dataset.unitPrice) || 0, stock_quantity: parseFloat(btn.dataset.stock) || 0 },
            sellingUnits,
            currencySuffix
        );
        if (chosenUnit === null) return false; // cancelled
    }

    // Now pick the stock layer (if multiple layers exist with different prices)
    var layer = null;
    var useFifo = false;
    if (layers.length > 1) {
        if (window.posLayersDifferInPrice(layers)) {
            layer = await window.posPickStockLayer({ id: productId, name: btn.dataset.productName || '' }, layers);
            if (!layer) return false;
        } else {
            useFifo = true;
        }
    } else if (layers.length === 1) {
        layer = layers[0];
    }

    // Build cart line
    var layerId = (!useFifo && layer) ? parseInt(layer.id, 10) : null;
    var unitId  = chosenUnit && chosenUnit.id != null ? parseInt(chosenUnit.id, 10) : null;
    var cartKey = window.posCartKeyWithUnit(productId, layerId, unitId);

    // Calculate price and stock
    var baseUnitPrice = (!useFifo && layer) ? parseFloat(layer.unit_sell_price) : parseFloat(btn.dataset.unitPrice) || 0;
    var unitPrice, factor, unitLabel, stockInUnits;

    if (chosenUnit && chosenUnit.id != null) {
        factor        = parseFloat(chosenUnit.conversion_factor) || 1.0;
        unitLabel     = chosenUnit.label;
        unitPrice     = chosenUnit.display_price != null ? parseFloat(chosenUnit.display_price) : (baseUnitPrice * factor);
        stockInUnits  = chosenUnit.stock_in_units != null ? parseFloat(chosenUnit.stock_in_units) : Math.floor((parseFloat(btn.dataset.stock) || 0) / factor);
    } else {
        // custom / no selling unit
        factor        = 1.0;
        unitLabel     = null;
        unitPrice     = baseUnitPrice;
        stockInUnits  = (!useFifo && layer) ? parseFloat(layer.quantity_remaining) : parseFloat(btn.dataset.stock) || 0;
    }

    var line = {
        cartKey:      cartKey,
        id:           productId,
        layerId:      layerId,
        layerLabel:   layer ? (layer.label || ('Batch #' + layer.id)) : '',
        sellingUnitId:    unitId,
        sellingUnitLabel: unitLabel,
        sellingUnitFactor: factor,
        name:         btn.dataset.productName || 'Product',
        sku:          btn.dataset.productSku || '',
        unitPrice:    unitPrice,
        quantity:     0,
        stock:        stockInUnits,
    };

    if (catalogEntry.has_warranty) {
        var existingLine = cart.get(cartKey);
        if (!existingLine) {
            var warranty = await window.posResolveProductWarranty(catalogEntry, btn.dataset.productName);
            if (warranty === null) return false; // warranty prompt cancelled
            line.warrantyType = warranty.warrantyType || null;
            line.warrantyDate = warranty.warrantyDate || null;
        }
    }

    if (catalogEntry.is_subscription) {
        line.isSubscription = true;
        line.subscriptionPeriod = catalogEntry.subscription_recurring_period || null;
    }

    return window.posAddCartLine(cart, line, 1);
};
</script>
@endonce
