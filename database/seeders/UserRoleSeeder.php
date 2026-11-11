<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Limited "user" role: PDF documents only. No templates, admins, settings, activity log or log viewer.
class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(['ViewAny', 'View', 'Create', 'Update', 'Delete'])
            ->map(fn (string $action) => Permission::findOrCreate("{$action}:PdfDocument", 'admin'));

        Role::findOrCreate('user', 'admin')->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::where('email', 'user@gmail.com')->first();

        if (! $admin) {
            $password = Str::password(12, symbols: false);
            $admin = Admin::create(['name' => 'user', 'email' => 'user@gmail.com', 'password' => $password]);
            $this->command?->info("Created user@gmail.com with password: {$password}");
        }

        $admin->syncRoles(['user']);
    }
}
