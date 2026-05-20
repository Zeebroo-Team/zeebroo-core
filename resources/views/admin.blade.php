@extends('theme::layouts.app', ['title' => 'Admin Panel', 'heading' => 'Admin Panel'])

@section('content')
<div class="card">
    <h1>Admin Panel</h1>
    <p class="muted">This page is protected by Spatie role middleware (`role:admin`).</p>
    <p><a class="linkbtn" href="{{ route('dashboard') }}">Back to Overview</a></p>
</div>
@endsection
