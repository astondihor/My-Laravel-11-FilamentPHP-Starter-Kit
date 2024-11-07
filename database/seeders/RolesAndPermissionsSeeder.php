<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Filament\Resources\Shield\RoleResource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $accessLogViewer = Permission::where('name', 'access_log_viewer')->first();

        if (! $accessLogViewer)
            Permission::create(['name' => 'access_log_viewer']);

        $roles = ["super_admin", "admin", "author"];

        foreach ($roles as $key => $role) {
            $exist = Role::where('name', '=', $role)->first();

            if (!$exist) {
                $roleCreated = (new (RoleResource::getModel()))->create(
                    [
                        'name' => $role,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if ($role == 'super_admin') {
                    $roleCreated->givePermissionTo('access_log_viewer');
                }
            } else {
                if ($role == 'super_admin') {
                    // check if super_admin has access_log_viewer permission
                     if (! $exist->hasPermissionTo('access_log_viewer')) {
                        $exist->givePermissionTo('access_log_viewer');
                     }
                }
            }
        }
    }
}
