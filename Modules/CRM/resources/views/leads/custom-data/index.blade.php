@extends('theme::layouts.app', ['title' => 'Custom data', 'heading' => $project->name])

@section('content')
@include('product::partials.catalog-hub-styles')

<div class="pcat-page-card card" style="max-width:100%;padding:14px;">
    @include('crm::partials.project-nav', ['project' => $project])

    <p class="muted" style="margin:0 0 14px;font-size:13px;line-height:1.45;">
        Submissions from general-purpose forms for <strong style="color:var(--text);">{{ $project->name }}</strong>, shown as one table per form. These leads still appear on the pipeline board — this is a flat, spreadsheet-style view for reviewing raw submissions.
    </p>

    @if($forms->isEmpty())
        <p class="muted" style="font-size:13px;">No general-purpose forms yet. Custom data appears here once a "generic" form (e.g. Contact us, Newsletter) collects submissions.</p>
    @else
        @foreach($forms as $form)
            @php
                $leads = $leadsByForm->get($form->id, collect());
                $customFieldIds = collect($form->fieldBlocksWithPaths())
                    ->map(fn ($block) => $block['field'] ?? '')
                    ->filter(fn ($f) => str_starts_with($f, 'custom:'))
                    ->map(fn ($f) => (int) substr($f, 7))
                    ->unique()
                    ->values();
            @endphp
            <div style="margin-bottom:22px;">
                <div class="pcat-toolbar" style="margin-bottom:8px;">
                    <strong style="font-size:13px;color:var(--text);"><i class="fa fa-table"></i> {{ $form->name }}</strong>
                    <span class="muted" style="font-size:12px;">{{ $leads->count() }} submission{{ $leads->count() === 1 ? '' : 's' }}</span>
                </div>

                @if($leads->isEmpty())
                    <p class="muted" style="font-size:12px;">No submissions yet.</p>
                @else
                    <div class="pcat-table-wrap">
                        <table class="pcat-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    @foreach($customFieldIds as $fid)
                                        <th>{{ $customFields->get($fid)?->label ?? 'Field' }}</th>
                                    @endforeach
                                    <th>Submitted</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($leads as $lead)
                                    <tr>
                                        <td><strong style="color:var(--text);">{{ $lead->name }}</strong></td>
                                        <td>{{ $lead->company ?: '—' }}</td>
                                        <td>{{ $lead->email ?: '—' }}</td>
                                        <td>{{ $lead->phone ?: '—' }}</td>
                                        @foreach($customFieldIds as $fid)
                                            <td>{{ $lead->customFieldValues->firstWhere('custom_field_id', $fid)?->value ?: '—' }}</td>
                                        @endforeach
                                        <td><span class="muted" style="font-size:12px;">{{ $lead->created_at?->format('Y-m-d H:i') }}</span></td>
                                        <td style="text-align:right;">
                                            <a href="{{ route('crm.leads.show', $lead) }}" class="pcat-link"><i class="fa fa-eye"></i> View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>

<div style="margin-top:14px;">
    <a href="{{ route('crm.projects.leads.index', $project) }}" class="linkbtn"
       style="padding:7px 12px;font-size:12px;background:transparent;border:1px solid var(--border);color:var(--text);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fa fa-arrow-left"></i> Leads
    </a>
</div>
@endsection
