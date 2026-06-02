<?php

namespace App\Http\Controllers\Agent\Source\Text;

use App\Workspace\Models\User;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Source\Text\Models\SourceText;
use Domain\Team\Exceptions\InviteRejectedException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgentTextController
{
    public function index(Request $request, Agent $agent): Response|Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.text'), 403);

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

    public function store(Request $request, Agent $agent, CreateAgentSourceTextAction $create): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.text'), 403);

        $request->validate(['content' => ['required', 'min:500']]);

        /** @var User $user */
        $user = $request->user();

        try {
            $create->execute($agent, $user, $request->input('content'));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
    }

    public function update(Request $request, Agent $agent, int $id, UpdateAgentSourceTextAction $update): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        /** @var SourceText $sourceText */
        $sourceText = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->firstOrFail();

        $request->validate(['content' => ['required', 'min:500']]);

        /** @var User $user */
        $user = $request->user();

        try {
            $update->execute($agent, $user, $sourceText, $request->input('content'));
        } catch (InviteRejectedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, Agent $agent, int $id, DeleteAgentSourceTextAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

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
