@extends('theme::layouts.app', ['title' => 'Edit Account', 'heading' => 'Edit Account'])

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
    <form method="post" action="{{ route('account.update', $account->id) }}" style="display:grid;gap:12px;">
        @csrf
        @method('patch')
        <select name="business_id" required class="acc-field">
            @foreach($businesses as $business)
                <option value="{{ $business->id }}" @selected(old('business_id', $account->business_id) == $business->id)>{{ $business->name }}</option>
            @endforeach
        </select>
        @include('account::partials.warehouse-branch-select', [
            'presetBranchId' => old('branch_id', $account->branch_id),
            'warehouseSelectClass' => 'acc-field',
        ])
        <input name="account_name" value="{{ old('account_name', $account->account_name) }}" placeholder="Account name" required class="acc-field">
        <select name="bank_type_id" required class="acc-field">
            @foreach($bankTypes as $type)
                <option value="{{ $type->id }}" @selected(old('bank_type_id', $account->bank_type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="bank_id" required class="acc-field">
            @foreach($banks as $bank)
                <option value="{{ $bank->id }}" @selected(old('bank_id', $account->bank_id) == $bank->id)>{{ $bank->name }}</option>
            @endforeach
        </select>
        <input name="bank_account_number" value="{{ old('bank_account_number', $account->bank_account_number) }}" placeholder="Bank account number" required class="acc-field">
        <input name="branch" value="{{ old('branch', $account->branch) }}" placeholder="Branch" required class="acc-field">
        <input name="current_balance" type="number" min="0" step="0.01" value="{{ old('current_balance', $account->current_balance) }}" placeholder="Current balance" required class="acc-field">
        <input name="bank_officer_contact" value="{{ old('bank_officer_contact', $account->bank_officer_contact) }}" placeholder="Bank officer contact (optional)" class="acc-field">
        <textarea name="notes" placeholder="Notes (optional)" class="acc-field">{{ old('notes', $account->notes) }}</textarea>
        <button type="submit">Update Account</button>
    </form>
</div>
@endsection
