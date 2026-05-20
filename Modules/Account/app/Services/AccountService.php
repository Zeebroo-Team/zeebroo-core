<?php

namespace Modules\Account\Services;

use App\Models\User;
use Modules\Account\Models\Account;
use Modules\Account\Models\Bank;

class AccountService
{
    public function create(User $user, array $data): Account
    {
        $data['user_id'] = $user->id;
        $bank = Bank::find($data['bank_id']);
        $data['bank_name'] = $bank?->name;

        return Account::create($data);
    }

    public function update(Account $account, array $data): Account
    {
        $bank = Bank::find($data['bank_id']);
        $data['bank_name'] = $bank?->name;
        $account->update($data);

        return $account->refresh();
    }

    /**
     * Decrement balance on an {@see Account} row that has already been selected with {@see lockForUpdate()} within the caller's transaction.
     */
    public function applyBalanceDeduction(Account $lockedAccount, float $amount): void
    {
        if ($amount <= 0.0) {
            return;
        }

        $new = round(((float) (string) $lockedAccount->current_balance) - $amount, 2);
        $lockedAccount->update(['current_balance' => $new]);
    }

    /**
     * Increment balance on an {@see Account} row that has already been selected with {@see lockForUpdate()} within the caller's transaction.
     */
    public function applyBalanceAddition(Account $lockedAccount, float $amount): void
    {
        if ($amount <= 0.0) {
            return;
        }

        $new = round(((float) (string) $lockedAccount->current_balance) + $amount, 2);
        $lockedAccount->update(['current_balance' => $new]);
    }
}
