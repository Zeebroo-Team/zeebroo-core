<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Auth\Models\AffiliateDocument;
use Modules\Auth\Models\AffiliateProfile;
use Modules\Auth\Services\AffiliateProfileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Affiliate settings page: profile, contact and address, payout bank, ID documents. */
class AffiliateSettingsController extends Controller
{
    private const PHONE_RULE = 'regex:/^\+?[0-9 ()\-]{7,20}$/';

    public function __construct(private readonly AffiliateProfileService $profiles) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $profile = $this->profiles->profileFor($user);
        $documents = $this->profiles->documentsFor($user);

        $tab = (string) (old('_tab') ?: $request->query('tab', 'profile'));
        if (! in_array($tab, AffiliateProfileService::TABS, true)) {
            $tab = 'profile';
        }

        return view('auth::affiliate.settings', [
            'user' => $user,
            'profile' => $profile,
            'documents' => $documents,
            'completion' => $this->profiles->completion($user, $profile, $documents),
            'tab' => $tab,
            'idTypes' => AffiliateProfile::ID_TYPES,
            'slots' => AffiliateDocument::SLOTS,
            'documentMimes' => AffiliateProfileService::DOCUMENT_MIMES,
            'documentMaxMb' => (int) (AffiliateProfileService::DOCUMENT_MAX_KB / 1024),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = strcasecmp(trim((string) $request->input('email')), (string) $user->email) !== 0;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            // Changing the login email or password needs the current password.
            'current_password' => [Rule::requiredIf($emailChanged || filled($request->input('password'))), 'nullable', 'current_password'],
        ], [
            'current_password.required' => __('Enter your current password to change your email or password.'),
            'current_password.current_password' => __('Your current password is not correct.'),
        ]);

        $this->profiles->updateAccount($user, $data);

        return $this->backToTab('profile', __('Profile saved.'));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', self::PHONE_RULE],
            'phone_alt' => ['nullable', 'string', self::PHONE_RULE],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
        ], [
            'phone.regex' => __('Enter a valid phone number, e.g. +94 77 123 4567.'),
            'phone_alt.regex' => __('Enter a valid phone number, e.g. +94 11 234 5678.'),
        ]);

        $this->profiles->updateContact($request->user(), $data);

        return $this->backToTab('contact', __('Contact details saved.'));
    }

    public function updatePayout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'bank_branch' => ['required', 'string', 'max:120'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9A-Za-z \-]{4,34}$/'],
        ], [
            'account_number.regex' => __('Enter the account number using digits only (spaces and dashes are fine).'),
        ]);

        $this->profiles->updatePayout($request->user(), $data);

        return $this->backToTab('payout', __('Payout bank details saved.'));
    }

    public function updateIdentity(Request $request): RedirectResponse
    {
        $user = $request->user();
        $fileRule = ['nullable', 'file', 'mimes:'.implode(',', AffiliateProfileService::DOCUMENT_MIMES), 'max:'.AffiliateProfileService::DOCUMENT_MAX_KB];

        $data = $request->validate([
            'id_type' => ['required', Rule::in(array_keys(AffiliateProfile::ID_TYPES))],
            'id_number' => ['required', 'string', 'max:30', $request->input('id_type') === AffiliateProfile::ID_NIC
                ? 'regex:/^([0-9]{9}[VvXx]|[0-9]{12})$/'
                : 'regex:/^[A-Za-z0-9]{6,12}$/'],
            AffiliateDocument::NIC_FRONT => $fileRule,
            AffiliateDocument::NIC_BACK => $fileRule,
            AffiliateDocument::PASSPORT => $fileRule,
        ], [
            'id_number.regex' => $request->input('id_type') === AffiliateProfile::ID_NIC
                ? __('Enter a valid NIC number: 9 digits and V or X, or 12 digits.')
                : __('Enter a valid passport number: 6 to 12 letters and digits.'),
        ] + collect(AffiliateDocument::types())->flatMap(fn (string $type) => [
            $type.'.mimes' => __('Upload a JPG, PNG, WEBP or PDF file.'),
            $type.'.max' => __('The file must be :mb MB or smaller.', ['mb' => (int) (AffiliateProfileService::DOCUMENT_MAX_KB / 1024)]),
            $type.'.uploaded' => __('The upload failed. Try a smaller file.'),
        ])->all());

        // Every slot of the chosen ID type needs a copy: already on file, or uploaded now.
        $existing = $this->profiles->documentsFor($user);
        $missing = [];
        foreach (AffiliateDocument::SLOTS[$data['id_type']] as $type => $label) {
            if (! $request->hasFile($type) && ! $existing->has($type)) {
                $missing[$type] = __('Upload a copy of the :label.', ['label' => mb_strtolower($label)]);
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $this->profiles->updateIdentity($user, $data['id_type'], $data['id_number'], [
            AffiliateDocument::NIC_FRONT => $request->file(AffiliateDocument::NIC_FRONT),
            AffiliateDocument::NIC_BACK => $request->file(AffiliateDocument::NIC_BACK),
            AffiliateDocument::PASSPORT => $request->file(AffiliateDocument::PASSPORT),
        ]);

        return $this->backToTab('identity', __('ID details saved.'));
    }

    public function showDocument(Request $request, string $type): StreamedResponse
    {
        return $this->profiles->documentResponse($request->user(), $type);
    }

    public function destroyDocument(Request $request, string $type): RedirectResponse
    {
        $this->profiles->deleteDocument($request->user(), $type);

        return $this->backToTab('identity', __(':label removed.', ['label' => AffiliateDocument::labelFor($type)]));
    }

    private function backToTab(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('affiliate.settings', ['tab' => $tab])->with('success', $message);
    }
}
