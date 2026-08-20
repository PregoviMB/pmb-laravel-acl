<?php

namespace Pmb\Acl\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AclInstallCommand extends Command
{
    protected $signature = 'acl:install';
    protected $description = 'Interactively install and configure PMB ACL package';

    public function handle(): int
    {
        $this->info(__('pmb-acl::messages.install_welcome'));

        // 1. Authorization Mode Choice
        $modeChoice = $this->choice(
            __('pmb-acl::messages.prompt_mode'),
            [
                'hybrid' => __('pmb-acl::messages.mode_hybrid'),
                'rbac'   => __('pmb-acl::messages.mode_rbac'),
                'pbac'   => __('pmb-acl::messages.mode_pbac'),
            ],
            'hybrid'
        );

        // 2. Wildcard Toggle
        $enableWildcards = $this->confirm(__('pmb-acl::messages.prompt_wildcards'), true);

        // 3. Primary Key Type
        $pkType = $this->choice(
            __('pmb-acl::messages.prompt_pk'),
            ['ulid', 'uuid', 'numeric'],
            'ulid'
        );

        // 4. User Foreign Key Type
        $userFkType = $this->choice(
            __('pmb-acl::messages.prompt_user_fk'),
            ['numeric', 'uuid', 'ulid'],
            'numeric'
        );

        // 5. Publish Configuration
        $this->info(__('pmb-acl::messages.publishes_config'));
        $this->call('vendor:publish', [
            '--provider' => 'Pmb\Acl\AclServiceProvider',
            '--tag'      => 'acl-config',
            '--force'    => true,
        ]);

        // 6. Update Configuration File Options
        $this->updateConfigFile($modeChoice, $enableWildcards, $pkType, $userFkType);

        // 7. Publish Migration Stub
        $this->info(__('pmb-acl::messages.publishes_migrations'));
        $this->publishMigrationStub();

        // 8. Ask to Run Migrations
        if ($this->confirm(__('pmb-acl::messages.prompt_run_migrations'), true)) {
            $this->call('migrate');
        }

        $this->info(__('pmb-acl::messages.install_success'));

        return Command::SUCCESS;
    }

    protected function updateConfigFile(string $mode, bool $wildcards, string $pkType, string $userFkType): void
    {
        $configPath = config_path('acl.php');

        if (File::exists($configPath)) {
            $content = File::get($configPath);

            $content = preg_replace("/'mode' => '.*'/", "'mode' => '{$mode}'", $content);
            $content = preg_replace("/'enable_wildcards' => (true|false)/", "'enable_wildcards' => " . ($wildcards ? 'true' : 'false'), $content);
            $content = preg_replace("/'primary_key_type' => '.*'/", "'primary_key_type' => '{$pkType}'", $content);
            $content = preg_replace("/'user_foreign_key_type' => '.*'/", "'user_foreign_key_type' => '{$userFkType}'", $content);

            File::put($configPath, $content);
        }
    }

    protected function publishMigrationStub(): void
    {
        $timestamp = date('Y_m_d_His');
        $targetPath = database_path("migrations/{$timestamp}_create_acl_tables.php");
        $stubPath = __DIR__ . '/../../database/migrations/create_acl_tables.php.stub';

        if (File::exists($stubPath)) {
            File::copy($stubPath, $targetPath);
        }
    }
}