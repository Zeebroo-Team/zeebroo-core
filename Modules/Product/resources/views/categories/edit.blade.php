@extends('theme::layouts.app', ['title' => 'Edit category', 'heading' => 'Edit category'])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="card" style="max-width:640px;margin:0 auto;padding:16px;">
    @include('product::partials.product-hub-nav')

    <p class="muted" style="margin:0 0 14px;font-size:13px;">Editing <strong style="color:var(--text);">{{ $category->name }}</strong></p>

    @if($errors->any())
        <div class="pcat-banner pcat-banner--err" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('product.categories.update', $category) }}" class="pcat-form-grid pcat-form-grid--2">
        @csrf
        @method('PUT')
        @include('product::categories.partials.form-fields', ['category' => $category, 'fieldIdPrefix' => 'edit-cat', 'parentOptions' => $parentOptions])
        <div style="grid-column:1/-1;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;">
            <a href="{{ route('product.categories.index') }}" class="linkbtn" style="padding:8px 14px;font-size:13px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;">Cancel</a>
            <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">Save changes</button>
        </div>
    </form>
</div>
@endsection
