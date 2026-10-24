<?php

namespace Database\Factories;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an untitled Chat Widget conversation inside a fresh visitor session.
 * The denormalized `agent_id` is copied from the session after making, so
 * callers that pass only a session still get a valid row. The surface and the
 * title are fixed, so a test that leaves them out always runs the same case.
 * {@see forAgent()} puts it in a new visitor session of a given agent.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    #[\Override]
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A session of the given agent when one is named, so the two agree.
            'chat_session_id' => fn (array $attributes): int => Session::factory()
                ->create(isset($attributes['agent_id']) ? ['agent_id' => $attributes['agent_id']] : [])
                ->id,
            'source' => ConversationSource::Widget,
            'title' => null,
        ];
    }

    /** Default agent_id and workspace_id from the session (denormalized, NOT NULL). */
    public function configure(): static
    {
        return $this->afterMaking(function (Conversation $conversation): void {
            $session = Session::query()->select(['agent_id', 'workspace_id'])->findOrFail($conversation->chat_session_id);

            $conversation->agent_id ??= $session->agent_id;
            $conversation->workspace_id ??= $session->workspace_id;
        });
    }

    /** In a new visitor session of the agent. */
    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    /** In the given session, of its agent. */
    public function inSession(Session $session): static
    {
        return $this->state(['chat_session_id' => $session->id, 'agent_id' => $session->agent_id]);
    }

    /** In a new session of the signed-in chatter (pair it with {@see forAgent()}). */
    public function ofChatter(Chatter $chatter): static
    {
        return $this->state([
            'chat_session_id' => fn (array $attributes): int => Session::factory()
                ->forChatter($chatter)
                ->create(isset($attributes['agent_id']) ? ['agent_id' => $attributes['agent_id']] : [])
                ->id,
        ]);
    }

    /** Started on the Hosted Chat surface. */
    public function hostedChat(): static
    {
        return $this->state(['source' => ConversationSource::HostedChat]);
    }

    /** Started on the Chat Widget surface (the default, named for clarity). */
    public function widget(): static
    {
        return $this->state(['source' => ConversationSource::Widget]);
    }

    /** Shown in the visitor's list and the chat log. */
    public function visible(): static
    {
        return $this->state(['is_visible' => true]);
    }

    /** Visible and active just now, so the Inbox lists it as online. */
    public function live(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_visible' => true,
            'last_activity_at' => now(),
        ]);
    }

    /** Waiting for a teammate (pending), or held by one (human). */
    public function handedOff(HandoffStatus $status = HandoffStatus::Human, ?User $operator = null): static
    {
        return $this->state([
            'handoff_status' => $status,
            'handoff_user_id' => $operator?->id,
        ]);
    }

    /**
     * Indicate that the conversation has a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => $title,
        ]);
    }

    /**
     * Indicate that the conversation has no title.
     */
    public function withoutTitle(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => null,
        ]);
    }

    /**
     * Stamp a specific expiration on the conversation. Useful for the prune
     * command's tests to assert what gets swept and what doesn't.
     */
    public function expiresAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => $expiresAt,
        ]);
    }
}
