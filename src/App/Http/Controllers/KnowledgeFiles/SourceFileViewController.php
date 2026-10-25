<?php

namespace App\Http\Controllers\KnowledgeFiles;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shows (or, with `?download=1`, downloads) the stored file behind a file,
 * text or website source, streamed from storage. A workspace owner sees every
 * source of the workspace; a teammate only a source used by an agent they may
 * manage and train with that kind of source.
 *
 * Stored files are uploads, so they are never served as a page of this site:
 * only PDFs and plain raster images open in the browser with their own type,
 * text is sent as plain text, and anything else downloads as raw bytes. Every
 * answer is sandboxed and forbids type sniffing.
 */
class SourceFileViewController
{
    /** The types the browser may show as themselves. */
    private const array INLINE_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    /** Types that are text but not under `text/`, shown as plain text. */
    private const array TEXT_TYPES = [
        'application/json',
        'application/csv',
    ];

    public function __construct(
        private readonly AgentAccessPolicy $agentAccess,
        private readonly TeamPermissionPolicy $permissions,
    ) {}

    public function __invoke(Request $request, string $type, string $externalId): StreamedResponse
    {
        [$model, $permission] = match ($type) {
            'file' => [SourceFile::class, 'agent.sources.train.files'],
            'text' => [SourceText::class, 'agent.sources.train.text'],
            'website' => [SourceWebsite::class, 'agent.sources.train.web'],
            default => abort(404),
        };

        if (! Str::isUuid($externalId)) {
            abort(404);
        }

        /** @var SourceFile|SourceText|SourceWebsite|null $record */
        $record = $model::query()
            ->where('external_id', $externalId)
            ->first();

        if (! $record?->filepath) {
            abort(404, __('Invalid source file.'));
        }

        /** @var User $user */
        $user = $request->user();

        // 404, not 403, so the endpoint does not confirm the existence of a
        // file the caller may not see.
        abort_unless($this->maySee($user, $record, $permission), 404);

        $filepath = $record->filepath;

        if (! Storage::exists($filepath)) {
            abort(404, __('File not found.'));
        }

        $stream = Storage::readStream($filepath);

        if ($stream === false) {
            abort(404, __('File not found.'));
        }

        $filename = basename($filepath);
        $detected = Str::lower(Str::before((string) Storage::mimeType($filepath), ';'));
        [$contentType, $inline] = $this->servedAs($detected);
        $size = Storage::size($filepath);

        $dispositionType = $inline && ! $request->boolean('download') ? 'inline' : 'attachment';

        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition($dispositionType, $filename, Str::ascii($filename)),
            'X-Content-Type-Options' => 'nosniff',
            // Browsers refuse to show a PDF in a sandboxed document; their PDF
            // viewers run no page script, so a PDF goes without `sandbox`.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'".($contentType === 'application/pdf' ? '' : '; sandbox'),
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ];

        if (is_int($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * The workspace owner sees every source of the workspace. A teammate needs
     * an agent using the source that they may manage and hold the kind's
     * training permission on.
     */
    private function maySee(User $user, SourceFile|SourceText|SourceWebsite $record, string $permission): bool
    {
        if ($user->cannot('view', $record)) {
            return false;
        }

        if ($user->isWorkspaceOwner()) {
            return true;
        }

        return $record->agents()->get()->contains(
            fn (Agent $agent): bool => $this->agentAccess->canManage($user, $agent)
                && $this->permissions->allowsOnAgent($user, $agent, $permission),
        );
    }

    /**
     * The Content-Type to send and whether the file may open in the browser.
     *
     * @return array{0: string, 1: bool}
     */
    private function servedAs(string $detected): array
    {
        return match (true) {
            in_array($detected, self::INLINE_TYPES, true) => [$detected, true],
            Str::startsWith($detected, 'text/') || in_array($detected, self::TEXT_TYPES, true) => ['text/plain; charset=utf-8', true],
            default => ['application/octet-stream', false],
        };
    }
}
