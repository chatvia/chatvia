<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Topic;

use App\Http\Requests\Concerns\ScopesRulesToWorkspace;
use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Data\SaveTopicData;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/agents/{agent}/topics` — give the agent a topic: either
 * attach an existing one by `topic_id` (a system topic or one of the
 * workspace's own), or create a new workspace topic from `topic` +
 * `description`.
 */
class StoreApiTopicRequest extends FormRequest
{
    use ScopesRulesToWorkspace;

    private ?Topic $topic = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The ID of an existing topic to attach. Leave it out to create one.
            'topic_id' => [
                'sometimes', 'required', 'uuid', 'prohibits:topic,description',
                WorkspaceRule::existsOrBuiltIn('topics', $this->ruleWorkspaceId(), 'external_id'),
            ],
            // The new topic's name.
            'topic' => ['required_without:topic_id', 'string', 'max:120'],
            // What conversations the topic covers — written for the analysis model.
            'description' => ['required_without:topic_id', 'string', 'max:255'],
        ];
    }

    /** Whether the request attaches an existing topic (vs creating one). */
    public function isAttach(): bool
    {
        return filled($this->validated('topic_id'));
    }

    /**
     * The existing topic to attach — scoped through forWorkspace (global or
     * owned by this workspace) so a body-supplied id can't reach another
     * account's private topic.
     */
    public function topic(Agent $agent): Topic
    {
        return $this->topic ??= Topic::query()
            ->forWorkspace($agent->workspace)
            ->where('external_id', $this->string('topic_id')->toString())
            ->firstOrFail();
    }

    public function toData(): SaveTopicData
    {
        return new SaveTopicData(
            topic: $this->string('topic')->toString(),
            description: $this->string('description')->toString(),
        );
    }
}
