<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Actions\ListAttachableTextsForAgentAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The "attach an existing text" list must show the workspace's texts (not just
 * the ones the viewing teammate created), read the excerpt from its column,
 * and cut multibyte excerpts on character boundaries.
 */
function attachableText(Agent $agent, string $content, string $excerpt): SourceText
{
    $path = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt';
    Storage::put($path, $content);

    return SourceText::create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => $path,
        'excerpt' => $excerpt,
        'length' => 1,
        'checksum' => md5($content),
        'status' => SourceStatus::Completed->value,
    ]);
}

beforeEach(function (): void {
    Storage::fake();
});

it('lists the workspace texts for a teammate, excluding ones already attached', function (): void {
    $agent = Agent::factory()->create();
    $teammate = User::factory()->employee($agent->workspace->owner)->create();

    $attachable = attachableText($agent, 'one two three four', 'one two three four');
    $attached = attachableText($agent, 'already linked text', 'already linked text');
    $agent->texts()->attach($attached->id);

    $rows = app(ListAttachableTextsForAgentAction::class)->execute($teammate, $agent);

    expect($rows->pluck('id')->all())->toBe([$attachable->id])
        ->and($rows->first()->excerpt)->toBe('one two three four')
        ->and($rows->first()->word_count)->toBe(4);
});

it('cuts a multibyte excerpt on a character boundary', function (): void {
    $agent = Agent::factory()->create();
    $excerpt = str_repeat('مرحبا ', 60); // 360 characters, 2 bytes each

    attachableText($agent, $excerpt, $excerpt);

    $row = app(ListAttachableTextsForAgentAction::class)->execute($agent->workspace->owner, $agent)->first();

    expect(mb_strlen($row->excerpt))->toBe(200)
        ->and(mb_check_encoding($row->excerpt, 'UTF-8'))->toBeTrue()
        ->and($row->word_count)->toBe(60);
});

it('does not list texts from another workspace', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    attachableText($otherAgent, 'foreign text', 'foreign text');

    expect(app(ListAttachableTextsForAgentAction::class)->execute($agent->workspace->owner, $agent))->toBeEmpty();
});
