@extends('theme::layouts.app', ['title' => 'Create Account', 'heading' => 'Create Account'])

@section('content')
<style>
    .acc-field{
        padding:12px;
        border-radius:10px;
        border:1px solid #d1d5db;
        background:transparent;
        color:var(--text);
        transition:all .2s ease;
    }
    .acc-field:focus{
        border-color:#a78bfa;
        background:color-mix(in srgb, var(--card) 82%, #fff);
        outline:none;
    }
</style>
<div class="card" style="max-width:760px;">
    <form method="post" action="{{ route('account.store') }}" style="display:grid;gap:12px;">
        @csrf
        <select name="business_id" required class="acc-field">
            <option value="">Select business</option>
            @foreach($businesses as $business)
                <option value="{{ $business->id }}" @selected(old('business_id') == $business->id)>{{ $business->name }}</option>
            @endforeach
        </select>
        @include('account::partials.warehouse-branch-select', [
            'presetBranchId' => old('branch_id'),
            'warehouseSelectClass' => 'acc-field',
        ])
        <input name="account_name" value="{{ old('account_name') }}" placeholder="Account name" required class="acc-field">
        <select name="bank_type_id" required class="acc-field">
            <option value="">Select account type</option>
            @foreach($bankTypes as $type)
                <option value="{{ $type->id }}" @selected(old('bank_type_id') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="bank_id" required class="acc-field">
            <option value="">Select bank</option>
            @foreach($banks as $bank)
                <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->name }}</option>
            @endforeach
        </select>
        <input name="bank_account_number" value="{{ old('bank_account_number') }}" placeholder="Bank account number" required class="acc-field">
        <input name="branch" value="{{ old('branch') }}" placeholder="Branch" required class="acc-field">
        <input name="current_balance" type="number" min="0" step="0.01" value="{{ old('current_balance') }}" placeholder="Current balance" required class="acc-field">
        <input name="bank_officer_contact" value="{{ old('bank_officer_contact') }}" placeholder="Bank officer contact (optional)" class="acc-field">
        <textarea name="notes" placeholder="Notes (optional)" class="acc-field">{{ old('notes') }}</textarea>
        <button type="submit">Save Account</button>
    </form>
</div>
@endsection
