<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pmb\Acl\Http\Middlewares\PermissionMiddleware;
use Pmb\Acl\Http\Middlewares\RoleMiddleware;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = $this->createUser();
});

it('allows access via RoleMiddleware with OR condition (|)', function () {
    /** @var TestCase $this */
    $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);
    $this->user->assignRole('manager');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new RoleMiddleware();
    $response = $middleware->handle($request, fn () => new Response('OK'), 'admin|manager');

    expect($response->getContent())->toBe('OK');
});

it('forbids access via RoleMiddleware when no role matches OR condition', function () {
    /** @var TestCase $this */
    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new RoleMiddleware();
    expect(fn () => $middleware->handle($request, fn () => new Response('OK'), 'admin|manager'))
        ->toThrow(HttpException::class);
});

it('handles RoleMiddleware with AND condition (&)', function () {
    /** @var TestCase $this */
    Role::create(['name' => 'Admin', 'slug' => 'admin']);
    Role::create(['name' => 'Super', 'slug' => 'super']);

    $this->user->assignRole('admin');

    $request = Request::create('/admin', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new RoleMiddleware();

    // Only 'admin' role, misses 'super' -> throws 403
    expect(fn () => $middleware->handle($request, fn () => new Response('OK'), 'admin&super'))
        ->toThrow(HttpException::class);

    // Both assigned -> allowed
    $this->user->assignRole('super');
    $response = $middleware->handle($request, fn () => new Response('OK'), 'admin&super');
    expect($response->getContent())->toBe('OK');
});

it('handles PermissionMiddleware with OR condition (|)', function () {
    /** @var TestCase $this */
    Permission::create(['name' => 'posts.create', 'label' => 'Create', 'module' => 'posts']);
    $this->user->givePermissionTo('posts.create');

    $request = Request::create('/posts', 'POST');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new PermissionMiddleware();

    // User has posts.create, not posts.edit -> allowed via OR
    $response = $middleware->handle($request, fn () => new Response('OK'), 'posts.edit|posts.create');
    expect($response->getContent())->toBe('OK');

    // Neither possessed -> 403
    expect(fn () => $middleware->handle($request, fn () => new Response('OK'), 'posts.edit|posts.delete'))
        ->toThrow(HttpException::class);
});

it('handles PermissionMiddleware with AND condition (&)', function () {
    /** @var TestCase $this */
    Permission::create(['name' => 'invoices.create', 'label' => 'Create', 'module' => 'invoices']);
    Permission::create(['name' => 'invoices.send', 'label' => 'Send', 'module' => 'invoices']);

    $this->user->givePermissionTo('invoices.create');

    $request = Request::create('/invoices', 'POST');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new PermissionMiddleware();

    // Has create, lacks send -> 403
    expect(fn () => $middleware->handle($request, fn () => new Response('OK'), 'invoices.create&invoices.send'))
        ->toThrow(HttpException::class);

    // Give invoices.send -> allowed
    $this->user->givePermissionTo('invoices.send');
    $response = $middleware->handle($request, fn () => new Response('OK'), 'invoices.create&invoices.send');
    expect($response->getContent())->toBe('OK');
});

it('bypasses PermissionMiddleware for super admin', function () {
    /** @var TestCase $this */
    Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $this->user->assignRole('super-admin');

    $request = Request::create('/invoices', 'POST');
    $request->setUserResolver(fn () => $this->user);

    $middleware = new PermissionMiddleware();
    $response = $middleware->handle($request, fn () => new Response('OK'), 'unknown.permission&another.missing');

    expect($response->getContent())->toBe('OK');
});

