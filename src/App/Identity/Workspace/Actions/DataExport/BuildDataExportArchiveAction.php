<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Support\Json\JsonArrayFile;
use Throwable;
use ZipArchive;

/**
 * Builds a full GDPR Article 15/20 export for one user: their profile, teams
 * and API keys, and — for a workspace owner — every row and uploaded binary
 * of the workspace (agents, knowledge, conversations, tools). Teammates get
 * their personal data only. It packages it all into a single ZIP on the local
 * private disk, and marks the DataExport ready.
 *
 * Runs from a queue worker; a large workspace takes many minutes. Memory stays
 * flat however big the workspace is: rows are read in id-ordered batches
 * (never a whole table, never an id list in one query) and written straight
 * to JSON files in a temp folder, and stored files are copied to temp files
 * as streams. The ZIP only references those files and reads them when it is
 * closed, so nothing large is held in memory.
 *
 * Each section lists its columns: secrets (signing keys, auth headers, OAuth
 * tokens), search vectors and internal bookkeeping (job ids, checksums, file
 * paths) are never exported.
 */
class BuildDataExportArchiveAction
{
    private const string ARCHIVE_DISK = 'local';

    private const string TEMP_PREFIX = 'data-exports/tmp';

    private const array PROFILE_COLUMNS = ['id', 'external_id', 'name', 'email', 'email_verified_at', 'two_factor_confirmed_at', 'locale', 'date_format', 'registered_ip', 'registered_country_code', 'last_login_at', 'deactivated_at', 'created_at', 'updated_at', 'avatar'];

    private const array API_KEY_COLUMNS = ['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at'];

    private const array AGENT_COLUMNS = ['id', 'external_id', 'team_id', 'name', 'slug', 'domain', 'system_instructions', 'agent_prompt_id', 'llm_id', 'is_public', 'metadata', 'source', 'created_at', 'updated_at'];

    private const array FUNCTION_COLUMNS = ['id', 'external_id', 'internal_name', 'display_name', 'description', 'parameters_schema', 'webhook_url', 'webhook_method', 'auth_type', 'requires_approval', 'timeout_ms', 'max_response_bytes', 'is_enabled', 'last_invoked_at', 'invocation_count', 'created_at', 'updated_at'];

    private const array MCP_COLUMNS = ['id', 'external_id', 'category', 'connector_slug', 'name', 'description', 'slug', 'url', 'transport', 'auth_type', 'oauth_scopes', 'timeout_ms', 'is_enabled', 'last_invoked_at', 'invocation_count', 'created_at', 'updated_at'];

    private const array PROMPT_COLUMNS = ['id', 'external_id', 'name', 'prompt', 'created_at', 'updated_at'];

    private const array FILE_COLUMNS = ['id', 'external_id', 'filename', 'filetype', 'mime_type', 'size_in_kb', 'language', 'document_classification', 'length', 'status', 'created_at', 'updated_at'];

    private const array TEXT_COLUMNS = ['id', 'external_id', 'excerpt', 'length', 'language', 'document_classification', 'status', 'created_at', 'updated_at'];

    private const array WEBSITE_COLUMNS = ['id', 'external_id', 'url', 'options', 'language', 'document_classification', 'status', 'created_at', 'updated_at'];

    private const array CONVERSATION_COLUMNS = ['id', 'external_id', 'agent_id', 'chat_session_id', 'source', 'title', 'is_visible', 'sentiment', 'tone', 'handoff_status', 'handoff_at', 'last_activity_at', 'expires_at', 'deleted_at', 'created_at', 'updated_at'];

    private const array MESSAGE_COLUMNS = ['id', 'external_id', 'chat_conversation_id', 'role', 'source', 'content', 'metadata', 'sentiment', 'tone', 'feedback', 'feedback_comment', 'confidence_score', 'response_time_ms', 'prompt_tokens', 'completion_tokens', 'read_at', 'created_at', 'updated_at'];

    /**
     * @param  int  $batchSize  rows read per query; also the conversations whose
     *                          messages and attachments are read together
     */
    public function __construct(
        private readonly int $batchSize = 500,
    ) {}

    public function execute(DataExport $export): void
    {
        $export->update(['status' => DataExportStatus::Processing]);

        $user = $export->user;
        $tempDir = self::TEMP_PREFIX.'/'.$export->id;
        $zipRelativePath = $user->storageRoot().'/data-exports/'.Str::uuid()->toString().'.zip';

        $localDisk = Storage::disk(self::ARCHIVE_DISK);
        $localDisk->makeDirectory($tempDir);
        $tempPath = $localDisk->path($tempDir);

        $absoluteZipPath = $localDisk->path($zipRelativePath);
        $absoluteZipDir = dirname($absoluteZipPath);

        if (! File::isDirectory($absoluteZipDir)) {
            // Quiet, so a folder another worker made meanwhile is no error.
            File::makeDirectory($absoluteZipDir, 0755, true, true);
        }

        if (! File::isDirectory($absoluteZipDir)) {
            throw new RuntimeException("Unable to create export directory: {$absoluteZipDir}");
        }

        $zip = new ZipArchive;

        try {
            if ($zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Unable to open ZIP for writing: {$absoluteZipPath}");
            }

            $this->writeReadme($zip, $user);
            $this->writeProfile($zip, $user);
            $this->writeTeams($zip, $user);
            $this->writeApiKeys($zip, $user);

            // The workspace's data (agents, knowledge, conversations, tools)
            // goes to its owner only; a teammate gets their personal data.
            if ($user->isWorkspaceOwner()) {
                $workspace = $user->workspace();

                $this->writeAgents($zip, $workspace, $tempPath);
                $this->writeAccountTooling($zip, $workspace, $tempPath);
                $this->writeKnowledge($zip, $workspace, $tempPath);
                $this->writeConversations($zip, $workspace, $tempPath);
                $this->writeMessageLimit($zip, $workspace);
                $this->copyKnowledgeFiles($zip, $workspace, $tempPath);
            }

            // Writes the archive, reading every temp file added above.
            if (! $zip->close()) {
                throw new RuntimeException("Unable to write ZIP: {$absoluteZipPath}");
            }

            $fileSize = $localDisk->size($zipRelativePath);

            $export->update([
                'status' => DataExportStatus::Ready,
                'file_path' => $zipRelativePath,
                'file_size' => $fileSize,
                'expires_at' => now()->addDay(),
            ]);

            DataExportReady::dispatch($export->fresh());
        } catch (Throwable $e) {
            $this->discard($zip);
            $localDisk->delete($zipRelativePath);

            // The archive is gone, so nothing may point at it, also when
            // the failure came after the "ready" values were set.
            $export->update([
                'status' => DataExportStatus::Failed,
                'error_message' => Str::limit($e->getMessage(), 1000),
                'file_path' => null,
                'file_size' => null,
                'expires_at' => null,
            ]);

            throw $e;
        } finally {
            $localDisk->deleteDirectory($tempDir);
        }
    }

    private function writeReadme(ZipArchive $zip, User $user): void
    {
        $generatedAt = now()->toIso8601String();
        $heading = 'ChatVia.ai — Personal Data Export';
        $underline = Str::repeat('=', Str::length($heading));

        $body = <<<TXT
{$heading}
{$underline}

Account: {$user->name} <{$user->email}>
User ID: {$user->id}
Generated: {$generatedAt}

This archive contains a copy of the personal data held about you on
ChatVia.ai, prepared in response to your Right of Access (GDPR Art. 15)
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
- message-limit.json        your monthly message limit, if one is set
- api-keys.json             personal access tokens (metadata only — the
                            secret value of an API token is never stored
                            in plaintext and therefore cannot be exported)
- attachments/              binary files you uploaded
- generated/                images and audio your agents made in chats

Secrets you configured (function signing keys and auth headers, MCP server
credentials) are not included.

This export will be available for 24 hours, then it will be deleted from
our servers. You may request a new export at any time.

If anything looks wrong or incomplete, please contact us.
TXT;

        $zip->addFromString('README.txt', $body);
    }

    private function writeProfile(ZipArchive $zip, User $user): void
    {
        $this->putJson($zip, 'profile.json', $user->only(self::PROFILE_COLUMNS));
    }

    private function writeTeams(ZipArchive $zip, User $user): void
    {
        $owned = $user->ownedTeams()->get()->toArray();
        $member = $user->memberTeams()->get()->toArray();

        $this->putJson($zip, 'teams.json', [
            'owned' => $owned,
            'member_of' => $member,
        ]);
    }

    private function writeApiKeys(ZipArchive $zip, User $user): void
    {
        $tokens = $user->tokens()
            ->get(self::API_KEY_COLUMNS)
            ->map(fn (Model $token): array => $token->only(self::API_KEY_COLUMNS))
            ->all();

        $this->putJson($zip, 'api-keys.json', $tokens);
    }

    private function writeMessageLimit(ZipArchive $zip, Workspace $workspace): void
    {
        $this->putJson($zip, 'message-limit.json', [
            'message_limit' => $workspace->messageLimit()->first()?->toArray(),
        ]);
    }

    private function writeAgents(ZipArchive $zip, Workspace $workspace, string $tempPath): void
    {
        $this->writeRows($zip, $tempPath, 'agents.json',
            Agent::query()->where('workspace_id', $workspace->id), self::AGENT_COLUMNS);
    }

    private function writeAccountTooling(ZipArchive $zip, Workspace $workspace, string $tempPath): void
    {
        $this->writeRows($zip, $tempPath, 'agent-functions.json',
            AgentFunction::query()->where('workspace_id', $workspace->id), self::FUNCTION_COLUMNS);

        $this->writeRows($zip, $tempPath, 'agent-mcps.json',
            AgentMcpServer::query()->where('workspace_id', $workspace->id), self::MCP_COLUMNS);

        $this->writeRows($zip, $tempPath, 'agent-prompts.json',
            AgentPrompt::query()->where('workspace_id', $workspace->id), self::PROMPT_COLUMNS);

        $path = $tempPath.'/agent-llm-settings.json';
        $file = JsonArrayFile::open($path);

        ModelSetting::query()
            ->select(['id', 'model_id', 'group', 'key', 'value', 'data_type', 'updated_at'])
            ->where('model_type', (new Agent)->getMorphClass())
            ->whereIn('model_id', Agent::query()->select('id')->where('workspace_id', $workspace->id))
            ->lazyById($this->batchSize)
            ->each(fn (ModelSetting $setting) => $file->push([
                'agent_id' => $setting->model_id,
                'group' => $setting->group,
                'key' => $setting->key,
                'value' => $setting->value,
                'data_type' => $setting->data_type,
                'updated_at' => $setting->updated_at,
            ]));

        $file->close();
        $this->addFile($zip, $path, 'agent-llm-settings.json');
    }

    /**
     * One JSON object with an array per source type. Texts and websites carry
     * `workspace_id` like files, so every source of the workspace is found
     * without listing its agents.
     */
    private function writeKnowledge(ZipArchive $zip, Workspace $workspace, string $tempPath): void
    {
        $path = $tempPath.'/knowledge-sources.json';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path} for writing.");
        }

        $sections = [
            'files' => [SourceFile::query(), self::FILE_COLUMNS],
            'texts' => [SourceText::query(), self::TEXT_COLUMNS],
            'websites' => [SourceWebsite::query(), self::WEBSITE_COLUMNS],
        ];

        fwrite($handle, '{');

        foreach (array_keys($sections) as $index => $name) {
            [$query, $columns] = $sections[$name];

            fwrite($handle, ($index === 0 ? '' : ',')."\n\"{$name}\": ");

            $array = JsonArrayFile::within($handle);
            $this->streamRows($array, $query->where('workspace_id', $workspace->id), $columns);
            $array->close();
        }

        fwrite($handle, "\n}\n");
        fclose($handle);

        $this->addFile($zip, $path, 'knowledge-sources.json');
    }

    /**
     * Conversations (trashed ones too: they are still held until purged), and
     * for each batch of them their messages, attachment files and the images
     * and audio their agents made. Keyed by
     * `workspace_id`, which the (workspace_id, id) unique index pages in id
     * order; each message query names one batch of conversations only.
     */
    private function writeConversations(ZipArchive $zip, Workspace $workspace, string $tempPath): void
    {
        $conversationsPath = $tempPath.'/conversations.json';
        $messagesPath = $tempPath.'/messages.json';

        $conversations = JsonArrayFile::open($conversationsPath);
        $messages = JsonArrayFile::open($messagesPath);
        $disk = Storage::disk(Attachment::DISK);

        Conversation::query()
            ->withTrashed()
            ->select(self::CONVERSATION_COLUMNS)
            ->where('workspace_id', $workspace->id)
            ->chunkById($this->batchSize, function (Collection $batch) use ($zip, $tempPath, $conversations, $messages, $disk): void {
                foreach ($batch as $conversation) {
                    $conversations->push($conversation->only(self::CONVERSATION_COLUMNS));
                }

                $this->streamRows($messages, Message::query()->whereIn('chat_conversation_id', $batch->modelKeys()), self::MESSAGE_COLUMNS);

                Attachment::query()
                    ->select(['id', 'chat_conversation_id', 'filepath', 'filename'])
                    ->whereIn('chat_conversation_id', $batch->modelKeys())
                    ->lazyById($this->batchSize)
                    ->each(fn (Attachment $attachment) => $this->copyStoredFile(
                        $zip,
                        $disk,
                        $attachment->filepath,
                        "attachments/conversations/{$attachment->chat_conversation_id}/{$attachment->id}-".$this->safeName($attachment->filename),
                        $tempPath,
                    ));

                GeneratedMedia::query()
                    ->select(['id', 'chat_conversation_id', 'filepath'])
                    ->whereIn('chat_conversation_id', $batch->modelKeys())
                    ->whereNotNull('filepath')
                    ->lazyById($this->batchSize)
                    ->each(fn (GeneratedMedia $media) => $this->copyStoredFile(
                        $zip,
                        $disk,
                        $media->filepath,
                        "generated/conversations/{$media->chat_conversation_id}/{$media->id}.".Str::afterLast($media->filepath ?? '', '.'),
                        $tempPath,
                    ));
            });

        $conversations->close();
        $messages->close();

        $this->addFile($zip, $conversationsPath, 'conversations.json');
        $this->addFile($zip, $messagesPath, 'messages.json');
    }

    private function copyKnowledgeFiles(ZipArchive $zip, Workspace $workspace, string $tempPath): void
    {
        $disk = Storage::disk();

        SourceFile::query()
            ->select(['id', 'filepath', 'filename'])
            ->where('workspace_id', $workspace->id)
            ->lazyById($this->batchSize)
            ->each(fn (SourceFile $file) => $this->copyStoredFile(
                $zip,
                $disk,
                $file->filepath,
                "attachments/knowledge/{$file->id}-".$this->safeName($file->filename),
                $tempPath,
            ));
    }

    /**
     * Stream a query's rows into a new JSON file and add it to the archive.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<string>  $columns
     */
    private function writeRows(ZipArchive $zip, string $tempPath, string $entry, Builder $query, array $columns): void
    {
        $path = $tempPath.'/'.$entry;
        $file = JsonArrayFile::open($path);

        $this->streamRows($file, $query, $columns);

        $file->close();
        $this->addFile($zip, $path, $entry);
    }

    /**
     * Push a query's rows, only the listed columns, in id-ordered batches.
     * Values go through the model's casts, so encrypted columns are written
     * decrypted and enums as their values.
     *
     * @param  Builder<covariant Model>  $query
     * @param  list<string>  $columns
     */
    private function streamRows(JsonArrayFile $file, Builder $query, array $columns): void
    {
        $query->select($columns)
            ->lazyById($this->batchSize)
            ->each(fn (Model $row) => $file->push($row->only($columns)));
    }

    /**
     * Copy a stored file into a temp file as a stream, then add that to the
     * archive. A file missing from storage is skipped.
     */
    private function copyStoredFile(ZipArchive $zip, Filesystem $disk, ?string $path, string $entry, string $tempPath): void
    {
        if (blank($path)) {
            return;
        }

        $source = $disk->readStream($path);

        if (! is_resource($source)) {
            return;
        }

        $tempFile = tempnam($tempPath, 'file');
        $target = $tempFile === false ? false : fopen($tempFile, 'wb');

        try {
            if ($target === false) {
                throw new RuntimeException('Unable to create a temp file for the export.');
            }

            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);

            if (is_resource($target)) {
                fclose($target);
            }
        }

        $this->addFile($zip, (string) $tempFile, $entry);
    }

    private function addFile(ZipArchive $zip, string $path, string $entry): void
    {
        if (! $zip->addFile($path, $entry)) {
            throw new RuntimeException("Unable to add {$entry} to the export.");
        }
    }

    /**
     * A file name from user input, without any folder part, so an entry can
     * never point outside its folder when the archive is unpacked.
     */
    private function safeName(string $filename): string
    {
        $name = basename(Str::replace('\\', '/', $filename));

        return in_array($name, ['', '.', '..'], true) ? 'file' : $name;
    }

    /**
     * Drop an archive that failed half-way: forget its pending entries so
     * closing it writes nothing.
     */
    private function discard(ZipArchive $zip): void
    {
        try {
            $zip->unchangeAll();
            $zip->close();
        } catch (Throwable) {
            // Not open, or already closed: nothing to drop.
        }
    }

    private function putJson(ZipArchive $zip, string $name, mixed $payload): void
    {
        $zip->addFromString($name, json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ));
    }
}
