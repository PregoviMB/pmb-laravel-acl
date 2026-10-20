<?php

/**
 * @author:  Pregovi MB
 * @package Pmb\Acl
 * @license MIT
 * @link https://github.com/pregovimb/laravel-acl
 */

namespace Pmb\Acl;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Pmb\Acl\Commands\AclInstallCommand;
use Pmb\Acl\Commands\AclShowCommand;
use Pmb\Acl\Commands\AclSyncCommand;
use Pmb\Acl\Http\Middlewares\PermissionMiddleware;
use Pmb\Acl\Http\Middlewares\RoleMiddleware;

class AclServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Deep recursive merge to ensure new config sections ('tenant', 'cache', etc.)
        // are seamlessly available even if the user published an earlier version of config/acl.php
        $this->mergeConfigRecursively(__DIR__ . '/../config/acl.php', 'acl');

        // Bind the AclManager singleton
        $this->app->singleton('pmb-acl', function () {
            return new AclManager();
        });
    }

    /**
     * Recursively merges the package configuration with the application's configuration.
     * Preserves custom host application values (like custom modules, custom tables, mode)
     * while guaranteeing new package configuration defaults are accessible.
     *
     * @param string $path
     * @param string $key
     * @return void
     */
    protected function mergeConfigRecursively(string $path, string $key): void
    {
        $packageConfig = require $path;
        $hostConfig = $this->app->make('config')->get($key, []);

        $merged = $this->deepMergeArray($packageConfig, $hostConfig);

        $this->app->make('config')->set($key, $merged);
    }

    /**
     * Helper to recursively merge two configuration arrays.
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    protected function deepMergeArray(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMergeArray($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublications();
        $this->registerCommands();
        $this->registerMiddlewares();
        $this->registerGateBypass();
        $this->registerModelObservers();
    }

    /**
     * Register model observers to invalidate ACL caches when roles or permissions change.
     *
     * @return void
     */
    protected function registerModelObservers(): void
    {
        $roleModel = config('acl.models.role', \Pmb\Acl\Models\Role::class);
        $permissionModel = config('acl.models.permission', \Pmb\Acl\Models\Permission::class);

        if (class_exists($roleModel)) {
            $roleModel::saved(function ($role) {
                // When a role is updated or saved, flush cache for all users attached to it
                if (method_exists($role, 'users')) {
                    $role->users()->each(function ($user) {
                        if (method_exists($user, 'flushUserAclCache')) {
                            $user->flushUserAclCache();
                        }
                    });
                }
            });

            $roleModel::deleted(function ($role) {
                if (method_exists($role, 'users')) {
                    $role->users()->each(function ($user) {
                        if (method_exists($user, 'flushUserAclCache')) {
                            $user->flushUserAclCache();
                        }
                    });
                }
            });
        }

        if (class_exists($permissionModel)) {
            $permissionModel::saved(function ($permission) {
                if (method_exists($permission, 'users')) {
                    $permission->users()->each(function ($user) {
                        if (method_exists($user, 'flushUserAclCache')) {
                            $user->flushUserAclCache();
                        }
                    });
                }
                if (method_exists($permission, 'roles')) {
                    $permission->roles()->each(function ($role) {
                        if (method_exists($role, 'users')) {
                            $role->users()->each(function ($user) {
                                if (method_exists($user, 'flushUserAclCache')) {
                                    $user->flushUserAclCache();
                                }
                            });
                        }
                    });
                }
            });
        }
    }

    /**
     * Register the package's publications 
     * (config, translations, models, and traits).
     *
     * @return void
     */
    protected function registerPublications(): void
    {
        // load the translations from the package's resources/lang directory
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'pmb-acl');

        // if the application is running in the console, publish the resources
        if ($this->app->runningInConsole()) {
            // Configuration
            $this->publishes([
                __DIR__ . '/../config/acl.php' => config_path('acl.php'),
            ], 'acl-config');

            // Translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/pmb-acl'),
            ], 'acl-translations');

            // Models
            $this->publishes([
                __DIR__ . '/Models/Role.php' => app_path('Models/Role.php'),
                __DIR__ . '/Models/Permission.php' => app_path('Models/Permission.php'),
            ], 'acl-models');

            // Traits
            $this->publishes([
                __DIR__ . '/Traits/HasAcl.php' => app_path('Traits/HasAcl.php'),
                __DIR__ . '/Traits/HasRolesAndPermissions.php' => app_path('Traits/HasRolesAndPermissions.php'),
            ], 'acl-traits');

            // Migrations (including tenant update migration)
            $this->publishes([
                __DIR__ . '/../database/migrations/add_tenant_support_to_acl_tables.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 1) . '_add_tenant_support_to_acl_tables.php'),
            ], 'acl-tenant-migrations');
        }
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AclInstallCommand::class,
                AclSyncCommand::class,
                AclShowCommand::class,
            ]);
        }
    }

    /**
     * Register the package's middlewares.
     *
     * @return void
     */
    protected function registerMiddlewares(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
    }

    /**
     * Register the gate bypass for users with specific permissions.
     *
     * @return void
     */
    protected function registerGateBypass(): void
    {
        Gate::before(function ($user, $ability) {

            $superAdminRole = config('acl.super_admin_role');

            if ($superAdminRole && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
                return true;
            }
            
            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($ability) ? true : null;
            }
        });
    }
}