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

class AgentTextController
{
    public function index(Request $request, Agent $agent): Response|Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        if ($request->wantsJson()) {
            return $this->buildTextList($agent);
        }

        return Inertia::render('agents/Sources/Text', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Request $request, Agent $agent, int $id): SourceText
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var SourceText $text */
        $text = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->firstOrFail();

        $text->content = Storage::get($text->filepath);

        return $text;
    }

    public function store(AgentSourceTextRequest $request, Agent $agent, CreateAgentSourceTextAction $create): void
    {
        /** @var User $user */
        $user = $request->user();

        $create->execute($agent, $user, (string) $request->validated('content'));
    }

    public function update(AgentSourceTextRequest $request, Agent $agent, int $id, UpdateAgentSourceTextAction $update): void
    {
        /** @var SourceText $sourceText */
        $sourceText = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->firstOrFail();

        /** @var User $user */
        $user = $request->user();

        $update->execute($agent, $user, $sourceText, (string) $request->validated('content'));
    }

    public function destroy(Request $request, Agent $agent, int $id, DeleteAgentSourceTextAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $agent), 404);

        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if($request->user()->cannot('delete', $text), 403);

        $delete->execute($agent, $text);
    }

    private function buildTextList(Agent $agent): Collection
    {
        $texts = $agent
            ->texts()
            ->select('source_texts.id', 'source_texts.excerpt', 'length', 'status', 'source_texts.created_at', 'source_texts.updated_at', 'expected_chunks')
            ->withCount(['agents', 'chunks'])
            ->latest('source_texts.created_at')
            ->get();

        return $texts->map(function ($text) {
            $text['progress'] = $text->ingestionProgress();
            $text['word_count'] = str_word_count($text->content);

            return $text;
        });
    }
}
