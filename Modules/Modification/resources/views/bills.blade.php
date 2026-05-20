@extends('theme::layouts.app', [
    'title' => __('Bills for modification'),
    'heading' => __('Modification'),
])

@section('content')
<div class="card" style="max-width:none;font-size:13px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px 14px;align-items:center;justify-content:space-between;">
        <div>
            <p class="muted" style="margin:0 0 4px;font-size:11px;">{{ __('Bills assigned to') }}</p>
            <h1 style="margin:0;font-size:18px;line-height:1.25;font-weight:700;">{{ $modification->name }}</h1>
            <p style="margin:8px 0 0;">
                <a href="{{ route('modification.show', $modification) }}" style="font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;">{{ __('View modification details') }}</a>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('modification.index') }}" class="linkbtn" style="padding:8px 11px;font-size:12px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;">
                ← {{ __('Back to modifications') }}
            </a>
            <a href="{{ route('account.bills.index') }}" class="linkbtn" style="padding:8px 11px;font-size:12px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;">
                {{ __('All bills') }}
            </a>
        </div>
    </div>

    <div style="margin-top:16px;">
        @include('modification::partials.assigned-bills-table', ['bills' => $bills, 'business' => $business])
    </div>
</div>
@endsection
