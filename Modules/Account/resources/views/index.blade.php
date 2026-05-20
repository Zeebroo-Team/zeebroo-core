@extends('theme::layouts.app', ['title' => 'Accounts', 'heading' => 'Accounts'])

@section('content')
<div class="card" style="max-width:100%;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h1 style="margin:0;">Bank Accounts</h1>
        <a class="linkbtn" href="{{ route('account.create') }}">Add Account</a>
    </div>

    @if(session('status'))
        <p style="margin-top:10px;color:#16a34a;">{{ session('status') }}</p>
    @endif

    <div style="margin-top:18px;display:grid;gap:10px;">
        @forelse($accounts as $account)
            <div style="border:1px solid var(--border);border-radius:12px;padding:12px 14px;">
                <div style="font-weight:600;">{{ $account->account_name }}</div>
                <div class="muted">Business: {{ $account->business?->name }}</div>
                <div class="muted">{{ $account->bank?->name ?? $account->bank_name }} - {{ $account->bankType?->name }}</div>
                <div class="muted">{{ $account->bank_account_number }} | {{ $account->branch }}@if($account->warehouse) · {{ $account->warehouse->name }} @endif</div>
                <div class="muted">Balance: {{ number_format((float) $account->current_balance, 2) }}</div>
                @if($account->bank_officer_contact)
                    <div class="muted">Officer: {{ $account->bank_officer_contact }}</div>
                @endif
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="linkbtn" href="{{ route('account.show', $account->id) }}">View</a>
                    <a class="linkbtn" href="{{ route('account.edit', $account->id) }}">Edit</a>
                    <form method="post" action="{{ route('account.destroy', $account->id) }}" style="display:inline;">
                        @csrf
                        @method('delete')
                        <button type="submit">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="muted">No accounts added yet.</p>
        @endforelse
    </div>
</div>
@endsection
