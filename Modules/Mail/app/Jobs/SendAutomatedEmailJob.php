<?php

namespace Modules\Mail\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Mail\Services\AutomatedEmailService;

class SendAutomatedEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(
        public readonly string $key,
        public readonly int $userId,
        public readonly array $context = [],
    ) {}

    public function handle(AutomatedEmailService $service): void
    {
        $user = User::find($this->userId);

        if ($user !== null) {
            // Failures are logged against the automation by the service; nothing to retry here.
            $service->deliver($this->key, $user, $this->context);
        }
    }
}
