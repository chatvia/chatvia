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

it('packages user data into a zip and marks the export ready', function (): void {
    Event::fake([DataExportReady::class]);

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

    $absolute = Storage::disk('local')->path($export->file_path);
    $zip = new ZipArchive;
    expect($zip->open($absolute))->toBeTrue();

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();

    expect($entries)->toContain('README.txt')
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
function exportEntry(DataExport $export, string $name): string|false
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->file_path));
    $contents = $zip->getFromName($name);
    $zip->close();

    return $contents;
}

it('streams a workspace bigger than one batch, with explicit columns and short queries', function (): void {
    Event::fake([DataExportReady::class]);
    Storage::fake('local');
    Storage::fake();
    Storage::fake('public');

    $agent = Agent::factory()->create(['auth_secret' => 'cvsk_secret']);
    $owner = $agent->workspace->owner;
    $other = Agent::factory()->create();

    // Five conversations of two messages and one attachment each; one is in
    // the trash. Batches of two need three rounds.
    $conversations = Conversation::factory()->count(5)->create(['agent_id' => $agent->id, 'title' => 'Hello']);

    foreach ($conversations as $conversation) {
        Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]);

        $path = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/attachments/'.$conversation->id.'.txt';
        Storage::disk('public')->put($path, 'attachment-'.$conversation->id);
        Attachment::create([
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

    (new BuildDataExportArchiveAction(batchSize: 2))->execute($export);

    $export->refresh();
    expect($export->status)->toBe(DataExportStatus::Ready);

    $exportedConversations = json_decode(exportEntry($export, 'conversations.json'), true, flags: JSON_THROW_ON_ERROR);
    $exportedMessages = json_decode(exportEntry($export, 'messages.json'), true, flags: JSON_THROW_ON_ERROR);
    $agents = json_decode(exportEntry($export, 'agents.json'), true, flags: JSON_THROW_ON_ERROR);
    $knowledge = json_decode(exportEntry($export, 'knowledge-sources.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($exportedConversations)->pluck('id')->sort()->values()->all())->toBe($conversations->modelKeys())
        ->and($exportedConversations[0]['title'])->toBe('Hello')
        ->and($exportedConversations[0])->not->toHaveKeys(['workspace_id', 'analysis_attempts'])
        ->and($exportedMessages)->toHaveCount(10)
        ->and($exportedMessages[0])->toHaveKey('content')
        ->and($exportedMessages[0])->not->toHaveKeys(['workspace_id', 'cost_in_micro_cents', 'generation_id'])
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

        expect(exportEntry($export, "attachments/conversations/{$conversationId}/{$attachment->id}-{$conversationId}.txt"))
            ->toBe('attachment-'.$conversationId);
    }

    expect(exportEntry($export, "attachments/knowledge/{$file->id}-manual.txt"))->toBe('knowledge-bytes');

    // No query carries more parameters than one batch of ids (plus a few).
    expect($maxBindings)->toBeLessThanOrEqual(6);
});
