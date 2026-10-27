<?php

/**
 * DeleteUserAccountAction wipes the storage tree the FK cascade would orphan:
 * `workspaces/{ext}`, which holds the agents' chat files, knowledge files,
 * each member's own files (photos, data exports) and the chatters' avatars,
 * and leaves other workspaces' files alone.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // The teardown wipes workspaces/{external_id} on the default, assets and
    // local disks.
    Storage::fake();
    Storage::fake('assets');
    Storage::fake('local');
});

it('wipes the chatters avatar trees and keeps those of other workspaces', function (): void {
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

it('wipes each member own storage tree and keeps those of other workspaces', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $bystander = User::factory()->create();

    $ownerPhoto = $owner->storageRoot().'/profile-photos/me.jpg';
    $employeeExport = $employee->storageRoot().'/data-exports/export.zip';
    $bystanderPhoto = $bystander->storageRoot().'/profile-photos/me.jpg';
    Storage::disk('assets')->put($ownerPhoto, 'img');
    Storage::put($employeeExport, 'zip');
    Storage::disk('assets')->put($bystanderPhoto, 'img');

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('assets')->assertMissing($ownerPhoto);
    Storage::assertMissing($employeeExport);
    Storage::disk('assets')->assertExists($bystanderPhoto);
});

it('wipes the members\' data exports on the local disk', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $bystander = User::factory()->create();

    $ownerExport = $owner->storageRoot().'/data-exports/owner.zip';
    $employeeExport = $employee->storageRoot().'/data-exports/employee.zip';
    $bystanderExport = $bystander->storageRoot().'/data-exports/bystander.zip';
    foreach ([$ownerExport, $employeeExport, $bystanderExport] as $path) {
        Storage::disk('local')->put($path, 'zip');
    }

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk('local')->assertMissing($ownerExport);
    Storage::disk('local')->assertMissing($employeeExport);
    Storage::disk('local')->assertExists($bystanderExport);
});

it('wipes conversation attachment files that the FK cascade would otherwise orphan', function (): void {
    // chat_attachments rows go by FK cascade with their conversation, which
    // never fires the Attachment deleting-observer. The files live under the
    // workspace prefix, so the storage wipe removes them.
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
});

it('wipes the workspace knowledge-file storage tree the FK cascade would orphan', function (): void {
    // Sources are removed by FK cascade, which never fires their observers — so
    // the knowledge files under workspaces/{external_id}/sources/… would leak.
    // The teardown wipes the whole workspaces/{external_id} tree to catch them.
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $knowledgePath = $file->filepath;
    expect($knowledgePath)->toStartWith('workspaces/'.$agent->workspace->external_id.'/sources/');
    Storage::put($knowledgePath, 'pdf-bytes');
    Storage::assertExists($knowledgePath);

    app(DeleteUserAccountAction::class)->execute($owner);

    Storage::assertMissing($knowledgePath);
});
