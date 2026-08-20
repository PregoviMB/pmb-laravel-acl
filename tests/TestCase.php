<?php

namespace Pmb\Acl\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pmb\Acl\AclServiceProvider;
use Pmb\Acl\Tests\Models\User; 

abstract class TestCase extends Orchestra
{

    // User instance for testing
    public User $user;
    /**
     * Get package providers for the test environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {        
        return [
            AclServiceProvider::class,
        ];
    }

    /**
     * Define database migrations for the test environment.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        // 1. Génère la table 'users'
        $this->loadLaravelMigrations();

        // 2. Exécute manuellement le fichier .stub de migration ACL
        $migration = include __DIR__ . '/../database/migrations/create_acl_tables.php.stub';
        $migration->up();
    }

    /**
     * Define the environment setup for the test application.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('database.default', 'testing');
        $app->make('config')->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Forcer le type de clé primaire à 'numeric' pour l'environnement de test
        $app->make('config')->set('acl.primary_key_type', 'ulid');
        $app->make('config')->set('acl.user_foreign_key_type', 'ulid');
        $app->make('config')->set('acl.models.user', User::class);

        $app->make('config')->set('acl.tables', [
            'roles' => 'mb_roles',
            'permissions' => 'mb_permissions',
            'user_roles' => 'user_roles',
            'user_permissions' => 'user_permissions',
            'role_permissions' => 'role_permissions',
        ]);

        $app->make('config')->set('acl.enable_direct_permissions', true);
        $app->make('config')->set('acl.super_admin_role', ['super-admin']);
        

    }

    /**
     * Helper pour créer un utilisateur de test avec mot de passe
     */
    public function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'     => 'Marley Pregovi MB',
            'email'    => 'marley@xg-consulting.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }
}