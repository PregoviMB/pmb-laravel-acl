<?php

namespace Pmb\Acl\Commands;

use Illuminate\Console\Command;
use Pmb\Acl\Models\Permission;

class AclSyncCommand extends Command
{
    protected $signature = 'acl:sync
                            {--prune : Supprime les permissions en base qui ne sont plus dans le catalogue acl.php}';

    protected $description = 'Synchronize application permissions catalog from config/acl.php to database';

    public function handle(): int
    {
        $modules = config('acl.modules', []);

        if (empty($modules)) {
            $this->warn('No modules configured in config/acl.php.');
            return Command::SUCCESS;
        }

        $permissionModel = config('acl.models.permission', Permission::class);
        $totalCreated = 0;
        $totalUpdated = 0;
        $activePermissionNames = [];

        $this->line('');
        $this->line('<fg=cyan;options=bold>Synchronisation du catalogue des permissions...</>');
        $this->line('');

        $rows = [];

        foreach ($modules as $module => $permissions) {
            foreach ($permissions as $name => $label) {
                $activePermissionNames[] = $name;

                $permission = $permissionModel::where('name', $name)->first();

                if (!$permission) {
                    $permission = $permissionModel::create([
                        'name'   => $name,
                        'label'  => $label,
                        'module' => $module,
                    ]);
                    $totalCreated++;
                    $rows[] = [$module, $name, $label, '<fg=green>Created</>'];
                } else {
                    $dirty = false;
                    if ($permission->label !== $label || $permission->module !== $module) {
                        $permission->label = $label;
                        $permission->module = $module;
                        $permission->save();
                        $totalUpdated++;
                        $dirty = true;
                    }
                    $rows[] = [$module, $name, $label, $dirty ? '<fg=yellow>Updated</>' : '<fg=gray>Synced</>'];
                }
            }
        }

        // Handle pruning if requested
        $totalPruned = 0;
        if ($this->option('prune')) {
            $orphans = $permissionModel::whereNotIn('name', $activePermissionNames)->get();
            foreach ($orphans as $orphan) {
                $rows[] = [$orphan->module ?? '-', $orphan->name, $orphan->label ?? '-', '<fg=red>Deleted</>'];
                $orphan->delete();
                $totalPruned++;
            }
        }

        $this->table(['Module', 'Permission (Name)', 'Label', 'Status'], $rows);
        $this->line('');

        $this->info("Permissions synchronisées avec succès ! [Créées: {$totalCreated}, Mises à jour: {$totalUpdated}, Supprimées: {$totalPruned}]");
        $this->line('');

        return Command::SUCCESS;
    }
}

