@php
    $editing = isset($resv) && $resv !== null;
    $inp = 'width:100%;padding:8px 11px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;outline:none;transition:border-color .15s;';
    $lbl = 'display:block;font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;';
@endphp

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

    {{-- Customer name (full width) --}}
    <div style="grid-column:1/-1;">
        <label style="{{ $lbl }}">Customer Name <span style="color:#ef4444;">*</span></label>
        <input type="text" name="customer_name"
               value="{{ old('customer_name', $resv?->customer_name) }}"
               required maxlength="255" placeholder="Full name"
               style="{{ $inp }}">
    </div>

    {{-- Phone --}}
    <div>
        <label style="{{ $lbl }}">Phone</label>
        <input type="text" name="customer_phone"
               value="{{ old('customer_phone', $resv?->customer_phone) }}"
               maxlength="30" placeholder="+1 555 000 0000"
               style="{{ $inp }}">
    </div>

    {{-- Email --}}
    <div>
        <label style="{{ $lbl }}">Email</label>
        <input type="email" name="customer_email"
               value="{{ old('customer_email', $resv?->customer_email) }}"
               maxlength="255" placeholder="guest@example.com"
               style="{{ $inp }}">
    </div>

    {{-- Date & time --}}
    <div>
        <label style="{{ $lbl }}">Date &amp; Time <span style="color:#ef4444;">*</span></label>
        <input type="datetime-local" name="reserved_at"
               value="{{ old('reserved_at', $resv ? $resv->reserved_at->format('Y-m-d\TH:i') : '') }}"
               required style="{{ $inp }}">
    </div>

    {{-- Party size --}}
    <div>
        <label style="{{ $lbl }}">Party Size <span style="color:#ef4444;">*</span></label>
        <input type="number" name="party_size"
               value="{{ old('party_size', $resv?->party_size ?? 2) }}"
               required min="1" max="500" placeholder="2"
               style="{{ $inp }}">
    </div>

    {{-- Duration --}}
    <div>
        <label style="{{ $lbl }}">Duration (minutes)</label>
        <input type="number" name="duration_minutes"
               value="{{ old('duration_minutes', $resv?->duration_minutes ?? 90) }}"
               min="15" max="480" placeholder="90"
               style="{{ $inp }}">
    </div>

    {{-- Table --}}
    <div>
        <label style="{{ $lbl }}">Table</label>
        <select name="table_id" style="{{ $inp }}">
            <option value="">— No specific table —</option>
            @foreach($tables as $tbl)
                <option value="{{ $tbl->id }}"
                        {{ old('table_id', $resv?->table_id) == $tbl->id ? 'selected' : '' }}>
                    {{ $tbl->name }} ({{ $tbl->capacity }} seats)
                </option>
            @endforeach
        </select>
    </div>

    @if($editing)
    {{-- Status (edit only) --}}
    <div>
        <label style="{{ $lbl }}">Status</label>
        <select name="status" style="{{ $inp }}">
            @foreach(['pending', 'confirmed', 'seated', 'completed', 'cancelled'] as $st)
                <option value="{{ $st }}"
                        {{ old('status', $resv?->status) === $st ? 'selected' : '' }}>
                    {{ ucfirst($st) }}
                </option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- Notes (full width) --}}
    <div style="grid-column:1/-1;">
        <label style="{{ $lbl }}">Notes</label>
        <textarea name="notes" rows="2" maxlength="1000"
                  placeholder="Special requests, allergies, occasion, accessibility needs…"
                  style="{{ $inp }}resize:vertical;">{{ old('notes', $resv?->notes) }}</textarea>
    </div>

</div>
