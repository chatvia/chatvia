<?php

namespace App\Http\Controllers\Agent\Source\Text;

use App\Http\Requests\Agent\Source\Text\AgentSourceTextRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Text sources: the page, the list (newest first, one page at a
 * time), and creating, editing and deleting a text. Agent access is checked by
 * the route middleware; the `agent.sources.train.text` and
 * `agent.sources.delete` permissions too.
 */
class AgentTextController
{
    private const int PER_PAGE = 50;

    /**
     * @return Response|LengthAwarePaginator<int, SourceText>
     */
    public function index(Request $request, Agent $agent): Response|LengthAwarePaginator
    {
        if ($request->wantsJson()) {
            return $this->textList($agent);
        }

        return Inertia::render('agents/Sources/Text', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Agent $agent, int $id): SourceText
    {
        $text = $this->agentText($agent, $id);

        // The text lives in storage; its path stays on the server.
        return $text
            ->setAttribute('content', Storage::get($text->filepath))
            ->makeHidden('filepath');
    }

    public function store(AgentSourceTextRequest $request, Agent $agent, CreateAgentSourceTextAction $create): void
    {
        $create->execute($agent, $request->textContent());
    }

    public function update(AgentSourceTextRequest $request, Agent $agent, int $id, UpdateAgentSourceTextAction $update): void
    {
        $update->execute($agent, $this->agentText($agent, $id), $request->textContent());
    }

    public function destroy(Request $request, Agent $agent, int $id, DeleteAgentSourceTextAction $delete): void
    {
        $text = $this->agentText($agent, $id);

        abort_if($request->user()->cannot('delete', $text), 403);

        $delete->execute($agent, $text);
    }

    private function agentText(Agent $agent, int $id): SourceText
    {
        /** @var SourceText */
        return $agent->texts()->where('source_texts.id', $id)->firstOrFail();
    }

    /**
     * The row's columns only: the text itself stays in storage.
     *
     * @return LengthAwarePaginator<int, SourceText>
     */
    private function textList(Agent $agent): LengthAwarePaginator
    {
        // The relation's own query: paginating the relation would add `*`
        // and the pivot columns back to the select.
        $texts = $agent
            ->texts()
            ->getQuery()
            ->select('source_texts.id', 'source_texts.excerpt', 'length', 'status', 'source_texts.created_at', 'source_texts.updated_at', 'expected_chunks')
            ->withCount('agents')
            ->orderByDesc('source_texts.id')
            ->paginate(self::PER_PAGE);

        // Only a text that is training shows progress, which needs its chunk count.
        $texts->getCollection()
            ->filter(fn (SourceText $text): bool => $text->status === SourceStatus::Processing)
            ->loadCount('chunks');

        return $texts->through(fn (SourceText $text): SourceText => $text->setAttribute('progress', $text->ingestionProgress()));
    }
}
