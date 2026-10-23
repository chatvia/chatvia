<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\DeleteFormDefinitionAction;
use Domain\Form\Actions\EnableFormsCapabilityAction;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The Forms capability follows the agent's forms: adding one turns it on,
 * removing the last one turns it off. Deleting a form deletes its leads.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    // The workspace-level forms server already exists, so enabling is a pure attach.
    $this->user->workspace()->mcpServers()->create([
        'connector_slug' => FormDefinition::CAPABILITY_SLUG,
        'category' => 'builtin',
        'name' => 'Forms',
        'slug' => 'forms',
        'url' => config('app.url').'/mcp/forms',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
});

it('turns Forms on for the agent, once', function (): void {
    app(EnableFormsCapabilityAction::class)->execute($this->agent);
    app(EnableFormsCapabilityAction::class)->execute($this->agent);

    expect($this->agent->isFormsEnabled())->toBeTrue()
        ->and($this->agent->mcpServers()->count())->toBe(1);
});

it('turns Forms off only when the last form is deleted', function (): void {
    [$first, $second] = FormDefinition::factory()->forAgent($this->agent)->count(2)->create();
    app(EnableFormsCapabilityAction::class)->execute($this->agent);

    app(DeleteFormDefinitionAction::class)->execute($this->agent, $first);
    expect(FormDefinition::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and($this->agent->isFormsEnabled())->toBeTrue();

    app(DeleteFormDefinitionAction::class)->execute($this->agent, $second);
    expect($this->agent->isFormsEnabled())->toBeFalse();
});

it('deletes the form leads in batches, before the form, and keeps other forms leads', function (): void {
    [$deleted, $kept] = FormDefinition::factory()->forAgent($this->agent)->count(2)->create();
    FormSubmission::factory()->forDefinition($deleted)->count(4)->create();
    FormSubmission::factory()->forDefinition($deleted)->create(['status' => FormSubmissionStatus::Pending]);
    $keptLead = FormSubmission::factory()->forDefinition($kept)->create();

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (preg_match('/^(?:delete from|DELETE FROM) "?(form_submissions|form_definitions)"? /', $query->sql, $match) === 1) {
            $statements[] = $match[1];
        }
    });

    app(DeleteFormDefinitionAction::class, ['leadBatch' => 2])->execute($this->agent, $deleted);

    // 5 leads in batches of 2 → 3 batches and 1 that finds nothing, then the form.
    expect($statements)->toBe(['form_submissions', 'form_submissions', 'form_submissions', 'form_submissions', 'form_definitions'])
        ->and(FormSubmission::query()->where('form_definition_id', $deleted->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->find($keptLead->id))->not->toBeNull();
});
