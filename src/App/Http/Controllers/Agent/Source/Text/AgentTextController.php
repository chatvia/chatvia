<?php

namespace App\Http\Controllers\Agent\Source\Text;

use App\Http\Requests\Agent\Source\Text\AgentSourceTextRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Text sources: the page, the list, and creating, editing and
 * deleting a text. Agent access is checked by the route middleware; the
 * `agent.sources.train.text` and `agent.sources.delete` permissions too.
 */
class AgentTextController
{
    public function index(Request $request, Agent $agent): Response|Collection
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
        /** @var User $user */
        $user = $request->user();

        $create->execute($agent, $user, $request->textContent());
    }

    public function update(AgentSourceTextRequest $request, Agent $agent, int $id, UpdateAgentSourceTextAction $update): void
    {
        /** @var User $user */
        $user = $request->user();

        $update->execute($agent, $user, $this->agentText($agent, $id), $request->textContent());
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
     * @return Collection<int, SourceText>
     */
    private function textList(Agent $agent): Collection
    {
        return $agent
            ->texts()
            ->select('source_texts.id', 'source_texts.excerpt', 'length', 'status', 'source_texts.created_at', 'source_texts.updated_at', 'expected_chunks', 'source_texts.filepath')
            ->withCount(['agents', 'chunks'])
            ->latest('source_texts.created_at')
            ->get()
            ->map(function (SourceText $text): SourceText {
                // The content lives in storage, not the row: read it to count
                // words, and keep the storage path on the server.
                $content = Storage::exists($text->filepath) ? (string) Storage::get($text->filepath) : '';

                return $text
                    ->setAttribute('progress', $text->ingestionProgress())
                    ->setAttribute('word_count', str_word_count($content))
                    ->makeHidden('filepath');
            });
    }
}
