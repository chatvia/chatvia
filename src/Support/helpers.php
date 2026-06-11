<?php

use Domain\Agent\Models\Agent as AgentModel;
use Domain\Agent\Services\Agent;
use Domain\Knowledge\Document\LibreOffice;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Support\MimeType;

if (! function_exists('create_temp_dir')) {
    function create_temp_dir(string $append = ''): string
    {
        $path = sys_get_temp_dir().$append;

        // If a subdirectory path is appended and doesn't exist, create it
        if (! blank($path) && ! is_dir($path)) {
            if (! mkdir($path, 0755, true) && ! is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }

        return $path;
    }
}

if (! function_exists('cache_dir')) {
    function cache_dir(): string
    {
        return config('cache.stores.file.path');
    }
}

if (! function_exists('create_temp_file')) {
    function create_temp_file(string $filename, string $content): string
    {
        $tempFile = create_temp_dir().'/'.uniqid('', true).'.'.File::extension($filename);

        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}

if (! function_exists('is_pdf_file')) {
    function is_pdf_file(UploadedFile|string $file): bool
    {
        $file = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isPdf((string) File::mimeType($file));
    }
}

if (! function_exists('office')) {
    function office(): LibreOffice
    {
        return app(LibreOffice::class);
    }
}

if (! function_exists('embed')) {
    function embed(string $text, bool $returnAsText = false): string|array
    {
        return app(EmbeddingsProviderInterface::class)->embed($text, $returnAsText);
    }
}

if (! function_exists('embed_query')) {
    function embed_query(string $text, bool $returnAsText = false): string|array
    {
        return app(EmbeddingsProviderInterface::class)->embedQuery($text, $returnAsText);
    }
}

if (! function_exists('template')) {
    function template(string $template, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }
}

if (! function_exists('prompt')) {
    /**
     * Render a prompt Blade view (markdown + Blade) to a string.
     *
     * Accepts slash- or dot-style names: prompt('business/profile')
     * resolves resources/prompts/business/profile.blade.php via the
     * `prompts::` view namespace. Pass variables as the second argument; use
     * {!! $var !!} in the prompt file so text isn't HTML-escaped.
     *
     * @param  array<string, mixed>  $data
     */
    function prompt(string $name, array $data = []): string
    {
        return view('prompts::'.str_replace('/', '.', $name), $data)->render();
    }
}

if (! function_exists('current_agent')) {
    /**
     * Memoised per-request. Chat/widget controllers call this 2–3× per page;
     * each call previously re-issued the sub_domain/external_id lookup.
     */
    function current_agent(): ?AgentModel
    {
        static $cache = [];

        $slug = request()->agentSlug();

        if ($slug === null) {
            return null;
        }

        return $cache[$slug] ??= Agent::resolveAgentFromSubdomain($slug);
    }
}

if (! function_exists('current_team')) {
    function current_team(): ?Team
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // Read-only — never persists during render. Session bootstrap +
        // RegisterUserAction call bootstrapCurrentTeam() to seed the column.
        return $user->currentOrPrimaryTeam();
    }
}

if (! function_exists('has_permission_to_agent')) {
    /**
     * Check if the current user has a specific permission for an agent.
     */
    function has_permission_to_agent(AgentModel $agent, string $permission): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        $team = current_team();

        if (! $team) {
            return false;
        }

        // Team owners have all permissions
        if ($user->ownsTeam($team)) {
            return true;
        }

        // Check agent access first, then permission
        $teamService = app(TeamService::class);

        if (! $teamService->canAccessAgent($user, $team, $agent)) {
            return false;
        }

        return $user->hasTeamPermission($team, $permission);
    }
}

if (! function_exists('has_team_permission')) {
    /**
     * Check if the current user has a team-level (non-agent-scoped) permission
     * in their current team. Owners and platform admins always pass.
     */
    function has_team_permission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $team = current_team();

        if (! $team) {
            return false;
        }

        if ($user->ownsTeam($team)) {
            return true;
        }

        return $user->hasTeamPermission($team, $permission);
    }
}

if (! function_exists('get_file_type')) {
    function get_file_type(string $mimeType): string
    {
        return MimeType::category($mimeType)
            ?? throw new RuntimeException('Unsupported mime type');
    }
}

if (! function_exists('is_audio_file')) {
    function is_audio_file(UploadedFile|string $file): bool
    {
        $filePath = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isAudio((string) File::mimeType($filePath));
    }
}

if (! function_exists('is_video_file')) {
    function is_video_file(UploadedFile|string $file): bool
    {
        $filePath = $file instanceof UploadedFile ? $file->path() : $file;

        return MimeType::isVideo((string) File::mimeType($filePath));
    }
}

if (! function_exists('file_extension')) {
    /**
     * Lowercased extension from the original filename (UploadedFile) or path
     * (string). Extension — not MIME — is the source of truth for OCR formats
     * because exotic text types share generic MIMEs (.tex/.bib → text/plain).
     */
    function file_extension(UploadedFile|string $file): string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file;

        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }
}

if (! function_exists('ocr_supported_extensions')) {
    /**
     * Flattened union of every extension Mistral OCR can parse.
     *
     * @return array<int, string>
     */
    function ocr_supported_extensions(): array
    {
        return array_values(array_unique(array_merge(...array_values(config('mimes.ocr')))));
    }
}

if (! function_exists('is_ocr_supported')) {
    function is_ocr_supported(UploadedFile|string $file): bool
    {
        return in_array(file_extension($file), ocr_supported_extensions(), true);
    }
}

if (! function_exists('is_image_file')) {
    function is_image_file(UploadedFile|string $file): bool
    {
        return in_array(file_extension($file), config('mimes.ocr.image'), true);
    }
}

if (! function_exists('file_category')) {
    /**
     * Extension → metering/UI category stored on SourceFile::filetype.
     * Every returned value except audio/video sits in the "pages" billing
     * bucket (see WorkspaceConsumption). Falls back to get_file_type($mime)
     * for audio/video, whose detection is reliably MIME-based.
     */
    function file_category(UploadedFile|string $file): string
    {
        return match (file_extension($file)) {
            'pdf' => 'pdf',
            'doc', 'docx', 'odt', 'rtf' => 'word',
            'ppt', 'pptx', 'odp' => 'presentation',
            'xls', 'xlsx', 'csv', 'ods' => 'spreadsheet',
            'txt' => 'text',
            'md', 'markdown' => 'markdown',
            'jpg', 'jpeg', 'png', 'avif', 'tiff', 'tif', 'gif', 'heic', 'heif', 'bmp', 'webp' => 'image',
            'epub', 'fb2', 'ipynb', 'xml', 'tex', 'bib', 'opml', '1', 'man', 'html', 'htm' => 'document',
            default => get_file_type(
                $file instanceof UploadedFile ? $file->getMimeType() : File::mimeType($file)
            ),
        };
    }
}

if (! function_exists('current_agent_from_widget')) {
    function current_agent_from_widget(): ?AgentModel
    {
        // Header is `X-AGENT-ID` (hyphenated) — matches what the widget sends
        // and what every other widget controller looks up. The previous
        // `x-agent_id` (underscore) never matched, so this helper always
        // returned null on endpoints that route through it.
        $agentId = request()->header('X-AGENT-ID');

        if ($agentId === null) {
            return null;
        }

        return AgentModel::where('external_id', $agentId)->first();
    }
}

if (! function_exists('checksum')) {
    function checksum(string $text): string
    {
        return hash('xxh128', $text);
    }
}
