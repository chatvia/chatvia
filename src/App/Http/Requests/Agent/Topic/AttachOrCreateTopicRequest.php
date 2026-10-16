<?php

namespace App\Http\Requests\Agent\Topic;

use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Data\SaveTopicData;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Two-shape input for AgentTopicController::store: when the caller passes
 * `topic_id` we attach an existing topic; otherwise we create a fresh one
 * from `topic` + `description`. Validation runs the right branch via
 * `sometimes` rules.
 */
class AttachOrCreateTopicRequest extends FormRequest
{
    private ?Topic $topic = null;

    public function authorize(): bool
    {
        $agent = $this->route('agent');

        return $agent !== null && $this->user()?->can('update', $agent);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        if ($this->has('topic_id')) {
            return [
                'topic_id' => ['required', 'integer', WorkspaceRule::existsOrBuiltIn('topics', $this->user()->workspace())],
            ];
        }

        return [
            'topic' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }

    public function isAttach(): bool
    {
        return $this->has('topic_id');
    }

    /**
     * The existing topic to attach — scoped through forWorkspace (global or
     * owned by this workspace) so a body-supplied id can't reach another
     * account's private topic. Mirrors the scope of the attachable-topics
     * listing in AgentTopicController::index().
     */
    public function topic(Agent $agent): Topic
    {
        return $this->topic ??= Topic::query()
            ->forWorkspace($agent->workspace)
            ->findOrFail($this->integer('topic_id'));
    }

    public function toData(): SaveTopicData
    {
        return new SaveTopicData(
            topic: $this->string('topic')->toString(),
            description: $this->string('description')->toString(),
        );
    }
}
