<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Forms management page is reachable regardless of the capability toggle, so
 * the controller keeps the capability in step with form existence: the first
 * form turns it on (and attaches the hidden `leads` companion); deleting the
 * last form turns it back off.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->user->billing()->firstOrCreate([]);
    $this->user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
    $this->user->refresh();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    // Pre-provision the account-level forms + leads rows so enabling is a pure
    // attach (no remote tools/list during the test).
    foreach (['forms', 'leads'] as $slug) {
        $this->user->mcpServers()->create([
            'connector_slug' => $slug,
            'category' => 'builtin',
            'name' => ucfirst($slug),
            'slug' => $slug,
            'url' => config('app.url').'/mcp/'.$slug,
            'auth_type' => 'none',
            'is_enabled' => true,
        ]);
    }
});

function storeForm(User $user, Agent $agent, string $name = 'Contact', string $fieldKey = 'email'): TestResponse
{
    return test()->actingAs($user)->post(route('agents.forms.store', $agent->external_id), [
        'name' => $name,
        'form_type' => 'lead',
        'is_active' => true,
        'schema' => [['key' => $fieldKey, 'label' => 'Field', 'type' => 'text', 'required' => true]],
    ]);
}

it('enables the Forms capability (and the leads companion) when the first form is created', function (): void {
    expect($this->agent->isFormsEnabled())->toBeFalse();

    storeForm($this->user, $this->agent)->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeTrue()
        ->and($this->agent->isLeadsEnabled())->toBeTrue();
});

it('disables the Forms capability when the last form is deleted', function (): void {
    storeForm($this->user, $this->agent)->assertRedirect();
    $form = FormDefinition::query()->forAgent($this->agent)->firstOrFail();
    expect($this->agent->isFormsEnabled())->toBeTrue();

    $this->actingAs($this->user)
        ->delete(route('agents.forms.destroy', ['agent' => $this->agent->external_id, 'form' => $form->external_id]))
        ->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeFalse();
});

it('keeps the Forms capability enabled while other forms remain', function (): void {
    storeForm($this->user, $this->agent, 'Contact', 'email');
    storeForm($this->user, $this->agent, 'Booking', 'date');
    $first = FormDefinition::query()->forAgent($this->agent)->orderBy('id')->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('agents.forms.destroy', ['agent' => $this->agent->external_id, 'form' => $first->external_id]))
        ->assertRedirect();

    expect($this->agent->isFormsEnabled())->toBeTrue();
});

it('lists each form with its submitted-submission count', function (): void {
    $form = FormDefinition::factory()->forAgent($this->agent)->create(['name' => 'Contact']);
    FormSubmission::factory()->forDefinition($form)->submitted()->count(2)->create();
    FormSubmission::factory()->forDefinition($form)->create(); // pending → not counted

    $this->actingAs($this->user)
        ->get(route('agents.forms.index', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page): \Inertia\Testing\AssertableInertia => $page
            ->component('agents/Forms/index')
            ->where('forms.0.submissions_count', 2));
});
