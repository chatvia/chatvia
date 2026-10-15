<?php

declare(strict_types=1);

namespace Domain\Agent\Queries;

use Domain\Agent\Data\Sources\AgentSourcePageData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Support\Pagination\CursorPage;

/**
 * One keyset page of an agent's files, texts and websites together, newest
 * first — the public API's source list. The three kinds live in three tables,
 * so the page runs over a union of the agent's links with each source's
 * `created_at`; the sources themselves are then loaded per kind. Ties on
 * `created_at` are broken by id and kind, so every link has one place in the
 * order and the cursor never skips or repeats one.
 */
class AgentSourcePageQuery
{
    /**
     * The cursor carries the sort columns of the last link of the page.
     *
     * @var array<string, 'int'|'string'|'timestamp'>
     */
    private const array CURSOR_COLUMNS = [
        'created_at' => 'timestamp',
        'sourceable_id' => 'int',
        'sourceable_type' => 'string',
    ];

    public function get(Agent $agent, int $perPage, ?string $cursor): AgentSourcePageData
    {
        $links = $this->links($agent->files())
            ->unionAll($this->links($agent->texts()))
            ->unionAll($this->links($agent->websites()));

        // An empty string, not null, means "first page": with null the
        // paginator would read a `cursor` from the current request.
        $page = AgentSource::query()
            ->fromSub($links, 'agent_sources')
            ->with('sourceable')
            ->orderByDesc('created_at')
            ->orderByDesc('sourceable_id')
            ->orderBy('sourceable_type')
            ->cursorPaginate($perPage, cursor: CursorPage::decode($cursor, self::CURSOR_COLUMNS) ?? '');

        // A source deleted between the two queries has no model; it is left
        // out of the page, and the cursor still moves past it.
        $sources = [];

        foreach ($page->items() as $link) {
            $source = $link->sourceable;

            if ($source instanceof SourceFile || $source instanceof SourceText || $source instanceof SourceWebsite) {
                $sources[] = $source;
            }
        }

        // Read from the links: the cursor holds their (created_at, id, kind).
        return new AgentSourcePageData(
            sources: $sources,
            next_cursor: $page->nextCursor()?->encode(),
        );
    }

    /**
     * The agent's links to one kind of source, each with the source's
     * `created_at`. The relation already joins the pivot and keeps only this
     * agent's rows of this kind.
     *
     * @param  MorphToMany<covariant Model, Agent, AgentSource>  $sources
     */
    private function links(MorphToMany $sources): Builder
    {
        return $sources->getQuery()
            ->select([
                $sources->qualifyPivotColumn('sourceable_type'),
                $sources->qualifyPivotColumn('sourceable_id'),
                $sources->getRelated()->qualifyColumn('created_at'),
            ])
            ->toBase();
    }
}
