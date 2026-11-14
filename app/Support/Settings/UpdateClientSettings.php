<?php

namespace App\Support\Settings;

use App\Support\OrganizationContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Saves the settings a client admin changed. The changes arrive nested by
 * group (attendance.cutoff_time is sent as attendance: {cutoff_time: …});
 * only editable keys the app knows are written, and a key set back to null
 * takes its default again.
 */
class UpdateClientSettings
{
    public function __construct(private OrganizationContext $organization) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function handle(array $changes): void
    {
        DB::transaction(function () use ($changes): void {
            foreach (ClientSettings::definitions() as $key => $definition) {
                if (! $definition['editable'] || ! Arr::has($changes, $key)) {
                    continue;
                }

                $value = Arr::get($changes, $key);

                if ($value === null) {
                    Setting::query()->where('key', $key)->delete();

                    continue;
                }

                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        $this->organization->forgetSettings();
    }
}
