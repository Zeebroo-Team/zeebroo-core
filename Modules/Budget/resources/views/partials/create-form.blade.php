<form method="post" action="{{ route('budget.store') }}">
    @csrf
    <div class="bud-field">
        <label for="bud-name">Budget name</label>
        <input type="text" name="name" id="bud-name" maxlength="255" required value="{{ old('name') }}" placeholder="e.g. FY2026 Operating Budget">
    </div>
    <div class="bud-field">
        <label for="bud-type">Type</label>
        <select name="type" id="bud-type" required>
            @foreach($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', 'monthly') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="bud-field">
        <label for="bud-start-date">Start date</label>
        <input type="date" name="start_date" id="bud-start-date" required value="{{ old('start_date', now()->toDateString()) }}">
    </div>
    <p style="margin:0 0 12px;font-size:11px;color:var(--muted);line-height:1.4;">The end date is set automatically (one month or one year from the start date). Category allocations start at zero — set them on the budget's page.</p>
    <button type="submit" class="bud-btn--primary" style="width:100%;justify-content:center;"><i class="fa fa-plus"></i> Create budget</button>
</form>
