<?php

namespace Pmb\Acl\Database\Seeders;

use Illuminate\Database\Seeder;
use Pmb\Acl\Models\Permission;

class AclSeeder extends Seeder
{
    /**
     * Execute the database seeds.
     * @return void
     */
    public function run(): void
    {
        $permissionModel = config('acl.models.permission', Permission::class);

        /**
         * Get the modules and their permissions 
         * from the configuration file.
         * 
         * e.g.
         * 'modules' => [
         *      'user' => [
         *          'user.create' => 'Create User',
         *          'user.edit' => 'Edit User',
         *          'user.delete' => 'Delete User',
         *      ],...
         * ],
         * 
         */
        $modules = config('acl.modules', []);

        if (empty($modules)) {
            return;
        }

        // Iterate through modules and their permissions to insert or update them in the database.
        foreach ($modules as $moduleName => $permissions) {

            foreach ($permissions as $name => $label) {
                $permissionModel::updateOrCreate(
                    ['name' => $name],
                    [
                        'label'  => $label,
                        'module' => $moduleName,
                    ]
                );
            }
        }
    }
}