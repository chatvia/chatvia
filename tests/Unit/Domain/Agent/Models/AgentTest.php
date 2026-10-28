<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Events\AgentToolsChanged;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * What the Agent model works out from its own columns and settings: the
 * system instructions it answers with, its UI languages, its model settings
 * and reasoning effort, its chat bubbles per locale, and the tool change it
 * announces when a function is attached or detached, and how a connector
 * starts out when it is attached.
 */
describe('system instructions', function (): void {
    it('uses the attached library prompt first', function (): void {
        $agent = Agent::factory()->create([
            'system_instructions' => 'Custom body',
            'agent_prompt_id' => AgentPrompt::factory()->create(['prompt' => 'Library body'])->id,
        ]);

        expect($agent->resolveSystemInstructions())->toBe('Library body');
    });

    it('falls back to the custom instructions without a library prompt', function (): void {
        $agent = Agent::factory()->create(['system_instructions' => 'Custom body', 'agent_prompt_id' => null]);

        expect($agent->resolveSystemInstructions())->toBe('Custom body');
    });

    it('falls back to the built-in default when neither is set', function (): void {
        $agent = Agent::factory()->create(['system_instructions' => null, 'agent_prompt_id' => null]);

        expect($agent->resolveSystemInstructions())->toBe(prompt('Knowledge/system'));
    });
});

describe('enabled languages', function (): void {
    beforeEach(function (): void {
        $this->agent = createAgent(User::factory()->create());
    });

    it('returns every platform language minus auto when nothing is selected', function (): void {
        $all = config('chatvia.languages');
        unset($all['auto']);

        expect($this->agent->enabledLanguages())->toBe($all);
    });

    it('returns only the selected languages, in platform order', function (): void {
        $this->agent->setSetting('enabled_languages', ['fr', 'de'], 'appearance');

        expect($this->agent->fresh()->enabledLanguages())->toBe([
            'de' => config('chatvia.languages.de'),
            'fr' => config('chatvia.languages.fr'),
        ]);
    });

    it('ignores unknown or non-string codes', function (): void {
        $this->agent->setSetting('enabled_languages', ['de', 'xx', 123], 'appearance');

        expect(array_keys($this->agent->fresh()->enabledLanguages()))->toBe(['de']);
    });
});

describe('model settings', function (): void {
    it('gives the generation settings as numbers, even when they are stored as text', function (): void {
        $agent = Agent::factory()->create();
        $agent->setSetting('temperature', '0.7', 'llm_settings');
        $agent->setSetting('top_k', '40', 'llm_settings');

        $settings = $agent->fresh()->llmSettings();

        expect($settings['temperature'])->toBe(0.7)
            ->and($settings['top_k'])->toBe(40)
            ->and($settings['top_p'])->toBeFloat();
    });

    it('reasons only with a reasoning model while the admin allows it', function (bool $allowed, bool $modelReasons, string $expected): void {
        config(['chatvia.ai.reasoning.enabled' => $allowed]);
        $agent = Agent::factory()->create(['llm_id' => testLlm('other-model', attributes: ['capabilities' => ['reasoning' => $modelReasons]])->id]);
        $agent->setSetting('effort', 'medium', 'reasoning');

        expect($agent->fresh()->reasoningEffort()->value)->toBe($expected);
    })->with([
        'allowed, reasoning model' => [true, true, 'medium'],
        'not allowed' => [false, true, 'off'],
        'model does not reason' => [true, false, 'off'],
    ]);
});

describe('chat bubbles', function (): void {
    it('resolves each bubble per locale: the override, then the translation, then the base text', function (): void {
        $agent = Agent::factory()->create();
        $agent->setSetting('bubbles', [
            ['text' => 'Hello', 'translations' => ['de' => 'Hallo'], 'overrides' => ['de' => 'Servus']],
            ['text' => 'Bye', 'translations' => ['de' => 'Tschüss'], 'overrides' => []],
            ['text' => 'Welcome', 'translations' => [], 'overrides' => []],
        ], 'widget');

        expect($agent->fresh()->resolvedBubblesForLocale('de-DE'))->toBe(['Servus', 'Tschüss', 'Welcome'])
            ->and($agent->fresh()->resolvedBubblesForLocale('auto'))->toBe(['Hello', 'Bye', 'Welcome']);
    });

    it('gives the editor every bubble in its full shape', function (): void {
        $agent = Agent::factory()->create();
        $agent->setSetting('bubbles', [
            ['text' => 'One', 'translations' => ['de' => 'Eins'], 'overrides' => []],
            ['text' => 'Two', 'translations' => [], 'overrides' => ['de' => 'Zwo']],
        ], 'widget');

        expect($agent->fresh()->normalizedBubbles())->toBe([
            ['text' => 'One', 'translations' => ['de' => 'Eins'], 'overrides' => []],
            ['text' => 'Two', 'translations' => [], 'overrides' => ['de' => 'Zwo']],
        ]);
    });
});

// Pivot writes fire no Eloquent events, so the agent announces the change
// itself; the tool layer then drops its cached pool (ForgetAgentToolPoolTest).
it('announces a tool change when a function is attached or detached', function (): void {
    Event::fake([AgentToolsChanged::class]);
    $agent = Agent::factory()->create();
    $function = AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id]);

    $agent->attachFunction($function);
    $agent->detachFunction($function);

    Event::assertDispatchedTimes(AgentToolsChanged::class, 2);
    Event::assertDispatched(AgentToolsChanged::class, fn (AgentToolsChanged $event): bool => $event->agentId === $agent->id);
});

// A new connector starts "always on"; attaching it again must not undo the
// agent's own choice.
it('attaches a new connector always on and keeps the choice on a repeat attach', function (): void {
    $agent = Agent::factory()->create();
    $server = AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id]);
    $alwaysOn = fn (): mixed => DB::table('agent_mcp_server')
        ->where('agent_id', $agent->id)
        ->where('mcp_server_id', $server->id)
        ->value('always_available');

    $agent->attachConnector($server);

    expect($alwaysOn())->toBeTrue();

    $agent->mcpServers()->updateExistingPivot($server->id, ['always_available' => false]);
    $agent->attachConnector($server);

    expect($alwaysOn())->toBeFalse()
        ->and($agent->mcpServers()->count())->toBe(1);
});
