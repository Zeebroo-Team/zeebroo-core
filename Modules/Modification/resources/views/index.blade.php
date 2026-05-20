@extends('theme::layouts.app', ['title' => __('Modification Overview'), 'heading' => __('Modification')])

@section('content')
<div class="card" style="max-width:none;font-size:13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h1 style="margin:0;font-size:20px;line-height:1.2;">{{ __('Modification Overview') }}</h1>
        @if(($modifications ?? collect())->isNotEmpty())
            <button type="button" class="linkbtn" id="openModificationCreateModal" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Add Modification') }}</button>
        @endif
    </div>

    @if(session('status'))
        <p style="margin:12px 0 0;color:#16a34a;font-weight:600;font-size:12px;">{{ session('status') }}</p>
    @endif

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

    @if(($modifications ?? collect())->isEmpty())
        <p class="muted" style="margin-top:12px;font-size:12px;">{{ __('No modifications found yet. Add your first modification below.') }}</p>
        <form method="post" action="{{ route('modification.store') }}" style="margin-top:12px;display:grid;gap:12px;">
            @csrf
            @include('modification::partials.create-form')
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Save Modification') }}</button>
            </div>
        </form>
    @else
        <div style="margin-top:14px;overflow:auto;border:1px solid var(--border);border-radius:12px;">
            <table id="modificationOverviewTable" style="width:100%;border-collapse:collapse;min-width:1040px;">
                <thead>
                <tr style="background:color-mix(in srgb,var(--card) 96%,transparent);">
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Name') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Assigned to') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Reference') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Estimation cost') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Duration') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Description') }}</th>
                    <th style="text-align:left;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Created') }}</th>
                    <th style="text-align:right;padding:10px;border-bottom:1px solid var(--border);font-size:12px;">{{ __('Bills') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($modifications as $modification)
                    @php
                        $rowRefLabel = \Modules\Modification\Models\Modification::displayAssignmentReference(
                            $modification->assignment_type,
                            $modification->assignment_reference,
                            $assignmentPropertyLookup ?? [],
                        );
                    @endphp
                    <tr data-mod-detail-url="{{ route('modification.show', $modification) }}" style="cursor:pointer;">
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-weight:600;font-size:12px;">
                            <a href="{{ route('modification.show', $modification) }}" style="color:inherit;font-weight:600;text-decoration:none;border-bottom:1px dashed color-mix(in srgb,var(--text) 35%,transparent);">{{ $modification->name }}</a>
                        </td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ ucfirst($modification->assignment_type) }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">
                            {{ $rowRefLabel ?? '—' }}
                        </td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ number_format((float) $modification->estimated_cost, 2) }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ $modification->duration ?: '—' }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);max-width:280px;font-size:12px;">{{ $modification->description ? \Illuminate\Support\Str::limit($modification->description, 120) : '—' }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;">{{ $modification->created_at?->format('Y-m-d') ?? '—' }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid color-mix(in srgb,var(--border) 70%,transparent);font-size:12px;text-align:right;white-space:nowrap;">
                            <a href="{{ route('modification.bills', $modification) }}" class="linkbtn" style="padding:7px 10px;font-size:11px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                {{ __('View bills') }}
                                <span class="muted" style="font-weight:600;font-size:10px;">({{ (int) ($modification->bills_count ?? 0) }})</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">
            {{ $modifications->links() }}
        </div>
        <style>
            #modificationOverviewTable tbody tr[data-mod-detail-url]:hover td {
                background: color-mix(in srgb, var(--primary) 6%, transparent);
            }
        </style>
        <script>
            (function () {
                var table = document.getElementById('modificationOverviewTable');
                if (!table) return;
                table.addEventListener('click', function (e) {
                    var from = e.target;
                    if (!(from instanceof Element)) {
                        from = from.parentElement;
                    }
                    if (from instanceof Element && from.closest('a')) return;
                    var tr = from instanceof Element ? from.closest('tr[data-mod-detail-url]') : null;
                    if (!tr) return;
                    var url = tr.getAttribute('data-mod-detail-url');
                    if (url) window.location.href = url;
                });
            })();
        </script>
    @endif
</div>

@if(($modifications ?? collect())->isNotEmpty())
    <div id="modificationCreateModal" hidden style="position:fixed;inset:0;z-index:70;">
        <div data-modification-modal-close style="position:absolute;inset:0;background:rgba(15,23,42,.55);"></div>
        <div style="position:relative;max-width:860px;width:calc(100% - 24px);margin:14px auto;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px;max-height:calc(100vh - 28px);overflow:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px;">
                <h2 style="margin:0;font-size:16px;">{{ __('Add Modification') }}</h2>
                <button type="button" data-modification-modal-close style="padding:7px 10px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Close') }}</button>
            </div>
            <form method="post" action="{{ route('modification.store') }}" style="display:grid;gap:12px;">
                @csrf
                @include('modification::partials.create-form')
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" style="padding:8px 11px;font-size:12px;border-radius:8px;">{{ __('Save Modification') }}</button>
                    <button type="button" data-modification-modal-close style="padding:8px 11px;font-size:12px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('modificationCreateModal');
            const openBtn = document.getElementById('openModificationCreateModal');
            if (!modal || !openBtn) return;

            const closeButtons = modal.querySelectorAll('[data-modification-modal-close]');
            const openModal = () => {
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
            };
            const closeModal = () => {
                modal.hidden = true;
                document.body.style.overflow = '';
            };

            openBtn.addEventListener('click', openModal);
            closeButtons.forEach((el) => el.addEventListener('click', closeModal));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.hidden) closeModal();
            });

            @if($errors->any())
                openModal();
            @endif
        })();
    </script>
@endif
@endsection
