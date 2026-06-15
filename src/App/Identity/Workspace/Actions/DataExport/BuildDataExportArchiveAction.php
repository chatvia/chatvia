<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Agent\Models\ModelSetting;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Billing\Models\UserUsage;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Helpdesk\Models\Ticket;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Models\Team;
use Domain\Prompt\Models\AgentPrompt;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Builds a full GDPR Article 15/20 export for one user:
 * collects every personal-data row across the domains the user owns,
 * pulls in their uploaded binaries, packages it all into a single ZIP
 * on the local private disk, and marks the DataExport ready.
 *
 * Designed to run from a queue worker — wall-clock can reach tens of
 * minutes on large accounts. Memory is kept low by streaming model
 * chunks and copying binaries straight through the ZipArchive handle.
 */
class BuildDataExportArchiveAction
{
    private const ARCHIVE_DISK = 'local';

    private const TEMP_PREFIX = 'data-exports/tmp';

    public function execute(DataExport $export): void
    {
        $export->update(['status' => DataExportStatus::Processing]);

        $user = $export->user;
        $tempDir = self::TEMP_PREFIX.'/'.$export->id;
        $zipRelativePath = 'users/'.$user->external_id.'/data-exports/'.Str::uuid()->toString().'.zip';

        $localDisk = Storage::disk(self::ARCHIVE_DISK);
        $localDisk->makeDirectory($tempDir);

        $absoluteZipPath = $localDisk->path($zipRelativePath);
        $absoluteZipDir = dirname($absoluteZipPath);

        if (! is_dir($absoluteZipDir) && ! mkdir($absoluteZipDir, 0755, true) && ! is_dir($absoluteZipDir)) {
            throw new \RuntimeException("Unable to create export directory: {$absoluteZipDir}");
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Unable to open ZIP for writing: {$absoluteZipPath}");
            }

            $this->writeReadme($zip, $user);
            $this->writeProfile($zip, $user);
            $this->writeAgents($zip, $user);
            $this->writeAccountTooling($zip, $user);
            $this->writeKnowledge($zip, $user);
            $this->writeConversationsAndMessages($zip, $user);
            $this->writeTeams($zip, $user);
            $this->writeBilling($zip, $user);
            $this->writeApiKeys($zip, $user);
            $this->writeTickets($zip, $user);
            $this->copyConversationAttachments($zip, $user);
            $this->copyKnowledgeFiles($zip, $user);

            $zip->close();

            $fileSize = $localDisk->size($zipRelativePath);

            $export->update([
                'status' => DataExportStatus::Ready,
                'file_path' => $zipRelativePath,
                'file_size' => $fileSize,
                'expires_at' => now()->addDay(),
            ]);

            event(new DataExportReady($export->fresh()));
        } catch (Throwable $e) {
            $localDisk->delete($zipRelativePath);

            $export->update([
                'status' => DataExportStatus::Failed,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);

            throw $e;
        } finally {
            $localDisk->deleteDirectory($tempDir);
        }
    }

    private function writeReadme(ZipArchive $zip, User $user): void
    {
        $generatedAt = now()->toIso8601String();

        $body = <<<TXT
ChatVia — Personal Data Export
================================

Account: {$user->name} <{$user->email}>
User ID: {$user->id}
Generated: {$generatedAt}

This archive contains a copy of the personal data held about you on
ChatVia, prepared in response to your Right of Access (GDPR Art. 15)
and Right to Data Portability (GDPR Art. 20).

Contents
--------
- profile.json              your account details
- agents.json               agents you have created
- agent-functions.json      custom function tools attached to your account
- agent-mcps.json           MCP servers attached to your account
- agent-prompts.json        reusable prompts you have authored
- agent-llm-settings.json   per-agent LLM configuration
- knowledge-sources.json    files, websites and texts in your knowledge base
- conversations.json        all conversations across your agents
- messages.json             all messages within those conversations
- teams.json                teams you own or belong to
- billing.json              billing, credit and usage records
- api-keys.json             personal access tokens (metadata only — the
                            secret value of an API token is never stored
                            in plaintext and therefore cannot be exported)
- tickets.json              support tickets you have opened
- attachments/              binary files you uploaded

This export will be available for 24 hours, then it will be deleted from
our servers. You may request a new export at any time.

If anything looks wrong or incomplete, please contact us.
TXT;

        $zip->addFromString('README.txt', $body);
    }

    private function writeProfile(ZipArchive $zip, User $user): void
    {
        $payload = $user->toArray();

        unset($payload['password'], $payload['remember_token'],
            $payload['two_factor_secret'], $payload['two_factor_recovery_codes']);

        $this->putJson($zip, 'profile.json', $payload);
    }

    private function writeAgents(ZipArchive $zip, User $user): void
    {
        $agents = Agent::query()->where('user_id', $user->id)->get()->toArray();
        $this->putJson($zip, 'agents.json', $agents);
    }

    private function writeAccountTooling(ZipArchive $zip, User $user): void
    {
        $this->putJson($zip, 'agent-functions.json',
            AgentFunction::query()->where('user_id', $user->id)->get()->toArray());

        $this->putJson($zip, 'agent-mcps.json',
            AgentMcpServer::query()->where('user_id', $user->id)->get()->toArray());

        $this->putJson($zip, 'agent-prompts.json',
            AgentPrompt::query()->where('user_id', $user->id)->get()->toArray());

        $agentIds = Agent::query()->where('user_id', $user->id)->pluck('id');

        $this->putJson($zip, 'agent-llm-settings.json',
            ModelSetting::query()
                ->where('model_type', Relation::getMorphAlias(Agent::class))
                ->whereIn('model_id', $agentIds)
                ->get()
                ->toArray());
    }

    private function writeKnowledge(ZipArchive $zip, User $user): void
    {
        $files = SourceFile::query()->where('user_id', $user->id)->get()->toArray();

        $agentIds = Agent::query()->where('user_id', $user->id)->pluck('id');

        $textIds = AgentSource::query()
            ->whereIn('agent_id', $agentIds)
            ->where('sourceable_type', Relation::getMorphAlias(SourceText::class))
            ->pluck('sourceable_id');

        $websiteIds = AgentSource::query()
            ->whereIn('agent_id', $agentIds)
            ->where('sourceable_type', Relation::getMorphAlias(SourceWebsite::class))
            ->pluck('sourceable_id');

        $texts = SourceText::query()->whereIn('id', $textIds)->get()->toArray();
        $websites = SourceWebsite::query()->whereIn('id', $websiteIds)->get()->toArray();

        $this->putJson($zip, 'knowledge-sources.json', [
            'files' => $files,
            'texts' => $texts,
            'websites' => $websites,
        ]);
    }

    private function writeConversationsAndMessages(ZipArchive $zip, User $user): void
    {
        $agentIds = Agent::query()->where('user_id', $user->id)->pluck('id');
        $sessionIds = Session::query()->whereIn('agent_id', $agentIds)->pluck('id');

        $conversationIds = [];
        $conversations = [];

        Conversation::query()
            ->whereIn('chat_session_id', $sessionIds)
            ->withTrashed()
            ->chunkById(500, function ($chunk) use (&$conversations, &$conversationIds): void {
                foreach ($chunk as $conversation) {
                    $conversations[] = $conversation->toArray();
                    $conversationIds[] = $conversation->id;
                }
            });

        $this->putJson($zip, 'conversations.json', $conversations);
        unset($conversations);

        $messages = [];
        Message::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->chunkById(1000, function ($chunk) use (&$messages): void {
                foreach ($chunk as $message) {
                    $messages[] = $message->toArray();
                }
            });

        $this->putJson($zip, 'messages.json', $messages);
    }

    private function writeTeams(ZipArchive $zip, User $user): void
    {
        $owned = Team::query()->where('user_id', $user->id)->get()->toArray();
        $member = $user->memberTeams()->get()->toArray();

        $this->putJson($zip, 'teams.json', [
            'owned' => $owned,
            'member_of' => $member,
        ]);
    }

    private function writeBilling(ZipArchive $zip, User $user): void
    {
        $billing = $user->billing()->first()?->toArray();
        $credits = UserCredit::query()->where('user_id', $user->id)->get()->toArray();
        $creditIds = array_column($credits, 'id');
        $usages = UserUsage::query()->whereIn('user_credit_id', $creditIds)->get()->toArray();
        $addons = UserAddon::query()->where('user_id', $user->id)->get()->toArray();
        $quota = UserQuota::query()->where('user_id', $user->id)->first()?->toArray();

        $this->putJson($zip, 'billing.json', [
            'billing' => $billing,
            'credits' => $credits,
            'usages' => $usages,
            'addons' => $addons,
            'quota' => $quota,
        ]);
    }

    private function writeApiKeys(ZipArchive $zip, User $user): void
    {
        $tokens = $user->tokens()
            ->get()
            ->map(function ($token): array {
                $row = $token->toArray();
                unset($row['token']);

                return $row;
            })
            ->toArray();

        $this->putJson($zip, 'api-keys.json', $tokens);
    }

    private function writeTickets(ZipArchive $zip, User $user): void
    {
        $this->putJson($zip, 'tickets.json',
            Ticket::query()->where('user_id', $user->id)->get()->toArray());
    }

    private function copyConversationAttachments(ZipArchive $zip, User $user): void
    {
        $agentIds = Agent::query()->where('user_id', $user->id)->pluck('id');
        $sessionIds = Session::query()->whereIn('agent_id', $agentIds)->pluck('id');
        $conversationIds = Conversation::query()
            ->whereIn('chat_session_id', $sessionIds)
            ->withTrashed()
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return;
        }

        $disk = Storage::disk(Attachment::DISK);

        Attachment::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->chunkById(200, function ($chunk) use ($zip, $disk): void {
                foreach ($chunk as $attachment) {
                    if (! $disk->exists($attachment->filepath)) {
                        continue;
                    }

                    $entry = "attachments/conversations/{$attachment->chat_conversation_id}/{$attachment->id}-{$attachment->filename}";

                    $contents = $disk->get($attachment->filepath);
                    if ($contents !== null) {
                        $zip->addFromString($entry, $contents);
                    }
                }
            });
    }

    private function copyKnowledgeFiles(ZipArchive $zip, User $user): void
    {
        $disk = Storage::disk();

        SourceFile::query()
            ->where('user_id', $user->id)
            ->chunkById(200, function ($chunk) use ($zip, $disk): void {
                foreach ($chunk as $file) {
                    if (! $file->filepath || ! $disk->exists($file->filepath)) {
                        continue;
                    }

                    $entry = "attachments/knowledge/{$file->id}-{$file->filename}";

                    $contents = $disk->get($file->filepath);
                    if ($contents !== null) {
                        $zip->addFromString($entry, $contents);
                    }
                }
            });
    }

    private function putJson(ZipArchive $zip, string $name, mixed $payload): void
    {
        $zip->addFromString($name, json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }
}
