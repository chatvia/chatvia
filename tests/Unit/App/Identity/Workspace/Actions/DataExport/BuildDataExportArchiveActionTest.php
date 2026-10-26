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
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Building a data export: the person's data in a ZIP on the local disk, the
 * workspace's data for its owner only, streamed in batches without internal
 * columns or secrets, the stored files copied in. A failed step marks the
 * export failed and leaves no files behind.
 */
it('packages user data into a zip and marks the export ready', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');

    $user = User::factory()->create();

    $export = DataExport::factory()->create(['user_id' => $user->id]);

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
    return DataExport::factory()->create(['user_id' => $user->id]);
}

/**
 * The owner's export of a workspace bigger than one batch of two: five
 * conversations of two messages and one stored attachment each (one of them
 * in the trash), a knowledge file, and a chat of another workspace. Built
 * with batches of two, so it takes three rounds; `maxBindings` is the most
 * parameters one query of the build carried.
 *
 * @return array{export: DataExport, agent: Agent, conversations: Collection<int, Conversation>, file: Model, maxBindings: int}
 */
function bdeBuildBigWorkspace(): array
{
    Event::fake([DataExportReady::class]);
    Storage::fake('local');
    Storage::fake();

    $agent = Agent::factory()->create(['auth_secret' => 'cvsk_secret']);
    $conversations = Conversation::factory()->count(5)->create(['agent_id' => $agent->id, 'title' => 'Hello']);

    foreach ($conversations as $conversation) {
        Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]);

        $path = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/attachments/'.$conversation->id.'.txt';
        Attachment::factory()->forConversation($conversation)->stored('attachment-'.$conversation->id)->create([
            'filepath' => $path,
            'filename' => '../evil/'.$conversation->id.'.txt',
            'mime_type' => 'text/plain',
        ]);
        GeneratedMedia::factory()->forConversation($conversation)->stored('image-'.$conversation->id)->create();
    }

    $conversations->first()->delete();
    Conversation::factory()->create(['agent_id' => Agent::factory()->create()->id]);

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'manual.txt']);
    Storage::put($file->filepath, 'knowledge-bytes');

    $export = bdeExport($agent->workspace->owner);

    $maxBindings = 0;
    DB::listen(function (QueryExecuted $query) use (&$maxBindings): void {
        $maxBindings = max($maxBindings, count($query->bindings));
    });

    new BuildDataExportArchiveAction(batchSize: 2)->execute($export);

    return ['export' => $export->refresh(), 'agent' => $agent, 'conversations' => $conversations, 'file' => $file, 'maxBindings' => $maxBindings];
}

/** @return array<int|string, mixed> */
function bdeJson(DataExport $export, string $name): array
{
    return json_decode((string) bdeEntry($export, $name), true, flags: JSON_THROW_ON_ERROR);
}

it('exports every conversation of the workspace, trashed ones too, in queries no bigger than one batch', function (): void {
    ['export' => $export, 'conversations' => $conversations, 'maxBindings' => $maxBindings] = bdeBuildBigWorkspace();

    expect($export->status)->toBe(DataExportStatus::Ready)
        ->and(collect(bdeJson($export, 'conversations.json'))->pluck('id')->sort()->values()->all())->toBe($conversations->modelKeys())
        ->and(bdeJson($export, 'messages.json'))->toHaveCount(10)
        // No query carries more parameters than one batch of ids (plus a few).
        ->and($maxBindings)->toBeLessThanOrEqual(6);
});

it('writes the exported columns only, never internal ids or secrets', function (): void {
    ['export' => $export, 'agent' => $agent] = bdeBuildBigWorkspace();

    $conversations = bdeJson($export, 'conversations.json');
    $messages = bdeJson($export, 'messages.json');
    $agents = bdeJson($export, 'agents.json');
    $knowledge = bdeJson($export, 'knowledge-sources.json');

    expect($conversations[0]['title'])->toBe('Hello')
        ->and($conversations[0])->not->toHaveKeys(['workspace_id', 'analysis_attempts'])
        ->and($messages[0])->toHaveKey('content')
        ->and($messages[0])->not->toHaveKeys(['workspace_id', 'generation_id'])
        ->and($agents)->toHaveCount(1)
        ->and($agents[0]['external_id'])->toBe($agent->external_id)
        ->and($agents[0])->not->toHaveKey('auth_secret')
        ->and($knowledge['files'])->toHaveCount(1)
        ->and($knowledge['files'][0])->not->toHaveKeys(['filepath', 'checksum'])
        ->and($knowledge['texts'])->toBe([])
        ->and($knowledge['websites'])->toBe([]);
});

it('copies every stored file in under a name without folders', function (): void {
    ['export' => $export, 'conversations' => $conversations, 'file' => $file] = bdeBuildBigWorkspace();

    // Moving a conversation to the trash already removed its attachment.
    $attachments = Attachment::query()->whereIn('chat_conversation_id', $conversations->modelKeys())->get();

    expect($attachments)->toHaveCount(4)
        ->and(bdeEntry($export, "attachments/knowledge/{$file->id}-manual.txt"))->toBe('knowledge-bytes');

    foreach ($attachments as $attachment) {
        $conversationId = $attachment->chat_conversation_id;

        expect(bdeEntry($export, "attachments/conversations/{$conversationId}/{$attachment->id}-{$conversationId}.txt"))
            ->toBe('attachment-'.$conversationId);
    }

    $generated = GeneratedMedia::query()->whereIn('chat_conversation_id', $conversations->modelKeys())->get();

    expect($generated)->toHaveCount(4);

    foreach ($generated as $media) {
        expect(bdeEntry($export, "generated/conversations/{$media->chat_conversation_id}/{$media->id}.png"))
            ->toBe('image-'.$media->chat_conversation_id);
    }
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
