<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Workspace-scoped knowledge consumption queries: pages, audio/video minutes,
 * and crawled links currently used across every agent the workspace owns.
 *
 * Extracted from User to keep the auth model from being a query layer too. The
 * scoping rule that ties this to the workspace (not the individual logged-in
 * employee) is preserved verbatim — every join goes through
 * {@see User::workspace()}, so a workspace employee sees the workspace
 * owner's consumption, exactly matching the in-place implementation that
 * shipped on User.
 */
readonly class WorkspaceConsumption
{
    public function __construct(private User $user) {}

    /**
     * Total "pages" used across uploaded documents (every OCR-parsed file —
     * PDF, Word, presentation, spreadsheet, image, and the exotic document
     * formats) AND raw text sources. Each source is counted once even when
     * attached to multiple agents (the inner query selects distinct ids).
     */
    public function pages(): int
    {
        $sourceFilesPages = SourceFile::query()
            ->whereIn('id', $this->ownedSourceIds(SourceFile::class))
            ->whereIn('filetype', ['pdf', 'word', 'markdown', 'text', 'presentation', 'spreadsheet', 'image', 'document'])
            ->sum('length');

        $sourceTextsPages = SourceText::query()
            ->whereIn('id', $this->ownedSourceIds(SourceText::class))
            ->sum('length');

        return (int) ($sourceFilesPages + $sourceTextsPages);
    }

    /** Total minutes of transcribed audio/video sources. */
    public function minutes(): int
    {
        return (int) SourceFile::query()
            ->whereIn('id', $this->ownedSourceIds(SourceFile::class))
            ->whereIn('filetype', ['audio', 'video'])
            ->sum('length');
    }

    /** Total number of distinct crawled URLs across owned website sources. */
    public function links(): int
    {
        return $this->user
            ->agents()
            ->join('agent_sources', function ($join): void {
                $join->on('agents.id', '=', 'agent_sources.agent_id')
                    ->where('agent_sources.sourceable_type', '=', Relation::getMorphAlias(SourceWebsite::class));
            })
            ->join('source_websites', 'source_websites.id', '=', 'agent_sources.sourceable_id')
            ->join('source_website_urls', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->whereNotNull('source_website_urls.crawled_at')
            ->distinct('source_website_urls.id')
            ->count('source_website_urls.id');
    }

    /**
     * Whether the user can upload `$amount` more pages without exceeding their
     * quota. Both `quota` and `pages()` route through {@see User::workspace()},
     * so a workspace employee gates against the owner's quota AND the owner's
     * measured pages — matching the original on-User semantics verbatim.
     *
     * The limit comes from {@see User::quotaLimit()}, which reads the lazy
     * `quota` relation (see User::quota() docblock) and has no limit when
     * billing is turned off.
     */
    public function canUploadPages(int $amount): bool
    {
        return ($this->user->quotaLimit('pages_limit') - $this->pages()) >= $amount;
    }

    /**
     * `whereIn('id', ...)` closure selecting the ids of $sourceableType rows
     * attached (via agent_sources) to any agent the workspace owns. Shared by
     * pages()/minutes() so the multi-join scoping lives in one place instead
     * of being hand-copied per source type.
     */
    private function ownedSourceIds(string $sourceableType): Closure
    {
        $workspaceId = $this->user->workspace()->id;
        // agent_sources stores the morph alias, never the FQCN.
        $alias = Relation::getMorphAlias($sourceableType);

        return function ($query) use ($workspaceId, $alias): void {
            $query->select('agent_sources.sourceable_id')
                ->from('agent_sources')
                ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
                ->where('agents.user_id', $workspaceId)
                ->where('agent_sources.sourceable_type', '=', $alias)
                ->distinct();
        };
    }
}
