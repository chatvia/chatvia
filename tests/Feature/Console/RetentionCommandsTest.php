<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The scheduled retention commands run their Action: each one changes a row
 * past its workspace's window. What the Actions keep and remove is tested
 * next to them in tests/Unit.
 */
it('anonymizes old visitor sessions with app:anonymize-chat-sessions', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['anonymize_after_days' => 30]);
    $session = Session::factory()->create(['agent_id' => $agent->id, 'email' => 'dana@example.com']);
    DB::table('chat_sessions')->where('id', $session->id)->update(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);

    $this->artisan('app:anonymize-chat-sessions', ['--batch' => 1])->assertSuccessful();

    expect(DB::table('chat_sessions')->where('id', $session->id)->first())
        ->anonymized_at->not->toBeNull()
        ->email->toBeNull();
});

it('deletes stale uploads that joined no conversation with app:prune-chat-attachments', function (): void {
    Storage::fake(Attachment::DISK);
    Storage::disk(Attachment::DISK)->put('users/x/agents/y/attachments/doc.pdf', 'pdf');
    $upload = Attachment::create(['filepath' => 'users/x/agents/y/attachments/doc.pdf', 'filename' => 'doc.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3]);
    DB::table('chat_attachments')->where('id', $upload->id)->update(['created_at' => now()->subDays(31)]);

    $this->artisan('app:prune-chat-attachments')->assertSuccessful();

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing('users/x/agents/y/attachments/doc.pdf');
});

it('deletes leads past the workspace\'s window with app:prune-leads', function (): void {
    $agent = Agent::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_leads_days' => 30]);
    $lead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($agent)->create())->submitted()->create(['submitted_at' => now()->subDays(60)]);

    $this->artisan('app:prune-leads', ['--batch' => 1])->assertSuccessful();

    expect(FormSubmission::query()->whereKey($lead->id)->exists())->toBeFalse();
});
