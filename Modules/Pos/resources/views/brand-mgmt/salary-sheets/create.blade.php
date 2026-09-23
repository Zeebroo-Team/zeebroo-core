@extends('theme::layouts.app', ['title' => 'New Salary Sheet', 'heading' => 'New Salary Sheet'])

@section('content')
@include('product::partials.catalog-hub-styles')
@include('pos::brand-mgmt.partials.crud-styles')

<div class="pcat-page-card card" style="max-width:640px;padding:20px;">
    @include('pos::partials.brand-mgmt-hub-nav')

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" style="font-weight:600;">{{ $errors->first() }}</div>
    @endif

    <p class="muted" style="margin:0 0 16px;font-size:13px;">
        Next reference: <strong style="color:var(--text);">{{ $nextRef }}</strong>
    </p>

    <form method="post" action="{{ route('pos.brand-mgmt.salary-sheets.store') }}">
        @csrf
        <div class="bmg-modal__grid" style="margin-bottom:14px;">
            <div class="bmg-field">
                <label for="job_id">Job</label>
                <select name="job_id" id="job_id">
                    <option value="">— none —</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}">{{ $job->name }} ({{ $job->job_ref }})</option>
                    @endforeach
                </select>
            </div>
            <div class="bmg-field">
                <label for="location">Location</label>
                <input type="text" name="location" id="location" maxlength="200">
            </div>
            <div class="bmg-field">
                <label for="date_from">Date from</label>
                <input type="date" name="date_from" id="date_from">
            </div>
            <div class="bmg-field">
                <label for="date_to">Date to</label>
                <input type="date" name="date_to" id="date_to">
            </div>
        </div>
        <div class="bmg-field" style="margin-bottom:16px;">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" rows="3" maxlength="2000" style="resize:vertical;font-family:inherit;"></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;">
            <a href="{{ route('pos.brand-mgmt.salary-sheets.index') }}" class="bmg-btn-ghost">Cancel</a>
            <button type="submit" class="bmg-btn-primary"><i class="fa fa-floppy-disk"></i> Create salary sheet</button>
        </div>
    </form>
</div>
@endsection
