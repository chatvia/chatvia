<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Lead\Actions\ExportAgentLeadsToCsvAction;
use Domain\Lead\Models\Lead;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/**
 * Behavioural coverage for the Lead actions. The Leads domain shipped with
 * ZERO tests despite being a named critical flow and revenue-adjacent (captured
 * contact info). The stake worth pinning here:
 *   - the CSV export's column order, fputcsv escaping, and cross-tenancy guard
 *
 * The cross-tenancy assertion is the one that matters: a bug where exports leak
 * another account's leads is the kind of thing that ends up in a security
 * advisory. The action's `$leads->count() !== count($leadIds)` check is the
 * structural guard — these tests pin it.
 *
 * Bulk delete is now an inline relation-scoped `$agent->leads()->whereIn(...)->delete()`
 * in AgentLeadController (no Action — single Eloquent call); its permission gate
 * and validation are covered at the HTTP level by AgentPermissionRouteTest.
 */
function leadAgent(string $name = 'Lead Bot'): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    return Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => $name,
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => false,
    ]);
}

/**
 * Builds the Agent → Session → Conversation → Lead chain the schema requires
 * (leads.chat_conversation_id is NOT NULL — the factory default of null doesn't
 * satisfy it).
 */
function leadForAgent(Agent $agent, array $overrides = []): Lead
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id]);

    return Lead::factory()->forAgent($agent)->create(array_merge([
        'chat_conversation_id' => $conversation->id,
    ], $overrides));
}

describe('ExportAgentLeadsToCsvAction', function (): void {
    it('exports the canonical 9-column header followed by one row per lead', function (): void {
        $agent = leadAgent();
        $lead = leadForAgent($agent, [
            'name' => 'Acme Buyer',
            'email' => 'buyer@acme.test',
            'phone' => '+1 555 010 0001',
            'job_title' => 'Director',
            'company' => 'Acme Inc',
            'location' => 'NYC',
            'message' => 'Send the prospectus.',
        ]);

        $result = app(ExportAgentLeadsToCsvAction::class)->execute($agent, [$lead->id]);

        expect($result)->toHaveKeys(['filename', 'csv'])
            ->and($result['filename'])->toContain('leads-Lead Bot-')
            ->and($result['filename'])->toEndWith('.csv');

        $lines = array_values(array_filter(explode("\n", trim($result['csv']))));
        expect($lines)->toHaveCount(2)
            ->and($lines[0])->toBe('ID,Name,Email,Phone,"Job Title",Company,Location,Message,"Created At"');

        // Row contents: id + the 7 lead fields + a Y-m-d H:i:s timestamp.
        expect($lines[1])->toContain((string) $lead->id)
            ->and($lines[1])->toContain('Acme Buyer')
            ->and($lines[1])->toContain('buyer@acme.test')
            ->and($lines[1])->toContain('Acme Inc')
            ->and($lines[1])->toContain('Send the prospectus.');
    });

    it('escapes commas and quotes via fputcsv so CSV consumers can round-trip the fields', function (): void {
        $agent = leadAgent();
        $lead = leadForAgent($agent, [
            'name' => 'Smith, John',                          // comma → field gets quoted
            'company' => 'Acme "The Best" Inc',              // embedded quotes → doubled
            'message' => "Line one\nLine two, with comma",   // newline + comma
        ]);

        $csv = app(ExportAgentLeadsToCsvAction::class)->execute($agent, [$lead->id])['csv'];

        // Per RFC-4180: fields with comma/quote/newline are wrapped in quotes,
        // embedded quotes are doubled. fputcsv does this automatically.
        expect($csv)->toContain('"Smith, John"')
            ->and($csv)->toContain('"Acme ""The Best"" Inc"')
            ->and($csv)->toContain("\"Line one\nLine two, with comma\"");
    });

    it('refuses to export when any requested id does not belong to the agent (cross-tenancy guard)', function (): void {
        $mine = leadAgent('Mine');
        $myLead = leadForAgent($mine);

        $other = leadAgent('Other');
        $stolenLead = leadForAgent($other, [
            'name' => 'Other Account Contact',
            'email' => 'leaked@other.test',
        ]);

        expect(fn () => app(ExportAgentLeadsToCsvAction::class)
            ->execute($mine, [$myLead->id, $stolenLead->id]))
            ->toThrow(DomainValidationException::class);

        // Belt-and-braces: re-run for just my legitimate leads and confirm the
        // stolen lead's content never appears in any exported byte.
        $csv = app(ExportAgentLeadsToCsvAction::class)->execute($mine, [$myLead->id])['csv'];
        expect($csv)->not->toContain('Other Account Contact')
            ->and($csv)->not->toContain('leaked@other.test');
    });

    it('renders empty optional fields as empty CSV cells (not the string "null")', function (): void {
        $agent = leadAgent();
        $lead = leadForAgent($agent, [
            'name' => 'Spartan',
            'email' => 'spartan@test.test',
            'phone' => null,
            'job_title' => null,
            'company' => null,
            'location' => null,
            'message' => null,
        ]);

        $csv = app(ExportAgentLeadsToCsvAction::class)->execute($agent, [$lead->id])['csv'];

        // After the email field, the 5 nullable columns must be a chain of
        // empty cells (",,,,,") followed by the created_at timestamp.
        expect($csv)->toContain('spartan@test.test,,,,,,')
            ->and($csv)->not->toContain(',null,');
    });
});
