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
        // Merge the package configuration with the application's configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/acl.php', 'acl');
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
    }

    /**
     * Register the package's publications 
     * (config and translations).
     *
     * @return void
     */
    protected function registerPublications(): void
    {

        // load the translations from the package's resources/lang directory
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'pmb-acl');


        // if the application is running in the console, publish the configuration and translation files

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/acl.php' => config_path('acl.php'),
            ], 'acl-config');

            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/pmb-acl'),
            ], 'acl-translations');
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