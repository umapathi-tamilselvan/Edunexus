<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\SyncRolesAndPermissions;
use App\Support\OrganizationContext;
use App\Support\Settings\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds what depends on the client's institution type (EDUCORE_INSTITUTION_TYPE):
 * its roles and its terminology.
 */
class InstitutionTypeSeeder extends Seeder
{
    public function run(OrganizationContext $organization, SyncRolesAndPermissions $syncRolesAndPermissions): void
    {
        $type = $organization->type();

        $syncRolesAndPermissions->handle($type);

        Setting::query()->updateOrCreate(['key' => 'institution.terminology'], ['value' => $type->terminology()]);
    }
}
