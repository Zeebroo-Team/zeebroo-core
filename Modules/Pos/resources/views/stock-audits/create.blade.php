@extends('theme::layouts.app', ['title' => 'New Stock Audit', 'heading' => 'New Stock Audit'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('pos::partials.pos-hub-nav')

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Info card --}}
    <div style="display:flex;gap:14px;align-items:flex-start;padding:16px;border:1px solid color-mix(in srgb,#3b82f6 35%,var(--border));border-radius:12px;background:color-mix(in srgb,#3b82f6 7%,transparent);margin-bottom:20px;">
        <div style="width:36px;height:36px;border-radius:10px;background:color-mix(in srgb,#3b82f6 18%,transparent);color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
            <i class="fa fa-circle-info"></i>
        </div>
        <div>
            <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:var(--text);">What happens when you create an audit</p>
            <p style="margin:0;font-size:13px;color:var(--muted);line-height:1.6;">
                A snapshot of all <strong style="color:var(--text);">{{ $productCount }} active product{{ $productCount !== 1 ? 's' : '' }}</strong> is taken with their current system stock quantities.
                You then enter your physical counts, and the system highlights discrepancies.
                Finalizing the audit updates product stock to match your physical counts.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('pos.stock-audits.store') }}" class="pcat-form-grid pcat-form-grid--2">
        @csrf

        <div class="pcat-field">
            <label for="sa-date">Audit date <span style="color:#ef4444;">*</span></label>
            <input id="sa-date" type="date" name="audit_date" required
                   value="{{ old('audit_date', now()->format('Y-m-d')) }}">
            @error('audit_date')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div class="pcat-field" style="grid-column:1/-1;">
            <label for="sa-notes">Notes (optional)</label>
            <textarea id="sa-notes" name="notes" maxlength="2000"
                      placeholder="Reason for audit, location, counter name…">{{ old('notes') }}</textarea>
            @error('notes')<div style="color:#f87171;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px;padding-top:8px;border-top:1px solid var(--border);">
            <a href="{{ route('pos.stock-audits.index') }}"
               class="linkbtn" style="padding:8px 16px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">
                Cancel
            </a>
            <button type="submit" class="linkbtn" style="padding:8px 18px;font-size:13px;">
                <i class="fa fa-clipboard-list"></i> Create audit
            </button>
        </div>
    </form>
</div>
@endsection
