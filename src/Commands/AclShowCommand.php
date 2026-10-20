<?php

namespace Pmb\Acl\Commands;

use Illuminate\Console\Command;

class AclShowCommand extends Command
{
    protected $signature = 'acl:show {user : The ID, email or name of the user}';

    protected $description = 'Inspect roles and permissions assigned to a specific user';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $userModel = config('acl.models.user', config('auth.providers.users.model', 'App\Models\User'));

        if (!class_exists($userModel)) {
            $this->error("User model [{$userModel}] does not exist.");
            return Command::FAILURE;
        }

        $user = $userModel::where((new $userModel)->getKeyName(), $userIdentifier)
            ->orWhere('email', $userIdentifier)
            ->orWhere('name', $userIdentifier)
            ->first();

        if (!$user) {
            $this->error("User [{$userIdentifier}] not found.");
            return Command::FAILURE;
        }

        $this->line('');
        $this->line("<fg=cyan;options=bold>ACL Audit for User:</> <options=bold>{$user->name}</> ({$user->email}) [ID: {$user->getKey()}]");
        $this->line(str_repeat('─', 60));

        // 1. Roles
        $roles = method_exists($user, 'roles') ? $user->roles : collect();
        $this->line('<fg=yellow;options=bold>Assigned Roles (' . $roles->count() . '):</>');
        if ($roles->isEmpty()) {
            $this->line('  <fg=gray>(No roles assigned)</>');
        } else {
            $roleRows = $roles->map(fn ($r) => [$r->name, $r->slug ?? '-', $r->label ?? '-'])->all();
            $this->table(['Role Name', 'Slug', 'Label'], $roleRows);
        }
        $this->line('');

        // 2. Direct Permissions
        $directPermissions = method_exists($user, 'directPermissions') ? $user->directPermissions : collect();
        $this->line('<fg=yellow;options=bold>Direct Permissions (' . $directPermissions->count() . '):</>');
        if ($directPermissions->isEmpty()) {
            $this->line('  <fg=gray>(No direct permissions)</>');
        } else {
            $directRows = $directPermissions->map(fn ($p) => [$p->module ?? '-', $p->name, $p->label ?? '-'])->all();
            $this->table(['Module', 'Permission', 'Label'], $directRows);
        }
        $this->line('');

        // 3. Consolidated Effective Permissions
        $allPermissions = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions() : collect();
        $this->line('<fg=green;options=bold>Consolidated Effective Permissions (' . $allPermissions->count() . '):</>');
        if ($allPermissions->isEmpty()) {
            $this->line('  <fg=gray>(No effective permissions)</>');
        } else {
            $allRows = $allPermissions->map(function ($p) use ($directPermissions) {
                $isDirect = $directPermissions->contains('id', $p->id);
                return [
                    $p->module ?? '-',
                    $p->name,
                    $isDirect ? '<fg=cyan>Direct (PBAC)</>' : '<fg=magenta>Via Role (RBAC)</>',
                ];
            })->all();
            $this->table(['Module', 'Permission', 'Source'], $allRows);
        }
        $this->line('');

        return Command::SUCCESS;
    }
}

