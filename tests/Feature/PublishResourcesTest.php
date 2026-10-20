<?php

use Illuminate\Support\ServiceProvider;
use Pmb\Acl\AclServiceProvider;
use Pmb\Acl\Tests\TestCase;

it('registers vendor publish tags for config, translations, models, and traits', function () {
    /** @var TestCase $this */
    $publishes = ServiceProvider::$publishes[AclServiceProvider::class] ?? [];
    $publishGroups = ServiceProvider::$publishGroups ?? [];

    expect($publishGroups)->toHaveKey('acl-config')
        ->and($publishGroups)->toHaveKey('acl-translations')
        ->and($publishGroups)->toHaveKey('acl-models')
        ->and($publishGroups)->toHaveKey('acl-traits');

    // Check models paths
    $modelFiles = $publishGroups['acl-models'];
    $publishedRole = array_values($modelFiles);
    expect(implode(';', $publishedRole))->toContain('Models/Role.php')
        ->and(implode(';', $publishedRole))->toContain('Models/Permission.php');

    // Check traits paths
    $traitFiles = $publishGroups['acl-traits'];
    $publishedTraits = array_values($traitFiles);
    expect(implode(';', $publishedTraits))->toContain('Traits/HasAcl.php')
        ->and(implode(';', $publishedTraits))->toContain('Traits/HasRolesAndPermissions.php');
});

