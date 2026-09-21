<?php

namespace Database\Seeders;

use App\Enums\Auth\Permission as PermissionEnum;
use App\Enums\Auth\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value)
                ->syncPermissions(array_column($role->permissions(), 'value'));
        }
    }
}
