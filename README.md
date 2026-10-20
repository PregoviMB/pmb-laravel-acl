# Laravel ACL (`pmb/acl`)

<!-- [![Latest Version on Packagist](https://img.shields.io/packagist/v/pmb/acl.svg?style=flat-square)](https://packagist.org/packages/pmb/acl)
[![Build Status](https://img.shields.io/github/actions/workflow/status/pmb/acl/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/pmb/acl/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/pmb/acl.svg?style=flat-square)](https://packagist.org/packages/pmb/acl)
[![License](https://img.shields.io/packagist/l/pmb/acl.svg?style=flat-square)](LICENSE.md) -->

Un package de gestion des habilitations d'accès (**RBAC** - *Role-Based Access Control* & **PBAC** - *Permission-Based Access Control*) puissant, flexible et haute performance pour Laravel.

Conçu pour s'adapter à toutes les architectures de base de données (ULID, UUID, Auto-incrément), il intègre la prise en charge des permissions joker (*wildcards* `*`), le bypass automatique pour les Super Administrateurs, ainsi qu'un modèle de configuration dynamique permettant de générer vos matrices de droits dans vos back-offices (Blade, Livewire, Inertia/Vue).

---

## Sommaire

- [Fonctionnalités Clés](#-fonctionnalités-clés)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration-configaclphp)
- [Préparation des Modèles](#-préparation-des-modèles)
- [Utilisation du Trait `HasAcl`](#-utilisation-du-trait-hasacl)
  - [Gestion des Rôles](#gestion-des-rôles)
  - [Gestion des Permissions](#gestion-des-permissions)
  - [Bypass Super Admin & Wildcards](#bypass-super-admin--wildcards)
- [Génération Dynamique d'Interfaces Administration (UI Matrix)](#-génération-dynamique-dinterfaces-administration-ui-matrix)
  - [1. Contrôleur de Gestion des Rôles (`RoleController`)](#1-contrôleur-de-gestion-des-rôles-rolecontroller)
  - [2. Formulaire Blade & JS Matrice de Permissions](#2-formulaire-blade--js-matrice-de-permissions)
  - [3. Composant Réactif Livewire](#3-composant-réactif-livewire)
- [Synchronisation Automatique via Seeder / Command](#-synchronisation-automatique-via-seeder--command)
- [Protection des Routes & Directives Blade](#-protection-des-routes--directives-blade)
- [Exécution des Tests](#-exécution-des-tests)
- [Licence](#-licence)

---

## Fonctionnalités Clés

- **Flexibilité des Clés Primaires (PK/FK) :** Choisissez entre `ulid`, `uuid` ou `numeric` (BigInteger) sans modifier le code source du package.
- **Modèle d'Accès Hybride (RBAC & PBAC) :**
  - **RBAC :** Attribuez des rôles aux utilisateurs (`admin`, `editor`, `manager`).
  - **PBAC :** Assignez des permissions directement à un utilisateur spécifique indépendamment de son rôle.
- **Résolution des Joker / Wildcards (`*`) :** Prise en charge des motifs hiérarchiques comme `posts.*`, `users.draft.*` ou `*.read` via `Str::is()`.
- **Super Admin Bypass :** Désignation d'un rôle d'administration suprême configuré pour contourner automatiquement toutes les vérifications.
- **Catalogue Dynamique par Modules :** Centralisez vos définitions de permissions dans la configuration pour alimenter automatiquement vos interfaces d'administration.
- **Haute Performance :** Chargement optimisé des relations et réduction du nombre de requêtes SQL.
- **Architecture Moderne & Propre :** Entièrement testé avec Pest PHP et Orchestra Testbench.

---

## Prérequis

- **PHP :** 8.2 ou supérieur
- **Laravel :** 10.x, 11.x ou 12.x
- **Extension PDO SQLite / MySQL / PostgreSQL**

---

## Installation

1. **Installer le package via Composer :**

```bash
composer require pmb/acl

```

2. **Installation et configuration automatique :**

```bash
php artisan acl:install
```

Cette commande interactive vous permet de :
- Choisir le mode d'autorisation (`hybrid`, `rbac`, `pbac`)
- Activer ou désactiver les wildcards (`*`)
- Détecter et adapter automatiquement le type d'ID (`numeric`, `ulid`, `uuid`)
- Publier la configuration et les migrations
- *(Optionnel)* Publier les **modèles** personnalisables (`Role`, `Permission`) et les **traits** (`HasAcl`)

3. **Ou publier manuellement les ressources selon vos besoins :**

```bash
# Publier la configuration
php artisan vendor:publish --tag=acl-config

# Publier les modèles dans app/Models (Role, Permission)
php artisan vendor:publish --tag=acl-models

# Publier les traits dans app/Traits (HasAcl, HasRolesAndPermissions)
php artisan vendor:publish --tag=acl-traits

# Publier les traductions
php artisan vendor:publish --tag=acl-translations
```

4. **Exécuter les migrations :**

```bash
php artisan migrate
```

---

## Configuration (`config/acl.php`)

Le fichier `config/acl.php` vous permet de paramétrer l'ensemble des comportements du package :

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    | Options: 'ulid', 'uuid', 'numeric'
    | Defines the primary key strategy for ACL tables (roles, permissions).
    */
    'primary_key_type' => env('ACL_PRIMARY_KEY_TYPE', 'numeric'),

    /*
    |--------------------------------------------------------------------------
    | User Foreign Key Type
    |--------------------------------------------------------------------------
    | Options: 'numeric', 'uuid', 'ulid'
    | Defines the column type used for user_id in pivot tables.
    */
    'user_foreign_key_type' => env('ACL_USER_FK_TYPE', 'numeric'),

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    | Users with this role bypass all permission checks automatically.
    */
    'super_admin_role' => 'super-admin',

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
    'enable_wildcards' => env('ENABLE_WILDCARDS', true),
    
    /*
    |--------------------------------------------------------------------------
    | Custom Models
    |--------------------------------------------------------------------------
    | Customize the models used by the package.
    */
    'models' => [
        'user'       => App\Models\User::class,
        'role'       => Pmb\Acl\Models\Role::class,
        'permission' => Pmb\Acl\Models\Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'roles'            => 'roles',
        'permissions'      => 'permissions',
        'role_permissions' => 'role_permissions',
        'user_roles'       => 'user_roles',
        'user_permissions' => 'user_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Catalogue / Modules
    |--------------------------------------------------------------------------
    | Centralized list of permissions grouped by functional modules.
    | Used to dynamically generate Admin Back-office interfaces.
    */
    'modules' => [
        'users' => [
            'users.view'   => 'View Users',
            'users.create' => 'Create Users',
            'users.edit'   => 'Edit Users',
            'users.delete' => 'Delete Users',
            'users.*'      => 'All User Operations',
        ],
        'posts' => [
            'posts.view'   => 'View Posts',
            'posts.create' => 'Create Posts',
            'posts.edit'   => 'Edit Posts',
            'posts.delete' => 'Delete Posts',
            'posts.publish'=> 'Publish Posts',
            'posts.*'      => 'All Post Operations',
        ],
        'settings' => [
            'settings.view' => 'View System Settings',
            'settings.edit' => 'Update Settings',
        ],
    ],
];

```

---

## Préparation des Modèles

### 1. Attacher le Trait `HasAcl` à votre Modèle `User`

Ajoutez le trait `Pmb\Acl\Traits\HasAcl` sur votre modèle `User` :

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pmb\Acl\Traits\HasAcl;

class User extends Authenticatable
{
    use HasAcl;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}

```

---

## Utilisation du Trait `HasAcl`

### Gestion des Rôles

```php
use Pmb\Acl\Models\Role;

$user = User::find(1);
$roleAdmin = Role::where('slug', 'admin')->first();

// Attribuer un rôle (via Modèle, Slug ou Name)
$user->assignRole($roleAdmin);
$user->assignRole('editor');

// Vérifier la possession d'un rôle
if ($user->hasRole('admin')) {
    // L'utilisateur est administrateur
}

// Synchroniser les rôles (Remplace les rôles existants)
$user->syncRoles(['editor', 'manager']);

// Retirer un rôle
$user->removeRole('editor');

```

### Gestion des Permissions

```php
use Pmb\Acl\Models\Permission;

// Assigner une permission directe à l'utilisateur (PBAC)
$user->givePermissionTo('posts.create');

// Vérifier une permission
if ($user->hasPermissionTo('posts.create')) {
    // Action autorisée
}

// Synchroniser les permissions directes
$user->syncPermissions(['posts.create', 'posts.edit']);

// Révoquer une permission directe
$user->revokePermissionTo('posts.create');

// Récupérer la relation des permissions directes uniquement
$directPermissions = $user->directPermissions; // ou $user->permissions

// Récupérer la collection fusionnée et dédupliquée (Permissions directes + via Rôles)
$allPermissions = $user->getAllPermissions();

// Ou via l'accesseur dynamique
$allPermissions = $user->all_permissions;

```

---

## 🖥️ Génération Dynamique d'Interfaces Administration (UI Matrix)

Grâce au tableau `modules` configuré dans `config/acl.php`, vous pouvez construire des écrans de gestion des rôles dynamiques, modulaires et faciles à maintenir.

### 1. Contrôleur de Gestion des Rôles (`RoleController`)

```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;

class RoleController extends Controller
{
    /**
     * Affiche le formulaire de création de rôle avec le catalogue dynamique.
     */
    public function create()
    {
        // Récupération du catalogue configuré
        $modules = config('acl.modules', []);

        return view('admin.roles.create', compact('modules'));
    }

    /**
     * Enregistre un nouveau rôle et synchronise ses permissions.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'required|string|max:255|unique:roles,slug',
            'description'   => 'nullable|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        // 1. Création du rôle
        $role = Role::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['slug']),
            'description' => $validated['description'] ?? null,
        ]);

        // 2. Synchronisation dynamique des permissions
        if (!empty($validated['permissions'])) {
            $permissionIds = [];
            foreach ($validated['permissions'] as $code) {
                // Recherche ou création automatique de la permission en BDD
                $permission = Permission::firstOrCreate(
                    ['name' => $code],
                    [
                        'label'  => Str::title(str_replace(['.', '_', '*'], [' ', ' ', ' All'], $code)),
                        'module' => explode('.', $code)[0] ?? 'general',
                    ]
                );
                $permissionIds[] = $permission->id;
            }
            $role->permissions()->sync($permissionIds);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rôle créé avec succès avec ses permissions.');
    }
}

```

---

### 2. Formulaire Blade & JS Matrice de Permissions

```html
<!-- resources/views/admin/roles/create.blade.php -->
@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto py-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Créer un nouveau Rôle</h2>

        <form action="{{ route('admin.roles.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Informations du rôle -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Intitulé du Rôle</label>
                    <input type="text" name="name" id="name" required placeholder="ex: Gestionnaire de Contenu" 
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug Identifiant</label>
                    <input type="text" name="slug" id="slug" required placeholder="ex: content-manager" 
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>

            <!-- Matrice Dynamique des Permissions par Module -->
            <div class="border-t border-gray-200 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Matrice des Permissions</h3>
                    <button type="button" onclick="toggleAllCheckboxes(true)" class="text-xs font-semibold text-indigo-600 hover:underline">Tout sélectionner</button>
                </div>

                <div class="space-y-6">
                    @foreach(config('acl.modules', []) as $module => $permissions)
                        <fieldset class="border border-gray-200 rounded-lg p-4 bg-gray-50/50">
                            <legend class="px-2 font-bold uppercase text-indigo-600 text-xs tracking-wider flex items-center gap-3">
                                <span>{{ $module }}</span>
                                <button type="button" 
                                        onclick="toggleModuleCheckboxes('{{ $module }}')" 
                                        class="text-xs font-normal text-gray-500 hover:text-indigo-600 underline lowercase">
                                    (inverser la sélection)
                                </button>
                            </legend>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                                @foreach($permissions as $code => $label)
                                    <label for="perm_{{ Str::slug($code) }}" 
                                           class="flex items-start gap-3 p-2.5 rounded-lg border border-gray-200 bg-white hover:border-indigo-300 transition cursor-pointer">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $code }}" 
                                               id="perm_{{ Str::slug($code) }}"
                                               data-module="{{ $module }}"
                                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <div class="text-xs">
                                            <span class="font-medium text-gray-800 block">{{ $label }}</span>
                                            <code class="text-[10px] text-gray-500 bg-gray-100 px-1 py-0.5 rounded">{{ $code }}</code>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.roles.index') }}" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                    Enregistrer le Rôle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleModuleCheckboxes(module) {
        const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }

    function toggleAllCheckboxes(state) {
        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = state);
    }
</script>
@endsection

```

---

## Synchronisation Automatique via Seeder

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;

class AclSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Synchroniser le catalogue de permissions
        foreach (config('acl.modules', []) as $moduleName => $permissions) {
            foreach ($permissions as $code => $label) {
                Permission::firstOrCreate(
                    ['name' => $code],
                    ['label' => $label, 'module' => $moduleName]
                );
            }
        }

        // 2. Créer le rôle Super Admin par défaut
        Role::firstOrCreate(
            ['slug' => config('acl.super_admin_role', 'super-admin')],
            ['name' => 'Super Admin', 'description' => 'Accès complet à l\'application']
        );
    }
}

```

---

## Exécution des Tests

```bash
./vendor/bin/pest

```

---

## 📄 Licence

Ce package est sous licence [MIT](LICENSE.md).
