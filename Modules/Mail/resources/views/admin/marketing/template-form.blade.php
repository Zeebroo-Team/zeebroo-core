@extends('theme::layouts.app', ['title' => $template ? 'Edit template' : 'New template', 'heading' => 'Email Marketing'])

@section('content')
@include('mail::admin.marketing._styles')

<div class="aem-wrap" style="max-width:980px;">
    <a href="{{ route('admin.email-marketing.index') }}" class="aem-back"><i class="fa fa-arrow-left"></i> Email Marketing</a>
    <div class="aem-header">
        <div>
            <h1 class="aem-title">{{ $template ? 'Edit template' : 'New email template' }}</h1>
            <p class="aem-sub">Design the email once and reuse it whenever you send. Merge tags like <code>&#123;&#123;first_name&#125;&#125;</code> are replaced with each recipient's details.</p>
        </div>
        @if($template)
            <a href="{{ route('admin.email-marketing.compose', ['template' => $template->id]) }}" class="aem-btn"><i class="fa fa-paper-plane"></i> Send this template</a>
        @endif
    </div>

    @if(session('status'))
        <div class="aem-msg"><i class="fa fa-circle-check" style="color:#22c55e;"></i> {{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="aem-msg aem-msg--err"><i class="fa fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $template ? route('admin.email-marketing.templates.update', $template) : route('admin.email-marketing.templates.store') }}" id="aem-template-form">
        @csrf
        @if($template) @method('PUT') @endif

        <div class="aem-card">
            <div class="aem-card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 14px;">
                    <label class="aem-field">
                        <span class="aem-label">Template name</span>
                        <input type="text" name="name" class="aem-input" maxlength="150" required value="{{ old('name', $template?->name) }}" placeholder="e.g. October product update">
                        <span class="aem-hint">Only visible to admins.</span>
                    </label>
                    <label class="aem-field">
                        <span class="aem-label">Email subject</span>
                        <input type="text" name="subject" id="aem-subject" class="aem-input" maxlength="200" required value="{{ old('subject', $template?->subject) }}" placeholder="e.g. Hi &#123;&#123;first_name&#125;&#125;, see what's new">
                        <span class="aem-hint">What recipients see in their inbox.</span>
                    </label>
                </div>

                @include('mail::admin.marketing._editor', ['body' => old('body', $template?->body ?? '')])
            </div>
        </div>

        <div class="aem-actions" style="justify-content:flex-end;margin-top:16px;">
            <a href="{{ route('admin.email-marketing.index') }}" class="aem-btn">Cancel</a>
            <button type="submit" name="after" value="send" class="aem-btn"><i class="fa fa-paper-plane"></i> Save &amp; send</button>
            <button type="submit" class="aem-btn aem-btn--primary"><i class="fa fa-floppy-disk"></i> Save template</button>
        </div>
    </form>
</div>

<script>
document.getElementById('aem-template-form').addEventListener('submit', function (e) {
    window.aemEditor.sync();
    if (window.aemEditor.isEmpty()) {
        e.preventDefault();
        alert('The email body is empty.');
    }
});
</script>
@endsection
