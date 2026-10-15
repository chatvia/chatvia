<?php

use Domain\Membership\Team\Services\TeamPermissionCatalog;

/**
 * TeamPermissionCatalog turns config/team-permissions.php into DTOs. A
 * permission may be configured as a full array or as a bare label string.
 */
it('reads a permission configured as an array with its label, description and prerequisites', function (): void {
    $permission = app(TeamPermissionCatalog::class)->permissions()['agent.chat.pulse'];

    expect($permission->label)->not->toBe('agent.chat.pulse')
        ->and($permission->requires)->toBe(['agent.chat.view']);
});

it('reads a permission configured as a bare label string', function (): void {
    config(['team-permissions.permissions' => [
        'reports.view' => 'View reports',
        'reports.edit' => ['label' => 'Edit reports', 'requires' => ['reports.view']],
    ]]);

    $permissions = app(TeamPermissionCatalog::class)->permissions();

    expect($permissions['reports.view']->toArray())->toBe(['label' => 'View reports', 'description' => '', 'requires' => []])
        ->and($permissions['reports.edit']->toArray())->toBe(['label' => 'Edit reports', 'description' => '', 'requires' => ['reports.view']]);
});

it('expands the * wildcard to every code and keeps any other list as it is', function (): void {
    config(['team-permissions.permissions' => ['a.view' => 'A', 'b.view' => 'B', 'c.view' => 'C']]);
    $catalog = app(TeamPermissionCatalog::class);

    expect($catalog->expand(['*']))->toBe(['a.view', 'b.view', 'c.view'])
        ->and($catalog->expand(['b.view', '*']))->toBe(['a.view', 'b.view', 'c.view'])
        ->and($catalog->expand([3 => 'c.view', 7 => 'a.view']))->toBe(['c.view', 'a.view']);
});
