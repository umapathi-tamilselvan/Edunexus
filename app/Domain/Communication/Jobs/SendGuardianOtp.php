<?php

namespace App\Domain\Communication\Jobs;

use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Models\Guardian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends a guardian their sign-in code on the critical queue (§4.3). The code
 * is held in the queued payload only until it is sent, and it expires after
 * five minutes whatever happens to the job.
 */
class SendGuardianOtp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(public int $guardianId, public string $code)
    {
        $this->onQueue('critical');
    }

    public function handle(GuardianNotifier $notifier): void
    {
        $guardian = Guardian::query()->find($this->guardianId);

        if ($guardian !== null) {
            $notifier->otp($guardian, $this->code);
        }
    }
}
