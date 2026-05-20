@extends('theme::layouts.app', ['title' => 'Account Details', 'heading' => 'Account Details'])

@section('content')
<div class="card" style="max-width:760px;">
    <h2 style="margin-top:0;">{{ $account->account_name }}</h2>
    <p class="muted">Business: {{ $account->business?->name }}</p>
    <p class="muted">Type: {{ $account->bankType?->name }}</p>
    <p class="muted">Bank: {{ $account->bank?->name ?? $account->bank_name }}</p>
    <p class="muted">Account Number: {{ $account->bank_account_number }}</p>
    <p class="muted">Bank branch (text): {{ $account->branch }}</p>
    @if($account->warehouse)
        <p class="muted">Warehouse / branch (location): {{ $account->warehouse->name }}</p>
    @endif
    <p class="muted">Current Balance: {{ number_format((float) $account->current_balance, 2) }}</p>
    @if($account->bank_officer_contact)
        <p class="muted">Officer Contact: {{ $account->bank_officer_contact }}</p>
    @endif
    @if($account->notes)
        <p class="muted">Notes: {{ $account->notes }}</p>
    @endif
    <a class="linkbtn" href="{{ route('account.index') }}">Back</a>
</div>
@endsection
