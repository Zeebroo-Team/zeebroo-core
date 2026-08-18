<form method="POST"
      action="{{ isset($client) ? route('advertising-agency.clients.update', $client) : route('advertising-agency.clients.store') }}">
    @csrf
    @if(isset($client)) @method('PUT') @endif

    <div class="pcat-form-grid">
        <div class="pcat-form-field">
            <label>Name <span class="pcat-req">*</span></label>
            <input type="text" name="name" value="{{ old('name', $client->name ?? '') }}"
                   placeholder="Client name" maxlength="150" required>
            @error('name') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>
        <div class="pcat-form-field">
            <label>Company</label>
            <input type="text" name="company" value="{{ old('company', $client->company ?? '') }}"
                   placeholder="Company name" maxlength="150">
        </div>
        <div class="pcat-form-field">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email', $client->email ?? '') }}"
                   placeholder="contact@example.com" maxlength="150">
            @error('email') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>
        <div class="pcat-form-field">
            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $client->phone ?? '') }}"
                   placeholder="+1 234 567 8900" maxlength="40">
        </div>
        <div class="pcat-form-field">
            <label>Industry</label>
            <input type="text" name="industry" value="{{ old('industry', $client->industry ?? '') }}"
                   placeholder="e.g. Retail, Finance, Tech" maxlength="100">
        </div>
        <div class="pcat-form-field">
            <label>Website</label>
            <input type="url" name="website" value="{{ old('website', $client->website ?? '') }}"
                   placeholder="https://example.com" maxlength="255">
            @error('website') <span class="pcat-err">{{ $message }}</span> @enderror
        </div>
        <div class="pcat-form-field pcat-form-field--full">
            <label>Address</label>
            <input type="text" name="address" value="{{ old('address', $client->address ?? '') }}"
                   placeholder="Street, City, Country" maxlength="300">
        </div>
        <div class="pcat-form-field pcat-form-field--full">
            <label>Notes</label>
            <textarea name="notes" rows="3" placeholder="Internal notes about this client…" maxlength="2000">{{ old('notes', $client->notes ?? '') }}</textarea>
        </div>
        <div class="pcat-form-field">
            <label>Status</label>
            <select name="status">
                <option value="active"   @selected(old('status', $client->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $client->status ?? 'active') === 'inactive')>Inactive</option>
            </select>
        </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 20px;">
            <i class="fa fa-floppy-disk"></i> {{ isset($client) ? 'Save changes' : 'Create client' }}
        </button>
    </div>
</form>
