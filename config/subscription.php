<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment grace period
    |--------------------------------------------------------------------------
    |
    | Days a pending/failed subscription payment may go unsettled before the
    | business is locked out (web + desktop). Admins can override this per
    | user from Admin › User Management › Edit (users.payment_grace_days).
    |
    */

    'grace_period_days' => (int) env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 2),

    // Upper bound for the per-user override an admin can set.
    'max_grace_period_days' => 365,

];
