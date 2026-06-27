@extends('theme::layouts.app', ['title' => 'Ingredients', 'heading' => 'Restaurant'])

@section('content')
<style>
/* ── Layout ───────────────────────────────────────── */
.ing-wrap { max-width:100%; }

/* ── Stats row ───────────────────────────────────── */
.ing-stats { display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px; }
.ing-stat   { flex:1;min-width:140px;background:var(--bg);border:1px solid var(--border);
               border-radius:12px;padding:14px 18px; }
.ing-stat__label { font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px; }
.ing-stat__val   { font-size:22px;font-weight:900;margin-top:4px;color:var(--text); }
.ing-stat--warn .ing-stat__val { color:#d97706; }
.ing-stat--ok   .ing-stat__val { color:#16a34a; }

/* ── Toolbar ─────────────────────────────────────── */
.ing-toolbar { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap; }
.ing-toolbar__title { font-size:15px;font-weight:900; }

/* ── List ────────────────────────────────────────── */
.ing-list { background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.ing-row  { display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid var(--border); }
.ing-row:last-child { border-bottom:none; }
.ing-row:hover { background:color-mix(in srgb,var(--primary) 3%,var(--bg)); }

/* left: name + meta */
.ing-row__info { flex:1;min-width:0; }
.ing-row__name { font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.ing-row__meta { font-size:11px;color:var(--muted);margin-top:2px; }

/* center: available stock pill */
.ing-row__stock { display:flex;align-items:center;gap:5px;
                   padding:6px 14px;border-radius:10px;border:1.5px solid;
                   white-space:nowrap; }
.ing-row__stock--ok  { border-color:#86efac;background:#f0fdf4; }
.ing-row__stock--low { border-color:#fcd34d;background:#fffbeb; }
.ing-row__stock-val  { font-size:15px;font-weight:900; }
.ing-row__stock-val--ok  { color:#15803d; }
.ing-row__stock-val--low { color:#b45309; }
.ing-row__stock-unit { font-size:11px;font-weight:700;text-transform:uppercase;color:var(--muted); }

/* right: status badge */
.ing-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;
              font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.4px; }
.ing-badge--low  { background:#fef3c7;color:#92400e; }
.ing-badge--ok   { background:#dcfce7;color:#15803d; }

/* actions */
.ing-actions { display:flex;align-items:center;gap:5px; }
.ing-btn { padding:6px 10px;border-radius:8px;font-size:12px;font-weight:700;border:1.5px solid;cursor:pointer;
            display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s; }
.ing-btn--primary { background:var(--primary);color:#fff;border-color:var(--primary); }
.ing-btn--primary:hover { opacity:.88; }
.ing-btn--outline { background:transparent;color:var(--text);border-color:var(--border); }
.ing-btn--outline:hover { border-color:var(--primary);color:var(--primary); }
.ing-btn--danger { background:transparent;color:#ef4444;border-color:#fca5a5; }
.ing-btn--danger:hover { background:#fef2f2; }
.ing-btn--green  { background:transparent;color:#16a34a;border-color:#86efac; }
.ing-btn--green:hover { background:#f0fdf4; }
.ing-btn--amber  { background:transparent;color:#d97706;border-color:#fcd34d; }
.ing-btn--amber:hover { background:#fffbeb; }

/* ── Empty state ─────────────────────────────────── */
.ing-empty__icon  { font-size:40px;color:var(--muted);margin-bottom:14px; }
.ing-empty__title { font-size:16px;font-weight:800;margin-bottom:6px; }
.ing-empty__sub   { font-size:13px;color:var(--muted);margin-bottom:20px; }

/* ── Modal shared ────────────────────────────────── */
.ing-modal { display:none;position:fixed;inset:0;z-index:900;align-items:center;justify-content:center;
              background:rgba(0,0,0,.45); }
.ing-modal.open { display:flex; }
.ing-modal__box { background:var(--bg);border-radius:16px;width:100%;max-width:480px;
                   border:1px solid var(--border);box-shadow:0 24px 60px rgba(0,0,0,.18); }
.ing-modal__head { padding:18px 22px 14px;border-bottom:1px solid var(--border);
                    display:flex;align-items:center;justify-content:space-between; }
.ing-modal__head h3 { margin:0;font-size:15px;font-weight:900; }
.ing-modal__close { background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px;padding:2px 6px; }
.ing-modal__close:hover { color:var(--text); }
.ing-modal__body { padding:20px 22px; }
.ing-modal__foot { padding:14px 22px;border-top:1px solid var(--border);
                    display:flex;align-items:center;justify-content:flex-end;gap:8px; }
.ing-field { margin-bottom:14px; }
.ing-field label { display:block;font-size:11px;font-weight:800;text-transform:uppercase;
                    letter-spacing:.3px;margin-bottom:5px;color:var(--text); }
.ing-field input,.ing-field select,.ing-field textarea {
  width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--border);
  background:var(--bg);color:var(--text);font-size:13px;box-sizing:border-box;outline:none; }
.ing-field input:focus,.ing-field select:focus,.ing-field textarea:focus {
  border-color:var(--primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--primary) 10%,transparent); }
.ing-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
</style>

<div class="ing-wrap">
  @include('restaurant::partials.nav')

  @if(session('status'))
    <div class="pcat-banner pcat-banner--ok" style="margin-bottom:16px;">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="pcat-banner pcat-banner--err" style="margin-bottom:16px;">{{ $errors->first() }}</div>
  @endif

  {{-- Stats --}}
  @php
    $total   = $ingredients->count();
    $lowCnt  = $ingredients->filter(fn($i) => $i->isLowStock())->count();
    $okCnt   = $total - $lowCnt;
  @endphp
  <div class="ing-stats">
    <div class="ing-stat">
      <div class="ing-stat__label">Total Ingredients</div>
      <div class="ing-stat__val">{{ $total }}</div>
    </div>
    <div class="ing-stat ing-stat--ok">
      <div class="ing-stat__label">Well Stocked</div>
      <div class="ing-stat__val">{{ $okCnt }}</div>
    </div>
    <div class="ing-stat ing-stat--warn">
      <div class="ing-stat__label">Low Stock</div>
      <div class="ing-stat__val">{{ $lowCnt }}</div>
    </div>
  </div>

  {{-- Toolbar --}}
  <div class="ing-toolbar">
    <span class="ing-toolbar__title"><i class="fa fa-flask" style="margin-right:7px;color:var(--primary);"></i>All Ingredients</span>
    <button class="ing-btn ing-btn--primary" onclick="openIngModal('add')">
      <i class="fa fa-plus"></i> Add Ingredient
    </button>
  </div>

  {{-- List --}}
  <div class="ing-list">
    @if($ingredients->isEmpty())
      <div style="text-align:center;padding:56px 24px;">
        <div class="ing-empty__icon"><i class="fa fa-flask"></i></div>
        <div class="ing-empty__title">No ingredients yet</div>
        <div class="ing-empty__sub">Add your first ingredient to start tracking stock.</div>
        <button class="ing-btn ing-btn--primary" onclick="openIngModal('add')">
          <i class="fa fa-plus"></i> Add Ingredient
        </button>
      </div>
    @else
      @foreach($ingredients as $ing)
      @php
        $low  = $ing->isLowStock();
        $qty  = (float) $ing->quantity;
        $disp = $qty == floor($qty) ? number_format($qty, 0) : rtrim(number_format($qty, 3), '0');
      @endphp
      <div class="ing-row">

        {{-- Name + meta --}}
        <div class="ing-row__info">
          <div class="ing-row__name">{{ $ing->name }}</div>
          <div class="ing-row__meta">
            {{ strtoupper($ing->unit) }}
            @if($ing->low_stock_threshold !== null)
              &nbsp;&middot;&nbsp;Alert at {{ rtrim(number_format((float)$ing->low_stock_threshold, 3), '0') }} {{ $ing->unit }}
            @endif
          </div>
        </div>

        {{-- Available stock pill --}}
        <div class="ing-row__stock {{ $low ? 'ing-row__stock--low' : 'ing-row__stock--ok' }}">
          <span class="ing-row__stock-val {{ $low ? 'ing-row__stock-val--low' : 'ing-row__stock-val--ok' }}">
            {{ $disp }}
          </span>
          <span class="ing-row__stock-unit">{{ $ing->unit }}</span>
        </div>

        {{-- Status badge --}}
        @if($low)
          <span class="ing-badge ing-badge--low"><i class="fa fa-triangle-exclamation"></i> Low</span>
        @else
          <span class="ing-badge ing-badge--ok"><i class="fa fa-check"></i> OK</span>
        @endif

        {{-- Actions --}}
        <div class="ing-actions">
          <button class="ing-btn ing-btn--green"
            onclick="openStockInModal({{ $ing->id }}, '{{ addslashes($ing->name) }}', '{{ $ing->unit }}')">
            <i class="fa fa-arrow-up"></i> Stock In
          </button>
          <button class="ing-btn ing-btn--amber" title="Record Waste"
            onclick="openWasteModal({{ $ing->id }}, '{{ addslashes($ing->name) }}', '{{ $ing->unit }}')">
            <i class="fa fa-trash-can"></i>
          </button>
          <a href="{{ route('restaurant.ingredients.transactions', $ing) }}"
             class="ing-btn ing-btn--outline" title="History">
            <i class="fa fa-clock-rotate-left"></i>
          </a>
          <button class="ing-btn ing-btn--outline" title="Edit"
            onclick="openIngModal('edit', {{ $ing->id }}, '{{ addslashes($ing->name) }}', '{{ $ing->unit }}', '{{ $ing->low_stock_threshold }}', '{{ $ing->cost_per_unit }}')">
            <i class="fa fa-pen"></i>
          </button>
          <form method="POST" action="{{ route('restaurant.ingredients.destroy', $ing) }}"
                onsubmit="return confirm('Delete {{ addslashes($ing->name) }}?')" style="display:inline;">
            @csrf @method('DELETE')
            <button type="submit" class="ing-btn ing-btn--danger" title="Delete">
              <i class="fa fa-trash"></i>
            </button>
          </form>
        </div>

      </div>
      @endforeach
    @endif
  </div>
</div>

{{-- ── Add / Edit Ingredient Modal ──────────────────────────── --}}
<div class="ing-modal" id="ingModal">
  <div class="ing-modal__box">
    <div class="ing-modal__head">
      <h3 id="ingModalTitle">Add Ingredient</h3>
      <button class="ing-modal__close" onclick="closeIngModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <form id="ingModalForm" method="POST" action="{{ route('restaurant.ingredients.store') }}">
      @csrf
      <span id="ingMethodField"></span>
      <div class="ing-modal__body">
        <div class="ing-field">
          <label>Name <span style="color:#ef4444;">*</span></label>
          <input type="text" name="name" id="ingName" required maxlength="255" placeholder="e.g. Tomato">
        </div>
        <div class="ing-grid2">
          <div class="ing-field">
            <label>Unit <span style="color:#ef4444;">*</span></label>
            <select name="unit" id="ingUnit" required>
              @foreach($units as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="ing-field" id="ingQtyWrap">
            <label>Opening Stock</label>
            <input type="number" name="quantity" id="ingQty" min="0" step="any" value="0">
          </div>
        </div>
        <div class="ing-grid2">
          <div class="ing-field">
            <label>Low-Stock Alert</label>
            <input type="number" name="low_stock_threshold" id="ingThreshold" min="0" step="any" placeholder="optional">
          </div>
          <div class="ing-field">
            <label>Cost Per Unit</label>
            <input type="number" name="cost_per_unit" id="ingCost" min="0" step="any" placeholder="optional">
          </div>
        </div>
      </div>
      <div class="ing-modal__foot">
        <button type="button" class="ing-btn ing-btn--outline" onclick="closeIngModal()">Cancel</button>
        <button type="submit" class="ing-btn ing-btn--primary"><i class="fa fa-floppy-disk"></i> Save</button>
      </div>
    </form>
  </div>
</div>

{{-- ── Stock In Modal ─────────────────────────────────────── --}}
<div class="ing-modal" id="stockInModal">
  <div class="ing-modal__box">
    <div class="ing-modal__head">
      <h3>Add Stock — <span id="siIngName"></span></h3>
      <button class="ing-modal__close" onclick="document.getElementById('stockInModal').classList.remove('open')"><i class="fa fa-xmark"></i></button>
    </div>
    <form id="stockInForm" method="POST" action="">
      @csrf
      <div class="ing-modal__body">
        <div class="ing-field">
          <label>Quantity to Add (<span id="siUnit"></span>) <span style="color:#ef4444;">*</span></label>
          <input type="number" name="quantity" id="siQty" min="0" step="any" required placeholder="e.g. 0.5">
        </div>
        <div class="ing-field">
          <label>Notes</label>
          <input type="text" name="notes" placeholder="e.g. Purchase from supplier" maxlength="500">
        </div>
      </div>
      <div class="ing-modal__foot">
        <button type="button" class="ing-btn ing-btn--outline"
          onclick="document.getElementById('stockInModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="ing-btn ing-btn--green"><i class="fa fa-arrow-up"></i> Add Stock</button>
      </div>
    </form>
  </div>
</div>

{{-- ── Waste Modal ─────────────────────────────────────────── --}}
<div class="ing-modal" id="wasteModal">
  <div class="ing-modal__box">
    <div class="ing-modal__head">
      <h3>Record Waste — <span id="wasteIngName"></span></h3>
      <button class="ing-modal__close" onclick="document.getElementById('wasteModal').classList.remove('open')"><i class="fa fa-xmark"></i></button>
    </div>
    <form id="wasteForm" method="POST" action="">
      @csrf
      <div class="ing-modal__body">
        <div class="ing-field">
          <label>Quantity Wasted (<span id="wasteUnit"></span>) <span style="color:#ef4444;">*</span></label>
          <input type="number" name="quantity" id="wasteQty" min="0" step="any" required placeholder="e.g. 0.5">
        </div>
        <div class="ing-field">
          <label>Notes</label>
          <input type="text" name="notes" placeholder="e.g. Expired stock" maxlength="500">
        </div>
      </div>
      <div class="ing-modal__foot">
        <button type="button" class="ing-btn ing-btn--outline"
          onclick="document.getElementById('wasteModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="ing-btn ing-btn--amber"><i class="fa fa-trash-can"></i> Record Waste</button>
      </div>
    </form>
  </div>
</div>

<script>
var _ingRoutes = {
  store:   '{{ route('restaurant.ingredients.store') }}',
  update:  '{{ url('restaurant/ingredients') }}/',
  stockIn: '{{ url('restaurant/ingredients') }}/',
  waste:   '{{ url('restaurant/ingredients') }}/',
};

function openIngModal(mode, id, name, unit, threshold, cost) {
    var form  = document.getElementById('ingModalForm');
    var title = document.getElementById('ingModalTitle');
    var meth  = document.getElementById('ingMethodField');
    var qWrap = document.getElementById('ingQtyWrap');

    if (mode === 'add') {
        title.textContent = 'Add Ingredient';
        form.action = _ingRoutes.store;
        meth.innerHTML = '';
        document.getElementById('ingName').value      = '';
        document.getElementById('ingUnit').value      = 'pcs';
        document.getElementById('ingQty').value       = '0';
        document.getElementById('ingThreshold').value = '';
        document.getElementById('ingCost').value      = '';
        qWrap.style.display = '';
    } else {
        title.textContent = 'Edit Ingredient';
        form.action = _ingRoutes.update + id;
        meth.innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('ingName').value      = name;
        document.getElementById('ingUnit').value      = unit;
        document.getElementById('ingThreshold').value = threshold || '';
        document.getElementById('ingCost').value      = cost || '';
        qWrap.style.display = 'none'; // stock change via stock-in only
    }

    document.getElementById('ingModal').classList.add('open');
}

function closeIngModal() {
    document.getElementById('ingModal').classList.remove('open');
}

function openStockInModal(id, name, unit) {
    document.getElementById('siIngName').textContent = name;
    document.getElementById('siUnit').textContent    = unit.toUpperCase();
    document.getElementById('siQty').value           = '';
    document.getElementById('stockInForm').action    = _ingRoutes.stockIn + id + '/stock-in';
    document.getElementById('stockInModal').classList.add('open');
}

function openWasteModal(id, name, unit) {
    document.getElementById('wasteIngName').textContent = name;
    document.getElementById('wasteUnit').textContent    = unit.toUpperCase();
    document.getElementById('wasteQty').value           = '';
    document.getElementById('wasteForm').action         = _ingRoutes.waste + id + '/waste';
    document.getElementById('wasteModal').classList.add('open');
}

// Close modals on backdrop click
document.querySelectorAll('.ing-modal').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === m) m.classList.remove('open');
    });
});
</script>
@endsection
