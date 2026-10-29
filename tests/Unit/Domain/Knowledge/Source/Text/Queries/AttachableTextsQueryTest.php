<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Text\Queries\AttachableTextsQuery;
use Illuminate\Support\Facades\Storage;

/**
 * The "attach an existing text" list shows the workspace's texts (not just
 * the ones the viewing teammate created) that are not attached yet, and
 * never a text of another workspace.
 */
/** A trained text of the agent's workspace, with its content in storage. */
function latText(Agent $agent, string $content, string $excerpt): SourceText
{
    $text = SourceText::factory()->ingested()->create([
        'workspace_id' => $agent->workspace_id,
        'excerpt' => $excerpt,
        'checksum' => md5($content),
    ]);
    Storage::put($text->filepath, $content);

    return $text;
}

beforeEach(function (): void {
    Storage::fake();
});

it('lists the workspace texts for a teammate, excluding ones already attached', function (): void {
    $agent = Agent::factory()->create();
    $teammate = User::factory()->employee($agent->workspace->owner)->create();

    $attachable = latText($agent, 'one two three four', 'one two three four');
    $attached = latText($agent, 'already linked text', 'already linked text');
    $agent->texts()->attach($attached->id);

    $rows = app(AttachableTextsQuery::class)->get($teammate, $agent);

    expect($rows->pluck('id')->all())->toBe([$attachable->id])
        ->and($rows->first()->excerpt)->toBe('one two three four')
        ->and($rows->first()->word_count)->toBe(4);
});

it('does not list texts from another workspace', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    latText($otherAgent, 'foreign text', 'foreign text');

    expect(app(AttachableTextsQuery::class)->get($agent->workspace->owner, $agent))->toBeEmpty();
});
