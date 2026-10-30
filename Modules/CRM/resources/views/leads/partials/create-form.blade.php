@php
    use Modules\CRM\Models\Lead;
    $editing = isset($lead) && $lead instanceof Lead;
    $assignableUsers = $assignableUsers ?? collect();
    $stageOptions = $stageOptions ?? collect();
    $customFields = $customFields ?? collect();
    $leadForm = $leadForm ?? null;
    $lostReasons = $lostReasons ?? collect();
    $divisions = $divisions ?? collect();
    $defaultStageId = $editing ? $lead->stage_id : optional(
        $stageOptions->first(fn ($s) => $s->system_key === \Modules\CRM\Models\LeadStage::STATUS_NEW)
        ?? $stageOptions->first(fn ($s) => !$s->is_won && !$s->is_lost && !$s->isReserved())
    )->id;
    $productSearchUrl = Route::has('sales.line-items.search') ? route('sales.line-items.search', ['type' => 'product']) : null;
    $selectedProducts = old('products') !== null
        ? collect(old('products'))->map(fn ($p) => ['id' => (int) ($p['product_id'] ?? 0), 'name' => $p['name'] ?? ('#' . ($p['product_id'] ?? '')), 'quantity' => $p['quantity'] ?? null])->values()
        : ($editing ? $lead->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'quantity' => $p->pivot->quantity !== null ? (float) $p->pivot->quantity : null])->values() : collect());
@endphp

@if($errors->any())
    <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('crm.leads.update', $lead) : route('crm.projects.leads.store', $project) }}"
      class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="pcat-field">
        <label for="ld-name">Name <span style="color:#ef4444;">*</span></label>
        <input id="ld-name" type="text" name="name" maxlength="150" required
               value="{{ old('name', $editing ? $lead->name : '') }}">
        @error('name')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="ld-stage">Stage</label>
        <select id="ld-stage" name="stage_id">
            @foreach($stageOptions as $opt)
                {{-- Pending approval / quotation sent are set by the quotation workflow only --}}
                <option value="{{ $opt->id }}"
                        data-lost="{{ $opt->isLostStage() ? 1 : 0 }}"
                        data-reason="{{ $opt->requires_reason && !$opt->isLostStage() ? 1 : 0 }}"
                        @disabled($opt->isReserved() && (int) $defaultStageId !== $opt->id)
                        @selected((int) old('stage_id', $defaultStageId) === $opt->id)>{{ $opt->name }}{{ $opt->isReserved() ? ' (automatic)' : '' }}</option>
            @endforeach
        </select>
        @error('stage_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        <div class="muted" style="font-size:11px;margin-top:4px;">
            Need a different stage? <a href="{{ route('crm.projects.stages.index', $project) }}" class="pcat-link">Manage stages</a>.
        </div>
    </div>

    {{-- Shown when the picked stage is a lost stage (reason required) --}}
    <div class="pcat-field" data-ld-lost-fields hidden>
        <label for="ld-lost-reason">Lost reason <span style="color:#ef4444;">*</span></label>
        <select id="ld-lost-reason" name="lost_reason_id">
            <option value="">— Pick a reason —</option>
            @foreach($lostReasons as $r)
                <option value="{{ $r->id }}" @selected((int) old('lost_reason_id', $editing ? $lead->lost_reason_id : 0) === $r->id)>{{ $r->label }}</option>
            @endforeach
        </select>
        @error('lost_reason_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        <input type="text" name="lost_reason" maxlength="255" placeholder="Note (optional)" style="margin-top:6px;"
               value="{{ old('lost_reason', $editing ? $lead->lost_reason : '') }}">
    </div>

    {{-- Shown when the picked stage asks for a reason --}}
    <div class="pcat-field" data-ld-reason-fields hidden>
        <label for="ld-stage-reason">Reason for this stage <span style="color:#ef4444;">*</span></label>
        <input id="ld-stage-reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}">
        @error('reason')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    {{--
        These fields (and their labels, placeholders, help text, required flags, and
        order) come straight from the project's lead form builder — the same fields a
        public visitor would fill out. Rendered flat, in path order: the builder's
        row/column layout is a public-page/preview concern only, not used here. Name
        is skipped since it already has its own fixed input above.
    --}}
    @if($leadForm)
        @foreach($leadForm->fieldBlocksWithPaths() as $path => $block)
            @continue(($block['field'] ?? '') === 'name')
            @include('crm::leads.partials.lead-form-field', ['path' => $path, 'block' => $block, 'customFields' => $customFields, 'lead' => $editing ? $lead : null])
        @endforeach
    @endif

    {{--
        Pipeline-management fields: staff-only concepts a public form can't express.
        Hidden on quick "Add lead" to keep that form short — set them later via Edit.
    --}}
    @if($editing)
        <div class="pcat-field">
            <label for="ld-value">Estimated value</label>
            <input id="ld-value" type="number" name="estimated_value" min="0" step="0.01" inputmode="decimal"
                   value="{{ old('estimated_value', $lead->estimated_value) }}">
            @error('estimated_value')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-close">Expected close date</label>
            <input id="ld-close" type="date" name="expected_close_date"
                   value="{{ old('expected_close_date', $lead->expected_close_date ? $lead->expected_close_date->format('Y-m-d') : '') }}">
            @error('expected_close_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-source">Source</label>
            <select id="ld-source" name="source">
                <option value="">— Unknown —</option>
                @foreach(\Modules\CRM\Models\Lead::SOURCES as $key => [$label])
                    <option value="{{ $key }}" @selected(old('source', $editing ? $lead->source : 'manual') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('source')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-assignee">Assigned to</label>
            <select id="ld-assignee" name="assigned_to" @disabled(! ($canAssign ?? true)) @if(! ($canAssign ?? true)) title="Only the department head can assign this lead." @endif>
                <option value="">— Unassigned —</option>
                @foreach($assignableUsers as $u)
                    <option value="{{ $u->id }}" @selected(old('assigned_to', $lead->assigned_to) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('assigned_to')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field">
            <label for="ld-score">Score</label>
            <select id="ld-score" name="score">
                <option value="">— Not rated —</option>
                @foreach(\Modules\CRM\Models\Lead::SCORES as $key => [$label])
                    <option value="{{ $key }}" @selected(old('score', $lead->score) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('score')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        @if($divisions->isNotEmpty())
            <div class="pcat-field">
                <label for="ld-division">Division</label>
                <select id="ld-division" name="division_id" @disabled(! ($canAssign ?? true))>
                    <option value="">— No division —</option>
                    @foreach($divisions as $d)
                        <option value="{{ $d->id }}" @selected((int) old('division_id', $lead->division_id) === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
                @error('division_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </div>
        @endif

        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="ld-product-search">Products of interest</label>
            <input type="hidden" name="products_present" value="1">
            <div id="ld-products" class="ld-products">
                @foreach($selectedProducts as $i => $p)
                    <span class="ld-product-chip" data-product-id="{{ $p['id'] }}">
                        <input type="hidden" name="products[{{ $i }}][product_id]" value="{{ $p['id'] }}">
                        <input type="hidden" name="products[{{ $i }}][name]" value="{{ $p['name'] }}">
                        {{ $p['name'] }}
                        <input type="number" name="products[{{ $i }}][quantity]" min="0" step="0.01" placeholder="Qty" value="{{ $p['quantity'] }}" title="Quantity (optional)">
                        <button type="button" data-ld-product-remove title="Remove">&times;</button>
                    </span>
                @endforeach
            </div>
            @if($productSearchUrl)
                <div style="position:relative;">
                    <input id="ld-product-search" type="search" autocomplete="off" placeholder="Search products by name or SKU…" data-url="{{ $productSearchUrl }}">
                    <div id="ld-product-results" class="ld-product-results" hidden></div>
                </div>
            @endif
            @error('products')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            <textarea name="product_interest_note" maxlength="2000" rows="2" style="margin-top:6px;"
                      placeholder="Anything else they are interested in (free text)…">{{ old('product_interest_note', $lead->product_interest_note) }}</textarea>
        </div>
    @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="ld-notes">Notes</label>
        <textarea id="ld-notes" name="notes" maxlength="5000"
                  placeholder="Background, requirements, next steps…">{{ old('notes', $editing ? $lead->notes : '') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($editing)
            <a href="{{ route('crm.leads.show', $lead) }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">
            {{ $editing ? 'Save changes' : 'Add lead' }}
        </button>
    </div>
</form>

@once('crm-lead-form-script')
<style>
.ld-products{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;}
.ld-products:empty{display:none;}
.ld-product-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);border-radius:999px;padding:3px 6px 3px 10px;font-size:12px;background:var(--card);}
.ld-product-chip input[type=number]{width:62px;padding:2px 6px;font-size:12px;}
.ld-product-chip button{border:none;background:transparent;color:var(--muted);cursor:pointer;font-size:15px;line-height:1;}
.ld-product-results{position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--card);border:1px solid var(--border);border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.18);max-height:240px;overflow-y:auto;}
.ld-product-results button{display:block;width:100%;text-align:left;background:transparent;border:none;padding:7px 10px;font-size:12.5px;color:var(--text);cursor:pointer;}
.ld-product-results button:hover{background:color-mix(in srgb,var(--muted) 14%,transparent);}
</style>
<script>
(function () {
    // Lost reason / stage reason fields follow the picked stage
    var stage = document.getElementById('ld-stage');
    var lostBox = document.querySelector('[data-ld-lost-fields]');
    var reasonBox = document.querySelector('[data-ld-reason-fields]');
    var initialStage = stage ? stage.value : null;
    function syncStage() {
        if (!stage) return;
        var opt = stage.options[stage.selectedIndex];
        var moved = stage.value !== initialStage;
        var lost = opt && opt.dataset.lost === '1';
        if (lostBox) lostBox.hidden = !lost;
        if (reasonBox) reasonBox.hidden = !(opt && opt.dataset.reason === '1' && moved);
    }
    stage && stage.addEventListener('change', syncStage);
    syncStage();

    // Products of interest: search the catalog, add chips
    var search = document.getElementById('ld-product-search');
    var results = document.getElementById('ld-product-results');
    var list = document.getElementById('ld-products');
    if (!list) return;
    var nextIndex = list.children.length;

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ld-product-remove]');
        if (btn) btn.closest('.ld-product-chip').remove();
    });

    if (!search || !results) return;
    var timer = null;
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function addProduct(id, name) {
        if (list.querySelector('[data-product-id="' + id + '"]')) return;
        var i = nextIndex++;
        var chip = document.createElement('span');
        chip.className = 'ld-product-chip';
        chip.dataset.productId = id;
        chip.innerHTML = '<input type="hidden" name="products[' + i + '][product_id]" value="' + esc(id) + '">'
            + '<input type="hidden" name="products[' + i + '][name]" value="' + esc(name) + '">'
            + esc(name)
            + ' <input type="number" name="products[' + i + '][quantity]" min="0" step="0.01" placeholder="Qty" title="Quantity (optional)">'
            + '<button type="button" data-ld-product-remove title="Remove">&times;</button>';
        list.appendChild(chip);
    }
    search.addEventListener('input', function () {
        clearTimeout(timer);
        var q = search.value.trim();
        if (q.length < 1) { results.hidden = true; return; }
        timer = setTimeout(function () {
            fetch(search.dataset.url + '&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (rows) {
                    var items = (Array.isArray(rows) ? rows : (rows.data || rows.products || [])).filter(function (r) { return !r.type || r.type === 'product'; });
                    results.innerHTML = items.length
                        ? items.map(function (p) { return '<button type="button" data-id="' + esc(p.id) + '" data-name="' + esc(p.name) + '">' + esc(p.name) + (p.sku ? ' <span class="muted">· ' + esc(p.sku) + '</span>' : '') + '</button>'; }).join('')
                        : '<div class="muted" style="padding:8px 10px;font-size:12px;">No products found.</div>';
                    results.hidden = false;
                })
                .catch(function () { results.hidden = true; });
        }, 200);
    });
    results.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-id]');
        if (!btn) return;
        addProduct(btn.dataset.id, btn.dataset.name);
        search.value = '';
        results.hidden = true;
    });
    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== search) results.hidden = true;
    });
})();
</script>
@endonce
