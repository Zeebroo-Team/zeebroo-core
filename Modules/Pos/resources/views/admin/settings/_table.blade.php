<h4 class="apss-section">{{ $title }}</h4>
<div class="ads-card apss-card">
    <table class="ads-table">
        <thead>
            <tr>
                <th class="apss-col-setting">Setting</th>
                <th class="apss-col-value">Value</th>
                <th class="apss-col-status">Status</th>
                @unless($surfaceId === 'other')<th>Also used by</th>@endunless
                <th class="apss-actions-col"><span class="apss-sr">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($tableRows as $row)
                @php $also = array_diff($row['surfaces'], [$surfaceId]); @endphp
                <tr>
                    <td data-label="Setting">
                        <div class="apss-setting">
                            <div class="apss-label">{{ $row['label'] }}</div>
                            @if($row['key'] !== $row['label'])<div class="apss-key">{{ $row['key'] }}</div>@endif
                        </div>
                    </td>
                    <td data-label="Value" class="apss-value-cell">
                        @if($row['type'] === 'toggle')
                            <span class="apss-pill {{ $row['value'] ? 'apss-pill--on' : 'apss-pill--off' }}">{{ $row['display'] }}</span>
                        @elseif($row['type'] === 'url' && preg_match('#^https?://#i', (string) $row['value']))
                            <span class="apss-logo"><img src="{{ $row['value'] }}" alt="" loading="lazy"><span class="apss-value" title="{{ $row['value'] }}">{{ $row['value'] }}</span></span>
                        @elseif($row['display'] === 'Not set' || $row['display'] === 'None')
                            <span class="apss-muted">{{ $row['display'] }}</span>
                        @else
                            <span class="apss-value" title="{{ $row['display'] }}">{{ $row['display'] }}</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <span class="apss-status {{ $row['saved'] ? 'apss-status--saved' : '' }}" title="{{ $row['saved'] ? 'Saved for this business' : 'Nothing saved, so apps use the default' }}">{{ $row['saved'] ? 'Saved' : 'Default' }}</span>
                    </td>
                    @unless($surfaceId === 'other')
                        <td data-label="Also used by">
                            <div class="apss-chips">
                                @forelse($also as $app)
                                    <span class="apss-chip">{{ \Modules\Pos\Support\PosSettingsCatalog::SURFACES[$app]['label'] }}</span>
                                @empty
                                    <span class="apss-muted">Only this app</span>
                                @endforelse
                            </div>
                        </td>
                    @endunless
                    <td data-label="Actions" class="apss-actions">
                        <div class="apss-action-btns">
                        <button type="button" class="apss-icon-btn" data-apss-edit="{{ $row['setting'] }}" title="Edit" aria-label="Edit {{ $row['label'] }}"><i class="fa fa-pen"></i></button>
                        <button type="button" class="apss-icon-btn apss-icon-btn--danger" data-apss-delete="{{ $row['setting'] }}" aria-label="Delete saved value of {{ $row['label'] }}"
                            @if(! $row['deletable']) disabled title="Required, so it can't be deleted"
                            @elseif(! $row['saved']) disabled title="Nothing saved to delete"
                            @else title="Delete saved value" @endif><i class="fa fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
