@extends('theme::layouts.app', ['title' => 'Register settings', 'heading' => 'Register settings'])

@section('content')
@include('product::partials.catalog-hub-styles')
<style>
.rg-grid{display:grid;grid-template-columns:1fr;gap:16px;}
@media(min-width:900px){.rg-grid{grid-template-columns:1fr 1fr;}}
.rg-panel{border:1px solid var(--border);border-radius:11px;padding:14px;background:var(--card);}
.rg-panel h2{font-size:14px;font-weight:800;margin:0 0 10px;}
.rg-form{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:14px;}
.rg-field{display:flex;flex-direction:column;gap:4px;}
.rg-field label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.rg-input{padding:7px 10px;font-size:13px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);min-width:0;}
.rg-input:focus{outline:none;border-color:var(--primary);}
.rg-actions{display:flex;gap:6px;}
.rg-actions button{padding:5px 10px;font-size:11px;font-weight:700;border-radius:7px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;}
.rg-actions button:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
.rg-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;border:1px solid var(--border);}
.rg-badge--on{border-color:color-mix(in srgb,#22c55e 45%,var(--border));background:color-mix(in srgb,#22c55e 10%,transparent);color:#16a34a;}
.rg-badge--off{border-color:color-mix(in srgb,#94a3b8 45%,var(--border));color:var(--muted);}
</style>

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="pcat-banner" style="font-weight:600;color:#ef4444;">{{ $errors->first() }}</div>
    @endif

    <p class="pcat-muted" style="margin-top:0;">Counters and cashiers named here are also available to the Zeebroo POS desktop app for this business.</p>

    <div class="rg-grid">
        <div class="rg-panel">
            <h2><i class="fa fa-cash-register"></i> Counters</h2>

            <form method="post" action="{{ route('pos.register-settings.counters.store') }}" class="rg-form">
                @csrf
                <div class="rg-field" style="flex:1;min-width:140px;">
                    <label>Name</label>
                    <input type="text" name="name" class="rg-input" style="width:100%;box-sizing:border-box;" placeholder="Counter 1" required>
                </div>
                <div class="rg-field">
                    <label>Sort order</label>
                    <input type="number" name="sort_order" class="rg-input" style="width:80px;" min="0" value="0">
                </div>
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Add counter</button>
            </form>

            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr><th>Name</th><th>Order</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($counters as $counter)
                            <tr>
                                <td style="font-weight:600;color:var(--text);">{{ $counter->name }}</td>
                                <td class="muted">{{ $counter->sort_order }}</td>
                                <td>
                                    <span class="rg-badge {{ $counter->is_active ? 'rg-badge--on' : 'rg-badge--off' }}">{{ $counter->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <div class="rg-actions" style="justify-content:flex-end;">
                                        <form method="post" action="{{ route('pos.register-settings.counters.update', $counter) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $counter->name }}">
                                            <input type="hidden" name="sort_order" value="{{ $counter->sort_order }}">
                                            <input type="hidden" name="is_active" value="{{ $counter->is_active ? '0' : '1' }}">
                                            <button type="submit">{{ $counter->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('pos.register-settings.counters.destroy', $counter) }}" onsubmit="return confirm('Delete this counter?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted" style="text-align:center;padding:20px;">No counters yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rg-panel">
            <h2><i class="fa fa-id-badge"></i> Cashiers</h2>

            <form method="post" action="{{ route('pos.register-settings.cashiers.store') }}" class="rg-form">
                @csrf
                <div class="rg-field" style="flex:1;min-width:120px;">
                    <label>Name</label>
                    <input type="text" name="name" class="rg-input" style="width:100%;box-sizing:border-box;" required>
                </div>
                <div class="rg-field" style="flex:1;min-width:120px;">
                    <label>Username</label>
                    <input type="text" name="username" class="rg-input" style="width:100%;box-sizing:border-box;" required>
                </div>
                <div class="rg-field" style="flex:1;min-width:120px;">
                    <label>Password</label>
                    <input type="password" name="password" class="rg-input" style="width:100%;box-sizing:border-box;" required minlength="4">
                </div>
                <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:13px;">Add cashier</button>
            </form>

            <p class="pcat-muted" style="font-size:12px;">Cashier accounts are used to sign in to the counter on the Zeebroo POS desktop app — they do not sign in to this website.</p>

            <div class="pcat-table-wrap">
                <table class="pcat-table">
                    <thead>
                        <tr><th>Name</th><th>Username</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($cashiers as $cashier)
                            <tr>
                                <td style="font-weight:600;color:var(--text);">{{ $cashier->name }}</td>
                                <td class="muted">{{ $cashier->username }}</td>
                                <td>
                                    <span class="rg-badge {{ $cashier->is_active ? 'rg-badge--on' : 'rg-badge--off' }}">{{ $cashier->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <div class="rg-actions" style="justify-content:flex-end;">
                                        <form method="post" action="{{ route('pos.register-settings.cashiers.update', $cashier) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $cashier->name }}">
                                            <input type="hidden" name="username" value="{{ $cashier->username }}">
                                            <input type="hidden" name="is_active" value="{{ $cashier->is_active ? '0' : '1' }}">
                                            <button type="submit">{{ $cashier->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('pos.register-settings.cashiers.destroy', $cashier) }}" onsubmit="return confirm('Delete this cashier?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted" style="text-align:center;padding:20px;">No cashiers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
