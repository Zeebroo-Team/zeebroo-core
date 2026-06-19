@php
    $isEdit   = isset($req) && $req instanceof \Modules\Service\Models\ServiceRequest;
    $action   = $isEdit ? route('service.requests.update', $req) : route('service.requests.store');
    $serviceItems = $serviceItems ?? collect();
    $customers    = $customers    ?? collect();
@endphp

<form method="POST" action="{{ $action }}" class="pcat-form-grid pcat-form-grid--2">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="sreq-title">Title / Job description <span style="color:#ef4444;">*</span></label>
        <input id="sreq-title" type="text" name="title" value="{{ old('title', $req->title ?? '') }}" maxlength="255" required placeholder="e.g. Laptop screen replacement, Garden maintenance…">
        @error('title')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="sreq-service">Service</label>
        <select id="sreq-service" name="service_item_id">
            <option value="">— None —</option>
            @foreach($serviceItems as $svc)
                <option value="{{ $svc->id }}" @selected(old('service_item_id', $req->service_item_id ?? '') == $svc->id)>{{ $svc->name }}</option>
            @endforeach
        </select>
        @error('service_item_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="sreq-customer">Customer</label>
        <select id="sreq-customer" name="customer_id">
            <option value="">— None —</option>
            @foreach($customers as $cust)
                <option value="{{ $cust->id }}" @selected(old('customer_id', $req->customer_id ?? '') == $cust->id)>{{ $cust->name }}</option>
            @endforeach
        </select>
        @error('customer_id')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="sreq-scheduled">Scheduled date &amp; time</label>
        <input id="sreq-scheduled" type="datetime-local" name="scheduled_at"
               value="{{ old('scheduled_at', isset($req) && $req->scheduled_at ? $req->scheduled_at->format('Y-m-d\TH:i') : '') }}">
        @error('scheduled_at')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="sreq-price">Price{{ isset($currency) && $currency ? ' ('.$currency.')' : '' }}</label>
        <input id="sreq-price" type="number" name="total_price" value="{{ old('total_price', $req->total_price ?? '') }}" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
        @error('total_price')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field">
        <label for="sreq-reference">Reference</label>
        <input id="sreq-reference" type="text" name="reference" value="{{ old('reference', $req->reference ?? '') }}" maxlength="120" placeholder="Job number, ticket #…">
        @error('reference')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div class="pcat-field" style="grid-column:1/-1;">
        <label for="sreq-notes">Notes</label>
        <textarea id="sreq-notes" name="notes" maxlength="5000" rows="3" placeholder="Customer instructions, special requirements…">{{ old('notes', $req->notes ?? '') }}</textarea>
        @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;">
        @if($isEdit)
            <a href="{{ route('service.requests.show', $req) }}" class="linkbtn"
               style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
        @endif
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ $isEdit ? 'Save changes' : 'Create request' }}</button>
    </div>
</form>
