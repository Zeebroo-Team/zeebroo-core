{{-- Admin → User → POS Settings tab. Fetched on demand and re-rendered after every change. --}}
@php($rowsFor = fn (array $fields) => collect($fields)->map(fn ($field) => $rows[$field]))
<script type="application/json" class="apss-data">@json($rows)</script>

<div class="apss-bar" role="tablist" aria-label="POS app">
    @foreach($groups as $id => $group)
        <button type="button" class="apss-surface {{ $loop->first ? 'is-active' : '' }}" data-surface="{{ $id }}" role="tab">
            <i class="fa {{ $group['icon'] }}"></i> {{ $group['label'] }} <span class="apss-count">{{ $group['count'] }}</span>
        </button>
    @endforeach
    @if($other !== [])
        <button type="button" class="apss-surface" data-surface="other" role="tab">
            <i class="fa fa-box-archive"></i> Other saved <span class="apss-count">{{ count($other) }}</span>
        </button>
    @endif
</div>

<p class="apss-note"><i class="fa fa-circle-info"></i> A business has one value per setting, shared by every app that uses it. Settings kept on the device itself, such as the printer, aren't stored on the server, so they aren't listed.</p>

<div class="apss-flash" role="status" hidden></div>

@foreach($groups as $id => $group)
    <div class="apss-group" data-group="{{ $id }}" @unless($loop->first) hidden @endunless>
        @foreach($group['sections'] as $section => $fields)
            @include('pos::admin.settings._table', ['title' => $section, 'tableRows' => $rowsFor($fields), 'surfaceId' => $id])
        @endforeach
    </div>
@endforeach

@if($other !== [])
    <div class="apss-group" data-group="other" hidden>
        @include('pos::admin.settings._table', ['title' => 'Other saved settings', 'tableRows' => $rowsFor($other), 'surfaceId' => 'other'])
    </div>
@endif
