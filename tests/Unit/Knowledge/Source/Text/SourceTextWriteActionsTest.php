<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creating or replacing a text source writes a file and several rows. The rows
 * are written in one transaction, a failed write removes the new file, and an
 * update deletes the old file only after the row points at the new one.
 */
function textWriterOwner(): User
{
    $owner = User::factory()->create();

    $owner->billing()->firstOrCreate([]);
    $owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);

    UserQuota::firstOrCreate(['user_id' => $owner->id], config('billing.packages.starter.quota_limits.fixed', []));

    app(TeamService::class)->createDefaultTeam($owner);

    return User::query()->findOrFail($owner->id);
}

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
});

it('replaces the file only after the row points at the new one', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, $owner, 'The original text about our product.');
    $oldPath = $text->filepath;

    $changed = app(UpdateAgentSourceTextAction::class)->execute($agent, $owner, $text, 'A new text about our product.');

    $text->refresh();

    expect($changed)->toBeTrue()
        ->and($text->filepath)->not->toBe($oldPath)
        ->and(Storage::get($text->filepath))->toBe('A new text about our product.')
        ->and(Storage::exists($oldPath))->toBeFalse();

    Bus::assertDispatchedTimes(TextIngestionJob::class, 2);
});

it('keeps the old file and row when the update fails', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, $owner, 'The original text about our product.');
    $oldPath = $text->filepath;

    SourceText::updating(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(UpdateAgentSourceTextAction::class)->execute($agent, $owner, $text, 'A new text about our product.'))
        ->toThrow(RuntimeException::class);

    expect(SourceText::query()->find($text->id)->filepath)->toBe($oldPath)
        ->and(Storage::get($oldPath))->toBe('The original text about our product.')
        ->and(Storage::allFiles())->toBe([$oldPath]);

    Bus::assertDispatchedTimes(TextIngestionJob::class, 1);
});

it('leaves no row and no file when creating the text fails half way', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);

    // Fails after the INSERT, before the pivot attach.
    SourceText::created(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(CreateAgentSourceTextAction::class)->execute($agent, $owner, 'Some text about our product.'))
        ->toThrow(RuntimeException::class);

    expect(SourceText::query()->where('user_id', $agent->user_id)->exists())->toBeFalse()
        ->and(Storage::allFiles())->toBe([]);

    Bus::assertNotDispatched(TextIngestionJob::class);
});
