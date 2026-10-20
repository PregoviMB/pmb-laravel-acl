<?php

use Illuminate\Support\Facades\Config;
use Pmb\Acl\Tests\TestCase;

it('performs deep recursive config merge preserving user custom modules while injecting new features', function () {
    /** @var TestCase $this */

    // Emulate existing published config in host app (user defined their own modules and custom table)
    $hostCustomConfig = [
        'mode' => 'rbac',
        'tables' => [
            'roles' => 'custom_roles',
        ],
        'modules' => [
            'billing_custom' => [
                'billing.charge' => 'Charge Customer',
                'billing.refund' => 'Refund Customer',
            ],
        ],
    ];

    // Read package default config
    $packageConfig = require __DIR__ . '/../../config/acl.php';

    // Simulate merge
    $reflection = new ReflectionClass(Pmb\Acl\AclServiceProvider::class);
    $method = $reflection->getMethod('deepMergeArray');
    $method->setAccessible(true);
    $provider = new Pmb\Acl\AclServiceProvider($this->app);

    $merged = $method->invoke($provider, $packageConfig, $hostCustomConfig);

    // 1. Host overrides must be preserved
    expect($merged['mode'])->toBe('rbac')
        ->and($merged['tables']['roles'])->toBe('custom_roles')
        ->and($merged['modules'])->toHaveKey('billing_custom');

    // 2. Missing sections from previous package versions must be injected automatically
    expect($merged)->toHaveKey('cache')
        ->and($merged['cache']['enabled'])->toBeTrue()
        ->and($merged)->toHaveKey('tenant')
        ->and($merged['tenant']['foreign_key'])->toBe('tenant_id')
        // Other default tables must still exist (not wiped out by shallow array_merge)
        ->and($merged['tables'])->toHaveKey('permissions');
});

