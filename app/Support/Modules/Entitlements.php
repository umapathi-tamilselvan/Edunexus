<?php

namespace App\Support\Modules;

use App\Support\OrganizationContext;

/**
 * Modules this deployment may use: the institution type's defaults, plus
 * EDUNEXUS_MODULES_ENABLE, minus EDUNEXUS_MODULES_DISABLE.
 */
class Entitlements
{
    public function __construct(private OrganizationContext $organization) {}

    public function allows(Module $module): bool
    {
        return in_array($module, $this->modules(), true);
    }

    /**
     * @return list<Module>
     */
    public function modules(): array
    {
        $values = array_map(fn (Module $module): string => $module->value, $this->organization->type()->defaultModules());
        $values = [...$values, ...(array) config('edunexus.modules.enable')];
        $values = array_diff($values, (array) config('edunexus.modules.disable'));

        return array_values(array_filter(array_map(Module::tryFrom(...), array_unique($values))));
    }
}
