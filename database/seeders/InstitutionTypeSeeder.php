<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\SyncRolesAndPermissions;
use App\Support\OrganizationContext;
use App\Support\Settings\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds what depends on the client's institution type (EDUNEXUS_INSTITUTION_TYPE):
 * its roles, its terminology and the month its academic years start in.
 * A start month the client already has is kept.
 */
class InstitutionTypeSeeder extends Seeder
{
    public function run(OrganizationContext $organization, SyncRolesAndPermissions $syncRolesAndPermissions): void
    {
        $type = $organization->type();

        $syncRolesAndPermissions->handle($type);

        Setting::query()->updateOrCreate(['key' => 'institution.terminology'], ['value' => $type->terminology()]);
        Setting::query()->firstOrCreate(['key' => OrganizationContext::YEAR_START_MONTH], ['value' => $type->yearStartMonth()]);
    }
}
