<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Support\Deployment\InstitutionType;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the code-defined permissions and the institution type's roles in
 * this client's database. Safe to rerun on every deploy: new permissions are
 * added, new roles get their defaults, and roles a client has edited are
 * left alone. Super Admin and Admin always hold every permission.
 */
class SyncRolesAndPermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(InstitutionType $type): void
    {
        $this->registrar->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach ([Role::SuperAdmin, ...$type->roles()] as $role) {
            $model = RoleModel::query()->firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

            if ($role->alwaysHoldsEveryPermission() || $model->wasRecentlyCreated) {
                $model->syncPermissions(array_map(fn (Permission $permission): string => $permission->value, $role->defaultPermissions()));
            }
        }

        $this->registrar->forgetCachedPermissions();
    }
}
