<?php

declare(strict_types=1);

namespace Tests\Support\Mcp;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;

/**
 * An agent with one active "Book a demo" form and a visitor's conversation
 * on the given surface, as the find-forms and render-form tools see them.
 */
final class FormAgent
{
    /** @return array{agent: Agent, conversation: Conversation, form: FormDefinition} */
    public static function seed(ConversationSource $source = ConversationSource::Widget): array
    {
        $agent = Agent::factory()->create();
        $form = FormDefinition::factory()->forAgent($agent)->create([
            'name' => 'Book a demo',
            'slug' => 'book-a-demo',
            'subtitle' => 'Pick a time that suits you.',
            'agent_instructions' => 'Show this when the visitor wants to book a demo.',
            'is_active' => true,
        ]);
        $session = Session::factory()->create(['agent_id' => $agent->id]);
        $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => $source]);

        return ['agent' => $agent, 'conversation' => $conversation, 'form' => $form];
    }
}
