<?php

namespace Modules\Account\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Account\Models\AddressBook;

class AddressBookService
{
    /**
     * Create or merge a landlord/owner row keyed by normalized email first, then phone (per-user).
     *
     * @param  array{name: string, email: ?string, phone: ?string, bank_account_details: ?string, owner_notes: ?string, street_address: ?string}  $input
     */
    public function syncLandlord(User $user, array $input): AddressBook
    {
        $email = $this->normalizeEmail($input['email'] ?? null);
        $phone = $this->normalizePhone($input['phone'] ?? null);

        if ($email === null && $phone === null) {
            throw ValidationException::withMessages([
                'owner_email' => 'Provide landlord email or phone so we can save them once in your address book.',
            ]);
        }

        $base = AddressBook::query()->where('user_id', $user->id);

        $byEmail = $email !== null
            ? (clone $base)->where('email', $email)->first()
            : null;
        $byPhone = $phone !== null
            ? (clone $base)->where('phone', $phone)->first()
            : null;

        if ($byEmail !== null && $byPhone !== null && (int) $byEmail->getKey() !== (int) $byPhone->getKey()) {
            throw ValidationException::withMessages([
                'owner_phone' => 'Another contact already uses this phone with a different email. Use consistent details or fix the address book entry first.',
            ]);
        }

        $row = $byEmail ?? $byPhone ?? new AddressBook(['user_id' => $user->id]);

        $excludeId = $row->exists ? (int) $row->getKey() : null;

        if ($email !== null) {
            $dupe = (clone $base)->where('email', $email);
            if ($excludeId !== null) {
                $dupe->where('id', '!=', $excludeId);
            }
            if ($dupe->exists()) {
                throw ValidationException::withMessages(['owner_email' => 'This email is already used for another contact.']);
            }
        }
        if ($phone !== null) {
            $dupe = (clone $base)->where('phone', $phone);
            if ($excludeId !== null) {
                $dupe->where('id', '!=', $excludeId);
            }
            if ($dupe->exists()) {
                throw ValidationException::withMessages(['owner_phone' => 'This phone is already used for another contact.']);
            }
        }

        $row->fill([
            'user_id' => $user->id,
            'name' => $input['name'],
            'email' => $email,
            'phone' => $phone,
            'bank_account_details' => $input['bank_account_details'] ?? null,
            'notes' => $input['owner_notes'] ?? null,
            'street_address' => $input['street_address'] ?? null,
        ]);

        $row->save();

        return $row->refresh();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }
        $t = trim(mb_strtolower($email));

        return $t === '' ? null : $t;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $t = preg_replace('/\s+/', '', trim($phone));

        return $t === '' ? null : $t;
    }
}
