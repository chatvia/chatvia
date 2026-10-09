<?php

use Domain\Membership\Team\Data\Resources\Permissions\PermissionMetaData;
use Domain\Membership\Team\Services\TeamPermissionCatalog;

it('reads every permission code and its label from config', function (): void {
    $catalog = app(TeamPermissionCatalog::class);

    expect($catalog->codes())->toBe(array_keys(config('team-permissions.permissions')))
        ->and($catalog->permissions()['agent.chat.view'])->toBeInstanceOf(PermissionMetaData::class)
        ->and($catalog->permissions()['agent.chat.view']->label)->toBe('View conversations');
});

it('expands the * wildcard to every code', function (): void {
    $catalog = app(TeamPermissionCatalog::class);

    expect($catalog->expand(['*']))->toBe($catalog->codes())
        ->and($catalog->expand(['agent.chat.view']))->toBe(['agent.chat.view']);
});
