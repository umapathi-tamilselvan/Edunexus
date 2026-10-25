<?php

namespace App\Providers;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only a Super Admin opens the queue dashboard outside local
     * development: job payloads can hold students' personal data.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => (bool) $user?->hasRole(Role::SuperAdmin->value));
    }
}
