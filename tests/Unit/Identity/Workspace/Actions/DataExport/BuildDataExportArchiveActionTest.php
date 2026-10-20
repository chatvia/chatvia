<?php

use App\Identity\Workspace\Actions\DataExport\BuildDataExportArchiveAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('packages user data into a zip and marks the export ready', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');

    $user = User::factory()->create();

    $export = DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Pending,
        'download_token' => str_repeat('z', 64),
    ]);

    app(BuildDataExportArchiveAction::class)->execute($export);

    $export->refresh();

    expect($export->status)->toBe(DataExportStatus::Ready)
        ->and($export->file_path)->toStartWith('users/'.$user->external_id.'/data-exports/')
        ->and($export->file_path)->toEndWith('.zip')
        ->and($export->file_size)->toBeGreaterThan(0)
        ->and($export->expires_at)->not->toBeNull();

    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    expect(bdeEntries($export))->toContain('README.txt')
        ->toContain('profile.json')
        ->toContain('agents.json')
        ->toContain('conversations.json')
        ->toContain('messages.json')
        ->toContain('message-limit.json');

    Event::assertDispatched(DataExportReady::class);
});

/**
 * Read one entry of the export archive.
 */
function bdeEntry(DataExport $export, string $name): string|false
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->file_path));
    $contents = $zip->getFromName($name);
    $zip->close();

    return $contents;
}

/**
 * The names of every entry in the export archive.
 *
 * @return list<string>
 */
function bdeEntries(DataExport $export): array
{
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($export->file_path)))->toBeTrue();

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    return $entries;
}

/** A pending export for the user. */
function bdeExport(User $user): DataExport
{
    return DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Pending,
        'download_token' => Str::random(64),
    ]);
}

it('streams a workspace bigger than one batch, with explicit columns and short queries', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');
    Storage::fake();

    $agent = Agent::factory()->create(['auth_secret' => 'cvsk_secret']);
    $owner = $agent->workspace->owner;
    $other = Agent::factory()->create();

    // Five conversations of two messages and one attachment each; one is in
    // the trash. Batches of two need three rounds.
    $conversations = Conversation::factory()->count(5)->create(['agent_id' => $agent->id, 'title' => 'Hello']);

    foreach ($conversations as $conversation) {
        Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]);

        $path = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/attachments/'.$conversation->id.'.txt';
        Storage::disk(Attachment::DISK)->put($path, 'attachment-'.$conversation->id);
        Attachment::create([
            'workspace_id' => $agent->workspace_id,
            'chat_conversation_id' => $conversation->id,
            'filepath' => $path,
            'filename' => '../evil/'.$conversation->id.'.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 12,
        ]);
    }

    $conversations->first()->delete();

    // Another workspace's chats stay out.
    Conversation::factory()->create(['agent_id' => $other->id]);

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'manual.txt']);
    Storage::put($file->filepath, 'knowledge-bytes');

    $export = DataExport::create([
        'user_id' => $owner->id,
        'status' => DataExportStatus::Pending,
        'download_token' => str_repeat('y', 64),
    ]);

    $maxBindings = 0;
    DB::listen(function (QueryExecuted $query) use (&$maxBindings): void {
        $maxBindings = max($maxBindings, count($query->bindings));
    });

    new BuildDataExportArchiveAction(batchSize: 2)->execute($export);

    $export->refresh();
    expect($export->status)->toBe(DataExportStatus::Ready);

    $exportedConversations = json_decode(bdeEntry($export, 'conversations.json'), true, flags: JSON_THROW_ON_ERROR);
    $exportedMessages = json_decode(bdeEntry($export, 'messages.json'), true, flags: JSON_THROW_ON_ERROR);
    $agents = json_decode(bdeEntry($export, 'agents.json'), true, flags: JSON_THROW_ON_ERROR);
    $knowledge = json_decode(bdeEntry($export, 'knowledge-sources.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($exportedConversations)->pluck('id')->sort()->values()->all())->toBe($conversations->modelKeys())
        ->and($exportedConversations[0]['title'])->toBe('Hello')
        ->and($exportedConversations[0])->not->toHaveKeys(['workspace_id', 'analysis_attempts'])
        ->and($exportedMessages)->toHaveCount(10)
        ->and($exportedMessages[0])->toHaveKey('content')
        ->and($exportedMessages[0])->not->toHaveKeys(['workspace_id', 'generation_id'])
        ->and($agents)->toHaveCount(1)
        ->and($agents[0]['external_id'])->toBe($agent->external_id)
        ->and($agents[0])->not->toHaveKey('auth_secret')
        ->and($knowledge['files'])->toHaveCount(1)
        ->and($knowledge['files'][0])->not->toHaveKeys(['filepath', 'checksum'])
        ->and($knowledge['texts'])->toBe([])
        ->and($knowledge['websites'])->toBe([]);

    // Every stored file is copied in, under a name without folders. (Moving
    // a conversation to the trash already removed its attachment.)
    $attachments = Attachment::query()->whereIn('chat_conversation_id', $conversations->modelKeys())->get();
    expect($attachments)->toHaveCount(4);

    foreach ($attachments as $attachment) {
        $conversationId = $attachment->chat_conversation_id;

        expect(bdeEntry($export, "attachments/conversations/{$conversationId}/{$attachment->id}-{$conversationId}.txt"))
            ->toBe('attachment-'.$conversationId);
    }

    expect(bdeEntry($export, "attachments/knowledge/{$file->id}-manual.txt"))->toBe('knowledge-bytes');

    // No query carries more parameters than one batch of ids (plus a few).
    expect($maxBindings)->toBeLessThanOrEqual(6);
});

it('gives a teammate their personal data only, never the workspace data', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');

    $agent = Agent::factory()->create();
    Conversation::factory()->create(['agent_id' => $agent->id]);
    $teammate = User::factory()->employee($agent->workspace->owner)->create();

    $export = bdeExport($teammate);
    app(BuildDataExportArchiveAction::class)->execute($export);

    $entries = bdeEntries($export->refresh());

    expect($export->status)->toBe(DataExportStatus::Ready)
        ->and($entries)->toContain('README.txt', 'profile.json', 'teams.json', 'api-keys.json')
        ->and($entries)->not->toContain('agents.json')
        ->and($entries)->not->toContain('conversations.json')
        ->and($entries)->not->toContain('messages.json')
        ->and($entries)->not->toContain('knowledge-sources.json')
        ->and($entries)->not->toContain('message-limit.json')
        ->and(json_decode(bdeEntry($export, 'profile.json'), true, flags: JSON_THROW_ON_ERROR)['id'])->toBe($teammate->id);
});

it('marks the export failed, leaves no archive or temp files, and rethrows when a step fails', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');

    $owner = Agent::factory()->create()->workspace->owner;
    // The last step fails, after the archive was written to disk.
    DataExport::updating(static function (DataExport $export): void {
        if ($export->status === DataExportStatus::Ready) {
            throw new RuntimeException('Database went away');
        }
    });

    $export = bdeExport($owner);

    expect(fn () => app(BuildDataExportArchiveAction::class)->execute($export))
        ->toThrow(RuntimeException::class, 'Database went away');

    $export->refresh();

    expect($export->status)->toBe(DataExportStatus::Failed)
        ->and($export->error_message)->toBe('Database went away')
        ->and($export->file_path)->toBeNull()
        ->and($export->file_size)->toBeNull()
        ->and($export->expires_at)->toBeNull()
        ->and(Storage::disk('local')->allFiles('users/'.$owner->external_id.'/data-exports'))->toBe([])
        ->and(Storage::disk('local')->allFiles('data-exports/tmp'))->toBe([]);

    Event::assertNotDispatched(DataExportReady::class);
});
