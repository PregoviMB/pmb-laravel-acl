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
                            {--publish : Publie uniquement les ressources (configuration et migrations)}
                            {--config : Publie et configure uniquement le fichier de configuration}
                            {--migrations : Publie et exécute uniquement les migrations}';

    protected $description = 'Interactively install and configure PMB ACL package';

    protected $help = 'This command will guide you through the installation and configuration of the PMB ACL package.';

    public function handle(): int
    {
        // 1. Force la prise en compte des couleurs ANSI et configure les styles Symfony
        $this->setupCustomStyles();

        $isForce = (bool) $this->option('force');
        $onlyConfig = (bool) $this->option('config');
        $onlyMigrations = (bool) $this->option('migrations');
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

        // ----------------------------------------------------
        // GÉNÉRATION DES RESSOURCES
        // ----------------------------------------------------
        $this->line(' <cyan>⚙  Génération et publication des ressources</cyan>');
        $this->line('');

        spin(
            callback: function () use ($modeChoice, $enableWildcards, $idType, $isForce) {
                $this->publishConfig($isForce);
                $this->updateConfigFile($modeChoice, $enableWildcards, $idType, $idType);
                $this->publishMigrationStub();
            },
            message: ' Écriture des fichiers de configuration et stubs...'
        );

        $this->line('  <green>✓</green> Configuration générée : <gray>config/acl.php</gray>');
        $this->line('  <green>✓</green> Migration stubs générés : <gray>database/migrations/</gray>');
        $this->line('');

        if ($onlyPublish) {
            $this->renderDivider();
            outro(' Publication des ressources terminée (--publish).');
            return Command::SUCCESS;
        }

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
        $this->callSilent('vendor:publish', [
            '--provider' => 'Pmb\Acl\AclServiceProvider',
            '--tag'      => 'acl-config',
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

            $content = preg_replace("/'mode' => '.*'/", "'mode' => '{$mode}'", $content);
            $content = preg_replace("/'enable_wildcards' => (true|false)/", "'enable_wildcards' => " . ($wildcards ? 'true' : 'false'), $content);
            $content = preg_replace("/'primary_key_type' => '.*'/", "'primary_key_type' => '{$pkType}'", $content);
            $content = preg_replace("/'user_foreign_key_type' => '.*'/", "'user_foreign_key_type' => '{$userFkType}'", $content);

            File::put($configPath, $content);
        }
    }
}