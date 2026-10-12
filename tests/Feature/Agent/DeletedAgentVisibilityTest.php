<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A deleted agent disappears at once, while its purge still waits in the
 * queue: dashboard and API routes answer 404, its hosted chat shows the
 * not-found page, and the lists leave it out.
 */
beforeEach(function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->deleted = createAgent($this->owner, ['name' => 'Gone', 'is_public' => true]);
    $this->kept = createAgent($this->owner, ['name' => 'Kept']);

    $this->slug = $this->deleted->slug;
    app(DeleteAgentAction::class)->execute($this->deleted);
});

it('answers 404 on the deleted agent dashboard pages', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->deleted->external_id))
        ->assertNotFound();
});

it('leaves the deleted agent out of the dashboard list', function (): void {
    $this->actingAs($this->owner)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', $this->kept->external_id);
});

it('shows the not-found page on the deleted agent hosted chat', function (string $address): void {
    $segment = $address === 'slug' ? $this->slug : $this->deleted->external_id;

    $this->get('/chat/'.$segment)
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNotFound'));
})->with(['slug', 'external id']);

it('answers 404 in the API and leaves the agent out of its list', function (): void {
    $headers = ['Authorization' => 'Bearer '.$this->owner->createToken('api')->plainTextToken, 'Accept' => 'application/json'];

    $this->withHeaders($headers)
        ->getJson('/api/v1/agents/'.$this->deleted->external_id)
        ->assertNotFound();

    $this->withHeaders($headers)
        ->getJson('/api/v1/agents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->kept->external_id);
});
