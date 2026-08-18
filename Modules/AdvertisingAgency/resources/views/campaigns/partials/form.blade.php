<form method="POST"
      action="{{ isset($campaign) ? route('advertising-agency.campaigns.update', $campaign) : route('advertising-agency.campaigns.store') }}">
    @csrf
    @if(isset($campaign)) @method('PUT') @endif

    <div class="pcat-form-grid">
        <div class="pcat-form-field">
            <label>Client <span class="pcat-req">*</span></label>
            <select name="client_id" required>
                <option value="">— select client —</option>
                @foreach($clients as $cl)
                    <option value="{{ $cl->id }}" @selected(old('client_id', $campaign->client_id ?? '') == $cl->id)>
                        {{ $cl->name }}{{ $cl->company ? ' ('.$cl->company.')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('client_id') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>

        <div class="pcat-form-field">
            <label>Campaign name <span class="pcat-req">*</span></label>
            <input type="text" name="name" value="{{ old('name', $campaign->name ?? '') }}"
                   placeholder="e.g. Summer Sale 2026" maxlength="150" required>
            @error('name') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>

        <div class="pcat-form-field">
            <label>Channel</label>
            <select name="channel">
                <option value="">— select channel —</option>
                @foreach($channels as $ch)
                    <option value="{{ $ch }}" @selected(old('channel', $campaign->channel ?? '') === $ch)>
                        {{ ucwords(str_replace('_', ' ', $ch)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="pcat-form-field">
            <label>Status</label>
            <select name="status">
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', $campaign->status ?? 'draft') === $s)>
                        {{ ucfirst($s) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="pcat-form-field">
            <label>Budget</label>
            <input type="number" name="budget" step="0.01" min="0"
                   value="{{ old('budget', $campaign->budget ?? '') }}"
                   placeholder="0.00">
            @error('budget') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>

        <div class="pcat-form-field">
            <label>Start date</label>
            <input type="date" name="start_date"
                   value="{{ old('start_date', $campaign->start_date?->format('Y-m-d') ?? '') }}">
            @error('start_date') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>

        <div class="pcat-form-field">
            <label>End date</label>
            <input type="date" name="end_date"
                   value="{{ old('end_date', $campaign->end_date?->format('Y-m-d') ?? '') }}">
            @error('end_date') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>

        <div class="pcat-form-field">
            <label>Goal</label>
            <input type="text" name="goal" value="{{ old('goal', $campaign->goal ?? '') }}"
                   placeholder="e.g. 1000 signups, brand awareness" maxlength="255">
        </div>

        <div class="pcat-form-field">
            <label>Target audience</label>
            <input type="text" name="target_audience"
                   value="{{ old('target_audience', $campaign->target_audience ?? '') }}"
                   placeholder="e.g. 25-40, urban, tech-savvy" maxlength="255">
        </div>

        <div class="pcat-form-field pcat-form-field--full">
            <label>Description</label>
            <textarea name="description" rows="3" maxlength="2000"
                      placeholder="Campaign overview…">{{ old('description', $campaign->description ?? '') }}</textarea>
        </div>

        <div class="pcat-form-field pcat-form-field--full">
            <label>Notes</label>
            <textarea name="notes" rows="2" maxlength="2000"
                      placeholder="Internal notes…">{{ old('notes', $campaign->notes ?? '') }}</textarea>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 20px;">
            <i class="fa fa-floppy-disk"></i> {{ isset($campaign) ? 'Save changes' : 'Create campaign' }}
        </button>
    </div>
</form>
