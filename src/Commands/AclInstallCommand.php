<?php

namespace Pmb\Acl\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class AclInstallCommand extends Command
{
    protected $signature = 'acl:install
                            {--F|force : Écrase les fichiers existants et force l\'exécution des migrations}
                            {--publish : Publie toutes les ressources (configuration, migrations, modèles et traits)}
                            {--config : Publie et configure uniquement le fichier de configuration}
                            {--migrations : Publie et exécute uniquement les migrations}
                            {--models : Publie uniquement les modèles (Role, Permission)}
                            {--traits : Publie uniquement les traits (HasAcl)}';

    protected $description = 'Interactively install and configure PMB ACL package';

    protected $help = 'This command will guide you through the installation and configuration of the PMB ACL package.';

    public function handle(): int
    {
        // 1. Force la prise en compte des couleurs ANSI et configure les styles Symfony
        $this->setupCustomStyles();

        $isForce = (bool) $this->option('force');
        $onlyConfig = (bool) $this->option('config');
        $onlyMigrations = (bool) $this->option('migrations');
        $onlyModels = (bool) $this->option('models');
        $onlyTraits = (bool) $this->option('traits');
        $onlyPublish = (bool) $this->option('publish');

        // Affichage de l'en-tête ASCII
        $this->renderHeader();

        // Traitement du drapeau --config seul
        if ($onlyConfig) {
            $this->publishConfig($isForce);
            $this->renderDivider();
            $this->line('  <green>✓</green> <bold>Configuration publiée avec succès.</bold>');
            $this->line('');
            return Command::SUCCESS;
        }

        // Traitement du drapeau --migrations seul
        if ($onlyMigrations) {
            $this->publishAndRunMigrations($isForce);
            $this->renderDivider();
            $this->line('  <green>✓</green> <bold>Migrations générées et exécutées avec succès.</bold>');
            $this->line('');
            return Command::SUCCESS;
        }

        // Traitement du drapeau --models seul
        if ($onlyModels) {
            $this->publishModels($isForce);
            $this->renderDivider();
            $this->line('  <green>✓</green> <bold>Modèles publiés avec succès dans app/Models.</bold>');
            $this->line('');
            return Command::SUCCESS;
        }

        // Traitement du drapeau --traits seul
        if ($onlyTraits) {
            $this->publishTraits($isForce);
            $this->renderDivider();
            $this->line('  <green>✓</green> <bold>Traits publiés avec succès dans app/Traits.</bold>');
            $this->line('');
            return Command::SUCCESS;
        }

        // Traitement du drapeau --publish seul (publie TOUT sans passer par les questions interactives)
        if ($onlyPublish) {
            $this->publishConfig($isForce);
            $this->publishMigrationStub();
            $this->publishModels($isForce);
            $this->publishTraits($isForce);
            $this->renderDivider();
            $this->line('  <green>✓</green> <bold>Toutes les ressources (config, migrations, modèles, traits) ont été publiées avec succès.</bold>');
            $this->line('');
            return Command::SUCCESS;
        }

        // ----------------------------------------------------
        // ÉTAPE 1 : Configuration du mode d'autorisation
        // ----------------------------------------------------
        $this->renderSectionHeader('1/2', 'Configuration du mode d\'autorisation');

        $modeChoice = select(
            label: __('pmb-acl::messages.prompt_mode'),
            options: [
                'hybrid' => __('pmb-acl::messages.mode_hybrid'),
                'rbac'   => __('pmb-acl::messages.mode_rbac'),
                'pbac'   => __('pmb-acl::messages.mode_pbac'),
            ],
            default: 'hybrid'
        );

        $enableWildcards = confirm(
            label: __('pmb-acl::messages.prompt_wildcards'),
            default: true
        );

        $this->line('');

        // ----------------------------------------------------
        // ÉTAPE 2 : Architecture des identifiants (IDs)
        // ----------------------------------------------------
        $this->renderSectionHeader('2/2', 'Architecture des identifiants');

        $userModelClass = config('acl.models.user', config('auth.providers.users.model', 'App\Models\User'));
        $detectedUserKeyType = $this->detectUserKeyType();
        $validTypes = ['numeric', 'uuid', 'ulid'];

        if (in_array($detectedUserKeyType, $validTypes, true)) {
            $idType = $detectedUserKeyType;
            $this->line("  <green>✓</green> Type d'ID détecté pour <bold>{$userModelClass}</bold> : <badge> " . strtoupper($idType) . ' </badge>');
        } else {
            $idType = select(
                label: __('pmb-acl::messages.prompt_id_type'),
                options: [
                    'numeric' => 'Auto-Incrément (BigInteger - Classique Laravel)',
                    'ulid'    => 'ULID (Recommandé - Triable et performant)',
                    'uuid'    => 'UUID v4 (Universellement unique)',
                ],
                default: 'numeric'
            );
        }

        $this->line('');
        $this->renderDivider();

        // Demande optionnelle pour publier les modèles et traits
        $publishModelsChoice = confirm(
            label: __('pmb-acl::messages.prompt_publish_models'),
            default: false
        );

        $publishTraitsChoice = confirm(
            label: __('pmb-acl::messages.prompt_publish_traits'),
            default: false
        );

        $this->line('');
        $this->renderDivider();

        // ----------------------------------------------------
        // GÉNÉRATION DES RESSOURCES
        // ----------------------------------------------------
        $this->line(' <cyan>⚙  Génération et publication des ressources</cyan>');
        $this->line('');

        spin(
            callback: function () use ($modeChoice, $enableWildcards, $idType, $publishModelsChoice, $publishTraitsChoice, $isForce) {
                $this->publishConfig($isForce);

                $this->updateConfigFile($modeChoice, $enableWildcards, $idType, $idType);
                $this->publishMigrationStub();

                if ($publishModelsChoice) {
                    $this->publishModels($isForce);
                }

                if ($publishTraitsChoice) {
                    $this->publishTraits($isForce);
                }
            },
            message: ' Écriture des fichiers de configuration et stubs...'
        );

        $this->line('  <green>✓</green> Configuration générée : <gray>config/acl.php</gray>');
        $this->line('  <green>✓</green> Migration stubs générés : <gray>database/migrations/</gray>');
        if ($publishModelsChoice) {
            $this->line('  <green>✓</green> Modèles publiés : <gray>app/Models/</gray>');
        }
        if ($publishTraitsChoice) {
            $this->line('  <green>✓</green> Traits publiés : <gray>app/Traits/</gray>');
        }
        $this->line('');

        // ----------------------------------------------------
        // EXÉCUTION DES MIGRATIONS
        // ----------------------------------------------------
        $shouldMigrate = $isForce || confirm(
            label: __('pmb-acl::messages.prompt_run_migrations'),
            default: true
        );

        if ($shouldMigrate) {
            $this->line('');
            $this->runMigrations($isForce);
        } else {
            $this->line('');
            $this->line('  <yellow>!</yellow> Exécutez <bold>php artisan migrate</bold> pour finaliser l\'installation.');
        }

        $this->renderDivider();
        outro(__('pmb-acl::messages.install_success'));

        return Command::SUCCESS;
    }

    /**
     * Initialise les styles personnalisés Symfony Console sur l'OutputFormatter.
     */
    protected function setupCustomStyles(): void
    {
        $this->output->setDecorated(true);
        $formatter = $this->output->getFormatter();

        $formatter->setStyle('cyan', new OutputFormatterStyle('cyan', null, ['bold']));
        $formatter->setStyle('gray', new OutputFormatterStyle('gray'));
        $formatter->setStyle('green', new OutputFormatterStyle('green', null, ['bold']));
        $formatter->setStyle('yellow', new OutputFormatterStyle('yellow', null, ['bold']));
        $formatter->setStyle('bold', new OutputFormatterStyle('white', null, ['bold']));
        $formatter->setStyle('badge', new OutputFormatterStyle('black', 'cyan', ['bold']));
    }

    /**
     * Affiche l'en-tête ASCII avec les balises personnalisées.
     */
    protected function renderHeader(): void
    {
        $this->line('');
        $this->line('<cyan>.. db .. 88 . . .d8888 . .8888b .8b .. d8 8888b </cyan>');
        $this->line('<cyan>..dPYb . 88 . .d8 . . . . 88__8P 88b .d88 88__8P </cyan>');
        $this->line('<cyan>.dP__Yb. 88 . .Y8 . . . . 88--- .88YbdP88 88--8b </cyan>');
        $this->line('<cyan>dP""""Yb 88ood8 .Y8888 . .88 . . 88 YY 88 88oo8P </cyan>');
        $this->line('<gray> ────────────────────────────────────────────────────────────</gray>');
        $this->line(' <bold>Laravel ACL Package</bold> <gray>│ PMB Authorization System</gray>');
        $this->line('<gray> ────────────────────────────────────────────────────────────</gray>');
        $this->line('');
    }

    /**
     * Affiche un titre de section avec badge.
     */
    protected function renderSectionHeader(string $step, string $title): void
    {
        $this->line(" <badge> {$step} </badge> <bold>{$title}</bold>");
        $this->line('');
    }

    /**
     * Ligne de séparation grise.
     */
    protected function renderDivider(): void
    {
        $this->line('<gray> ────────────────────────────────────────────────────────────</gray>');
        $this->line('');
    }

    /**
     * Détecte le type de clé primaire du modèle User.
     */
    protected function detectUserKeyType(): string
    {
        $userClass = config('acl.models.user', config('auth.providers.users.model', 'App\Models\User'));

        if (!class_exists($userClass)) {
            return 'numeric';
        }

        try {
            $user = new $userClass();
            $traits = class_uses_recursive($userClass);

            if (in_array(HasUlids::class, $traits, true)) {
                return 'ulid';
            }

            if (in_array(HasUuids::class, $traits, true)) {
                return 'uuid';
            }

            if ($user->getKeyType() === 'string') {
                return 'uuid';
            }

            $table = $user->getTable();
            $primaryKey = $user->getKeyName();

            if (Schema::hasTable($table)) {
                $columnType = Schema::getColumnType($table, $primaryKey);

                if (in_array($columnType, ['uuid', 'guid'], true)) {
                    return 'uuid';
                }

                if (in_array($columnType, ['string', 'char'], true)) {
                    return 'ulid';
                }
            }
        } catch (\Throwable $e) {
            return 'numeric';
        }

        return 'numeric';
    }

    protected function publishConfig(bool $force = false): void
    {
        $configPath = config_path('acl.php');

        // If config already exists in host app, gracefully merge missing sections without overriding user's modules
        if (File::exists($configPath) && !$force) {
            $this->mergeMissingConfigSections($configPath);
            return;
        }

        $this->callSilent('vendor:publish', [
            '--provider' => 'Pmb\Acl\AclServiceProvider',
            '--tag'      => 'acl-config',
            '--force'    => $force,
        ]);
    }

    /**
     * Appends missing configuration blocks ('tenant', 'cache') to an existing published config/acl.php
     * so custom user-defined modules and settings are NEVER overwritten or lost.
     */
    protected function mergeMissingConfigSections(string $configPath): void
    {
        $content = File::get($configPath);
        $modified = false;

        // Check and append 'cache' block if missing
        if (!str_contains($content, "'cache' =>")) {
            $cacheBlock = <<<'PHP'

    /*
    |--------------------------------------------------------------------------
    | Permissions Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('ACL_CACHE_ENABLED', true),
        'ttl'     => env('ACL_CACHE_TTL', 86400),
        'key'     => env('ACL_CACHE_KEY', 'pmb_acl_user_permissions'),
        'store'   => env('ACL_CACHE_STORE', null),
    ],

PHP;
            // Insert before 'models' or 'modules'
            if (str_contains($content, "'models' =>")) {
                $content = str_replace("'models' =>", $cacheBlock . "    'models' =>", $content);
                $modified = true;
            }
        }

        // Check and append 'tenant' block if missing
        if (!str_contains($content, "'tenant' =>") && !str_contains($content, "'teams' =>")) {
            $tenantBlock = <<<'PHP'

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy / Scopes Configuration (Contextual RBAC)
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'enabled'     => env('ACL_TENANT_ENABLED', false),
        'foreign_key' => env('ACL_TENANT_FOREIGN_KEY', 'tenant_id'),
        'type'        => env('ACL_TENANT_KEY_TYPE', 'numeric'),
    ],

PHP;
            if (str_contains($content, "'primary_key_type' =>")) {
                $content = str_replace("'primary_key_type' =>", $tenantBlock . "    'primary_key_type' =>", $content);
                $modified = true;
            }
        }

        if ($modified) {
            File::put($configPath, $content);
        }
    }

    protected function publishModels(bool $force = false): void
    {
        $this->callSilent('vendor:publish', [
            '--provider' => 'Pmb\Acl\AclServiceProvider',
            '--tag'      => 'acl-models',
            '--force'    => $force,
        ]);
    }

    protected function publishTraits(bool $force = false): void
    {
        $this->callSilent('vendor:publish', [
            '--provider' => 'Pmb\Acl\AclServiceProvider',
            '--tag'      => 'acl-traits',
            '--force'    => $force,
        ]);
    }

    protected function publishMigrationStub(): void
    {
        $existingFiles = File::glob(database_path('migrations/*_create_acl_tables.php'));

        if (!empty($existingFiles)) {
            $targetPath = $existingFiles[0];
        } else {
            $timestamp = date('Y_m_d_His');
            $targetPath = database_path("migrations/{$timestamp}_create_acl_tables.php");
        }

        $stubPath = __DIR__ . '/../../database/migrations/create_acl_tables.php.stub';

        if (File::exists($stubPath)) {
            File::copy($stubPath, $targetPath);
        }
    }

    protected function runMigrations(bool $force = false): void
    {
        spin(
            callback: fn () => $this->callSilent('migrate', [
                '--force' => $force,
            ]),
            message: ' Exécution des migrations en cours...'
        );
        $this->line('  <green>✓</green> <bold>Base de données mise à jour avec succès.</bold>');
        $this->line('');
    }

    protected function publishAndRunMigrations(bool $force = false): void
    {
        $this->publishMigrationStub();
        $this->runMigrations($force);
    }

    protected function updateConfigFile(string $mode, bool $wildcards, string $pkType, string $userFkType): void
    {
        $configPath = config_path('acl.php');

        if (File::exists($configPath)) {
            $content = File::get($configPath);

            $content = preg_replace("/'mode' => .*/", "'mode' => env('ACL_MODE', '{$mode}'),", $content);
            $content = preg_replace("/'enable_wildcards' => (true|false)/", "'enable_wildcards' => " . ($wildcards ? 'true' : 'false'), $content);


            // pattern to match 'primary_key_type' => env('ACL_PRIMARY_KEY_TYPE', 'numeric'),
            $content = preg_replace("/'primary_key_type' => .*/", "'primary_key_type' => env('ACL_PRIMARY_KEY_TYPE', '{$pkType}'),", $content);


            // pattern to match 'user_foreign_key_type' => env('ACL_USER_FK_TYPE', 'numeric'),
            $content = preg_replace("/'user_foreign_key_type' => .*/", "'user_foreign_key_type' => env('ACL_USER_FK_TYPE', '{$userFkType}'),", $content);

            File::put($configPath, $content);
        }
    }
}