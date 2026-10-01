<?php

namespace App\Support;

use App\Support\Deployment\InstitutionType;
use App\Support\Settings\Setting;

/**
 * The client this deployment serves, from its .env, plus its settings
 * from its database.
 */
final class OrganizationContext
{
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function code(): string
    {
        return (string) config('edunexus.client.code');
    }

    public function name(): string
    {
        return (string) config('edunexus.client.name');
    }

    public function type(): InstitutionType
    {
        return InstitutionType::from((string) config('edunexus.client.type'));
    }

    /**
     * A setting from the settings table, loaded once per request.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $this->settings ??= Setting::query()->pluck('value', 'key')->all();

        return $this->settings[$key] ?? $default;
    }
}
