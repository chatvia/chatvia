<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceDashboards;

/**
 * Tenancy: a workspace's dashboard opens for its owner only. A teammate gets a
 * 403, and anyone from another workspace a 404, as for any record of another
 * workspace, on the page and on the data behind it.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->dashboard = app(WorkspaceDashboards::class)->create($this->owner->workspace(), [], false);
});

it('opens for the workspace\'s owner', function (): void {
    $this->actingAs($this->owner)->get($this->dashboard->url())->assertOk();
    $this->actingAs($this->owner)->getJson($this->dashboard->url().'/data')->assertOk();
});

it('refuses a teammate of the workspace', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($teammate)->get($this->dashboard->url())->assertForbidden();
});

it('answers 404 for the owner of another workspace', function (): void {
    $other = User::factory()->create();

    $this->actingAs($other)->get($this->dashboard->url())->assertNotFound();
    $this->actingAs($other)->getJson($this->dashboard->url().'/data')->assertNotFound();
});

it('sends a guest to sign in', function (): void {
    $this->get($this->dashboard->url())->assertRedirect(route('login'));
});
