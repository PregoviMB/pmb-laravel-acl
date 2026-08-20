<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authorization Architecture Mode
    |--------------------------------------------------------------------------
    |
    | Defines the authorization strategy for your application.
    |
    | Available options:
    | - 'hybrid' : Roles + Direct Permissions + Wildcards [Recommended]
    | - 'rbac'   : Role-Based Access Control only (Users -> Roles -> Permissions)
    | - 'pbac'   : Permission-Based Access Control only (Users -> Permissions)
    |
    | Practical Examples:
    |
    | 1. Mode 'hybrid' (Example: School Management System)
    |    A user has the 'Teacher' role (RBAC), but the administration grants them
    |    a direct permission 'scholarships.manage' (PBAC) for a specific event
    |    without creating a new role.
    |
    | 2. Mode 'rbac' (Example: Standard Blog)
    |    A user is assigned the 'Editor' role. They inherit all permissions bound
    |    to that role ('posts.create', 'posts.edit'). Individual permission
    |    assignment directly to users is disabled.
    |
    | 3. Mode 'pbac' (Example: Microservice / API Tokens)
    |    No roles exist in the system. Permissions like 'invoices.read' or
    |    'reports.export' are attached directly to users or API tokens.
    |
    */
    'mode' => 'hybrid',

    /*
    |--------------------------------------------------------------------------
    | Wildcards Evaluation
    |--------------------------------------------------------------------------
    |
    | Enable or disable wildcard pattern matching for permissions.
    |
    | Practical Example:
    | Assigning the permission 'posts.*' automatically authorizes actions such as:
    | - 'posts.create'
    | - 'posts.edit'
    | - 'posts.delete'
    |
    | Useful for granting full module access without listing every single permission.
    |
    */
    'enable_wildcards' => true,

    /*
    |--------------------------------------------------------------------------
    | Direct User Permissions
    |--------------------------------------------------------------------------
    |
    | Allow assigning permissions directly to users outside of their assigned roles.
    |
    | Practical Example:
    | $user->givePermissionTo('orders.cancel');
    | This grants 'orders.cancel' to $user even if their assigned role ('Support')
    | does not possess that permission in the 'role_permissions' table.
    |
    */
    'enable_direct_permissions' => true,

    /*
    |--------------------------------------------------------------------------
    | Super Admin Bypass
    |--------------------------------------------------------------------------
    |
    | Users assigned this role slug automatically bypass all permission checks.
    | Set to null or false to disable.
    |
    | Practical Example:
    | If set to 'admin', any user possessing the 'admin' role will pass all
    | @can('any.permission') directives and Gate checks, even if the permission
    | is not explicitly created in the database.
    |
    */
    'super_admin_role' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Primary Key Configuration
    |--------------------------------------------------------------------------
    |
    | Primary key types for ACL tables (roles, permissions) and the foreign key
    | type referencing your application's 'users' table.
    |
    | Options for 'primary_key_type' (ACL Tables):
    | - 'ulid'    : 01ARZ3NDEKTSV4RRFFQ69G5FAV (Sortable 26-character string) [Default]
    | - 'uuid'    : 123e4567-e89b-12d3-a456-426614174000 (36-character string)
    | - 'numeric' : Standard auto-incrementing BigInteger (1, 2, 3...)
    |
    | Options for 'user_foreign_key_type' (Users Table Foreign Key):
    | Must match the primary key column type of your host application's 'users' table.
    | - 'numeric' : Standard Laravel auto-incrementing user IDs (bigIncrements).
    | - 'uuid'    : If your User model uses UUIDs ($table->uuid('id')).
    | - 'ulid'    : If your User model uses ULIDs ($table->ulid('id')).
    |
    */
    'primary_key_type' => 'ulid',
    'user_foreign_key_type' => 'numeric',

    /*
    |--------------------------------------------------------------------------
    | Models Mapping
    |--------------------------------------------------------------------------
    |
    | Eloquent models used by the package. You can override these with custom
    | models extending the package's base classes.
    |
    | Practical Example:
    | If you create App\Models\CustomRole extending Pmb\Acl\Models\Role:
    | 'role' => App\Models\CustomRole::class
    |
    */
    'models' => [
        'role' => Pmb\Acl\Models\Role::class,
        'permission' => Pmb\Acl\Models\Permission::class,
        'user' => config('auth.providers.users.model', App\Models\User::class),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Custom database table names if default names conflict with existing tables.
    |
    */
    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles' => 'user_roles',
        'user_permissions' => 'user_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Permissions Catalog
    |--------------------------------------------------------------------------
    |
    | Structured mapping of application modules and their respective permissions.
    | Used for database seeders, administration UI forms, and documentation.
    |
    | Practical Example (Usage in a Database Seeder):
    | foreach (config('acl.modules') as $module => $permissions) {
    |     foreach ($permissions as $name => $label) {
    |         Permission::firstOrCreate(
    |             ['name' => $name],
    |             ['label' => $label, 'module' => $module]
    |         );
    |     }
    | }
    |
    |
    | Standard actions follow CRUD naming conventions:
    | view / create / edit / delete / restore / manage / *
    |
    */
    'modules' => [

        'users' => [
            'users.view'     => 'View user list and details',
            'users.create'   => 'Create new user accounts',
            'users.edit'     => 'Edit user information',
            'users.delete'   => 'Delete or archive users',
            'users.restore'  => 'Restore soft-deleted user accounts',
            'users.*'        => 'Full user management access',
        ],

        'roles' => [
            'roles.view'     => 'View roles and assigned privileges',
            'roles.create'   => 'Create new system roles',
            'roles.edit'     => 'Modify role structures and permissions',
            'roles.delete'   => 'Delete existing roles',
            'roles.assign'   => 'Assign or revoke roles from users',
            'roles.*'        => 'Full role and permission management',
        ],

        'finance' => [
            'invoices.view'   => 'View customer invoices and receipts',
            'invoices.create' => 'Generate new customer invoices',
            'invoices.edit'   => 'Adjust pending invoice items',
            'invoices.cancel' => 'Cancel or issue credit notes',
            'payments.record' => 'Record incoming and outgoing payments',
            'reports.export'  => 'Export financial and accounting reports',
            'finance.*'       => 'Full control over finance and accounting',
        ],

        'hr' => [
            'employees.view'   => 'View employee directory and profiles',
            'employees.manage' => 'Manage employee contracts and records',
            'attendance.view'  => 'View employee attendance logs',
            'leaves.approve'   => 'Approve or reject leave requests',
            'payroll.process'  => 'Calculate and process payroll statements',
            'hr.*'             => 'Full human resources administration',
        ],

        'crm' => [
            'leads.view'     => 'View sales opportunities list',
            'leads.create'   => 'Create new sales leads',
            'leads.convert'  => 'Convert leads into active customers',
            'deals.manage'   => 'Track and manage the sales pipeline',
            'crm.*'          => 'Full customer relationship management',
        ],

        'projects' => [
            'projects.create'    => 'Create new company projects',
            'tasks.assign'       => 'Create and assign tasks to collaborators',
            'tasks.update'       => 'Update task status and progress',
            'timetracking.view'  => 'View recorded time tracking logs',
            'projects.*'         => 'Full project management access',
        ],

        'ecommerce' => [
            'products.manage' => 'Manage product catalog and inventory',
            'orders.view'     => 'View order details and status',
            'orders.process'  => 'Process, fulfill, and ship orders',
            'orders.cancel'   => 'Cancel and refund customer orders',
            'ecommerce.*'      => 'Full e-commerce store management',
        ],

        'blog' => [
            'posts.view'     => 'Read and view published blog posts',
            'posts.create'   => 'Draft new blog posts',
            'posts.edit'     => 'Edit existing blog posts',
            'posts.publish'  => 'Publish or unpublish blog posts',
            'posts.delete'   => 'Delete blog posts',
            'posts.*'        => 'Full blog management access',
        ],

        'school' => [
            'students.enroll' => 'Enroll new students',
            'grades.manage'   => 'Record and validate academic grades',
            'courses.assign'  => 'Assign courses to instructors',
            'academic.*'      => 'Full academic system management',
        ],

        'settings' => [
            'settings.view'   => 'View system configuration settings',
            'settings.edit'   => 'Modify global application settings',
            'logs.view'       => 'View audit trails and activity logs',
            'backups.manage'  => 'Generate and restore database backups',
            'settings.*'      => 'Full access to system settings',
        ],

    ],
];