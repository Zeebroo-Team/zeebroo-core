@php
  $isEdit = isset($po) && $po !== null;
  $action = $isEdit
    ? route('restaurant.ingredients.purchases.update', $po)
    : route('restaurant.ingredients.purchases.store');
@endphp

<form method="POST" action="{{ $action }}">
  @csrf
  @if($isEdit) @method('PUT') @endif

  {{-- Header --}}
  <div class="ipo-row2" style="margin-bottom:14px;">
    <div class="ipo-field">
      <label>Supplier (optional)</label>
      <select name="supplier_id">
        <option value="">— No supplier —</option>
        @foreach($suppliers as $sup)
          <option value="{{ $sup->id }}" {{ old('supplier_id', $po?->supplier_id) == $sup->id ? 'selected' : '' }}>
            {{ $sup->name }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="ipo-field">
      <label>Status</label>
      <select name="status">
        <option value="draft" {{ old('status', $po?->status ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
        <option value="ordered" {{ old('status', $po?->status) === 'ordered' ? 'selected' : '' }}>Ordered</option>
      </select>
    </div>
  </div>

  <div class="ipo-row2" style="margin-bottom:14px;">
    <div class="ipo-field">
      <label>Purchase Date</label>
      <input type="date" name="purchase_date" value="{{ old('purchase_date', $po?->purchase_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
    </div>
    <div class="ipo-field">
      <label>Expected Delivery Date</label>
      <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $po?->expected_delivery_date?->format('Y-m-d')) }}">
    </div>
  </div>

  <div class="ipo-field" style="margin-bottom:18px;">
    <label>Notes</label>
    <textarea name="notes" rows="2" style="resize:vertical;">{{ old('notes', $po?->notes) }}</textarea>
  </div>

  {{-- Line items --}}
  <div style="margin-bottom:14px;">
    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:8px;">
      Ingredients
    </div>
    <table class="ipo-items-table" style="width:100%;">
      <thead>
        <tr>
          <th style="width:40%;">Ingredient</th>
          <th style="width:12%;">Qty</th>
          <th style="width:8%;">Unit</th>
          <th style="width:14%;">Unit Cost</th>
          <th style="width:12%;">Line Total</th>
          <th style="width:5%;"></th>
        </tr>
      </thead>
      <tbody class="ipo-items-tbody" id="ipoItemsTbody{{ $isEdit ? '_edit' : '_create' }}">
        @if($isEdit)
          @foreach($po->items as $idx => $item)
            <tr>
              <td>
                <select name="items[{{ $idx }}][ingredient_id]" onchange="ipoIngredientChange(this)" required>
                  <option value="">Select ingredient…</option>
                  @foreach($ingredients as $ing)
                    <option value="{{ $ing->id }}"
                            data-unit="{{ $ing->unit }}"
                            data-cost="{{ (float)($ing->cost_per_unit ?? 0) }}"
                            {{ $item->ingredient_id == $ing->id ? 'selected' : '' }}>
                      {{ $ing->name }} ({{ $ing->unit }})
                    </option>
                  @endforeach
                </select>
              </td>
              <td><input type="number" name="items[{{ $idx }}][quantity]" step="0.001" min="0.001"
                         value="{{ (float)$item->quantity }}" oninput="ipoCalcLine(this)" required style="width:90px;"></td>
              <td><span class="ipo-unit-label" style="font-size:11px;color:var(--muted);">{{ $item->ingredient?->unit }}</span></td>
              <td><input type="number" name="items[{{ $idx }}][unit_cost]" step="0.0001" min="0"
                         value="{{ (float)$item->unit_cost }}" oninput="ipoCalcLine(this)" required style="width:90px;"></td>
              <td class="ipo-line-total" style="font-size:12px;font-weight:700;white-space:nowrap;">
                {{ number_format((float)$item->line_total, 2) }}
              </td>
              <td><button type="button" class="ipo-btn ipo-btn--ghost" onclick="this.closest('tr').remove();ipoReindex()"><i class="fa fa-times"></i></button></td>
            </tr>
          @endforeach
        @else
          <tr>
            <td>
              <select name="items[0][ingredient_id]" onchange="ipoIngredientChange(this)" required>
                <option value="">Select ingredient…</option>
                @foreach($ingredients as $ing)
                  <option value="{{ $ing->id }}" data-unit="{{ $ing->unit }}" data-cost="{{ (float)($ing->cost_per_unit ?? 0) }}">
                    {{ $ing->name }} ({{ $ing->unit }})
                  </option>
                @endforeach
              </select>
            </td>
            <td><input type="number" name="items[0][quantity]" step="0.001" min="0.001" placeholder="0" oninput="ipoCalcLine(this)" required style="width:90px;"></td>
            <td><span class="ipo-unit-label" style="font-size:11px;color:var(--muted);"></span></td>
            <td><input type="number" name="items[0][unit_cost]" step="0.0001" min="0" placeholder="0.00" oninput="ipoCalcLine(this)" required style="width:90px;"></td>
            <td class="ipo-line-total" style="font-size:12px;font-weight:700;white-space:nowrap;">—</td>
            <td><button type="button" class="ipo-btn ipo-btn--ghost" onclick="this.closest('tr').remove();ipoReindex()"><i class="fa fa-times"></i></button></td>
          </tr>
        @endif
      </tbody>
    </table>
    <button type="button" class="ipo-add-btn"
            onclick="ipoAddLine('ipoItemsTbody{{ $isEdit ? '_edit' : '_create' }}')">
      <i class="fa fa-plus"></i> Add Ingredient
    </button>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px;">
    @if($isEdit)
      <a href="{{ route('restaurant.ingredients.purchases.show', $po) }}" class="ipo-btn ipo-btn--outline">Cancel</a>
    @endif
    <button type="submit" class="ipo-btn ipo-btn--primary">
      <i class="fa fa-save"></i> {{ $isEdit ? 'Save Changes' : 'Create Order' }}
    </button>
  </div>
</form>
