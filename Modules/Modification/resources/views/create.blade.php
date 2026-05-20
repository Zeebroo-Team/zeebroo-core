@extends('theme::layouts.app', ['title' => __('Create Modification'), 'heading' => __('Create Modification')])

@section('content')
<div class="card" style="max-width:840px;font-size:13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h1 style="margin:0;font-size:20px;line-height:1.2;">{{ __('Create Modification') }}</h1>
        <a class="linkbtn" href="{{ route('modification.index') }}" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Back to Overview') }}</a>
    </div>

    @if($errors->any())
        <div style="margin-top:12px;padding:12px 14px;border-radius:12px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;font-size:12px;">
            <strong>{{ __('Please correct the highlighted fields.') }}</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                @foreach($errors->all() as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('modification.store') }}" style="margin-top:14px;display:grid;gap:12px;">
        @csrf
        @include('modification::partials.create-form')

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Save Modification') }}</button>
            <a class="linkbtn" href="{{ route('modification.index') }}" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
