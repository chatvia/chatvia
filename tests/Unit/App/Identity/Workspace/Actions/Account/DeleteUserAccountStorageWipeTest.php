<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Storage;

/**
 * DeleteUserAccountAction wipes the storage tree the FK cascade would orphan:
 * `workspaces/{ext}`, which holds the agents' chat files, knowledge files,
 * each member's own files (photos, data exports) and the chatters' avatars,
 * and leaves other workspaces' files alone.
 */
beforeEach(function (): void {
    Storage::fake();
    Storage::fake('assets');
    Storage::fake('local');
});

it('wipes the chatters\' avatar trees and keeps those of other workspaces', function (): void {
    $owner = User::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($owner)->create();
    $otherChatter = Chatter::factory()->create();
    $photo = $chatter->storageRoot().'/profile-photos/avatar.jpg';
    $otherPhoto = $otherChatter->storageRoot().'/profile-photos/avatar.jpg';
    Storage::disk('assets')->put($photo, 'img');
    Storage::disk('assets')->put($otherPhoto, 'img');

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('assets')->assertMissing($photo);
    Storage::disk('assets')->assertExists($otherPhoto);
});

it('wipes each member\'s own tree on every disk and keeps those of other workspaces', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $bystander = User::factory()->create();
    $ownerPhoto = $owner->storageRoot().'/profile-photos/me.jpg';
    $employeeExport = $employee->storageRoot().'/data-exports/export.zip';
    $ownerLocalExport = $owner->storageRoot().'/data-exports/owner.zip';
    $employeeLocalExport = $employee->storageRoot().'/data-exports/employee.zip';
    $bystanderPhoto = $bystander->storageRoot().'/profile-photos/me.jpg';
    $bystanderExport = $bystander->storageRoot().'/data-exports/bystander.zip';
    Storage::disk('assets')->put($ownerPhoto, 'img');
    Storage::put($employeeExport, 'zip');
    Storage::disk('local')->put($ownerLocalExport, 'zip');
    Storage::disk('local')->put($employeeLocalExport, 'zip');
    Storage::disk('assets')->put($bystanderPhoto, 'img');
    Storage::disk('local')->put($bystanderExport, 'zip');

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('assets')->assertMissing($ownerPhoto);
    Storage::assertMissing($employeeExport);
    Storage::disk('local')->assertMissing($ownerLocalExport);
    Storage::disk('local')->assertMissing($employeeLocalExport);
    Storage::disk('assets')->assertExists($bystanderPhoto);
    Storage::disk('local')->assertExists($bystanderExport);
});

it('wipes the chat attachments and knowledge files the FK cascade would orphan', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    Storage::put($file->filepath, 'pdf-bytes');

    app(DeleteUserAccountAction::class)->execute($owner);

    expect($file->filepath)->toStartWith('workspaces/'.$agent->workspace->external_id.'/sources/')
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
    Storage::assertMissing($file->filepath);
});
