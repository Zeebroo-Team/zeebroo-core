@php($submitLabel = $submitLabel ?? 'Save branch')
@if($errors->any() && filter_var($showBranchCreateErrorBanner ?? true, FILTER_VALIDATE_BOOLEAN))
    <div class="{{ $branchFormErrorBannerClass ?? 'branch-inline-form__banner' }}" role="alert">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ route('business.branches.store') }}" class="branch-form-grid branch-form-grid--2">
    @csrf
    @if(!empty($singleLocationSetup))
        <input type="hidden" name="single_location_setup" value="1">
    @endif
    @include('business::branches.partials.branch-fields-body', ['fieldIdPrefix' => $fieldIdPrefix ?? '', 'requireName' => $requireName ?? true, 'defaultBranchName' => $defaultBranchName ?? ''])
    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
        <button type="submit" class="linkbtn" style="padding:8px 16px;font-size:13px;">{{ $submitLabel }}</button>
    </div>
</form>
