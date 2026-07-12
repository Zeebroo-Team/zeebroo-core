@extends('theme::layouts.app', ['title' => 'Project settings', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    @if(session('status'))
        <div class="pcat-banner pcat-banner--ok" style="font-weight:600;">{{ session('status') }}</div>
    @endif
    @if($errors->has('project'))
        <div class="pcat-banner pcat-banner--err">{{ $errors->first('project') }}</div>
    @endif

    <h2 style="margin:0 0 8px;font-size:16px;font-weight:800;">Project details</h2>
    @include('crm::projects.partials.create-form')

    <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <h3 style="margin:0 0 4px;font-size:13px;font-weight:800;">Danger zone</h3>
            <p class="muted" style="margin:0;font-size:12px;">
                @if($project->isArchived())
                    This project is archived. Reactivate it to add new leads again.
                @else
                    Archive this project to hide it from active use without deleting its data.
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            @if($project->isArchived())
                <form method="POST" action="{{ route('crm.projects.reactivate', $project) }}">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">
                        <i class="fa fa-rotate-left"></i> Reactivate
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('crm.projects.archive', $project) }}">
                    @csrf
                    <button type="submit" class="linkbtn" style="padding:7px 14px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);">
                        <i class="fa fa-box-archive"></i> Archive
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('crm.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="pcat-btn-del" style="padding:7px 14px;">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
