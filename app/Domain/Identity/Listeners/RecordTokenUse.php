<?php

namespace App\Domain\Identity\Listeners;

use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Records when a mobile token was last used, at most once every few
 * minutes (sanctum.last_used_at_every) instead of on every request.
 */
class RecordTokenUse
{
    public function handle(TokenAuthenticated $event): void
    {
        $lastUsedAt = $event->token->last_used_at;

        if ($lastUsedAt === null || $lastUsedAt->lte(now()->subSeconds((int) config('sanctum.last_used_at_every')))) {
            $event->token->forceFill(['last_used_at' => now()])->save();
        }
    }
}
