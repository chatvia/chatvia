<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\PromptData;
use Support\Prompt\PromptTemplate;

/**
 * The prompts an admin can edit in Settings → Prompts: every prompt the app
 * renders with prompt(), by group, with a label and what it is for. The
 * default text of each is its file in resources/prompts (read when needed,
 * so a changed file is the new default). A test keeps this list in step with
 * the prompt() calls in the code.
 */
final class PromptCatalog
{
    /** @var array<string, array{0: string, 1: string, 2: string}> name => [group, label, what it is for] */
    private const array PROMPTS = [
        'knowledge/system' => ['Chat', 'Agent role', 'The instructions of an agent that has none of its own.'],
        'knowledge/grounding' => ['Chat', 'How to answer', 'Added to every agent: how to answer from the knowledge sources.'],
        'knowledge/reference-material' => ['Chat', 'Reference material', 'Puts the question and the parts found in the sources (with their images) in front of the model.'],
        'chatter/identity' => ['Chat', 'Signed-in visitor', 'Tells a protected agent the name of the signed-in chatter.'],
        'business/profile' => ['Chat', 'Business details', 'The contact details of the business, when an agent has them.'],
        'tools/approval' => ['Chat', 'Tool actions', 'How an agent talks about the actions its tools take, and about actions waiting for approval.'],
        'knowledge/rewrite-query' => ['Search', 'Follow-up questions', 'Turns a follow-up ("and the price?") into a full question before the search. Must answer as "language|question".'],
        'knowledge/rewrite-query-user' => ['Search', 'Follow-up questions: message', 'The message with the new and the previous question.'],
        'chunking/document-chunking-system' => ['Training', 'Chunk enhancement', 'Adds a summary and likely questions to each part of a source (AI enhancement).'],
        'chunking/document-chunking-user' => ['Training', 'Chunk enhancement: page', 'The page, with the pages before and after it.'],
        'summarization/system' => ['Training', 'Source summary', 'Summarizes a whole document when it is trained.'],
        'image/describe' => ['Training', 'Image descriptions', 'Describes the images inside documents.'],
        'tables/describe' => ['Training', 'Table descriptions', 'Turns tables into searchable sentences.'],
        'conversation/title' => ['Conversations', 'Conversation titles', 'Writes the title of a conversation.'],
        'analytics/conversation-analysis' => ['Conversations', 'Conversation analysis', 'Finds the language, sentiment, tone and topics of a conversation.'],
        'analytics/message-analysis' => ['Conversations', 'Message sentiment', 'Finds the sentiment and tone of one message.'],
        'analytics/chat-system' => ['Conversations', 'Analytics assistant', 'The assistant that answers questions about a workspace\'s own data.'],
        'guardrails/classifier-system' => ['Safety and quality', 'Guardrail check', 'Checks a message for prompt injection and off-topic requests.'],
        'guardrails/classifier-user' => ['Safety and quality', 'Guardrail check: message', 'The message to check, with the allowed scope and blocked topics.'],
        'evals/judge-system' => ['Safety and quality', 'Eval judge', 'Grades an agent\'s answer in an evaluation.'],
        'evals/judge-user' => ['Safety and quality', 'Eval judge: case', 'The question, the criteria, the reference answer and the answer to grade.'],
        'agent-slug/generate' => ['Setup helpers', 'Agent web address', 'Suggests the name in a new agent\'s web address.'],
        'starters/generate' => ['Setup helpers', 'Conversation starters', 'Suggests the questions a chat shows before the first message.'],
        'starters/content' => ['Setup helpers', 'Conversation starters: content', 'The knowledge content the starters are written from.'],
        'forms/rewrite-description' => ['Setup helpers', 'Form instruction', 'Improves the instruction that tells an agent when to show a form.'],
        'translate/strings' => ['Setup helpers', 'Translation', 'Translates an agent\'s title, welcome message and placeholder.'],
    ];

    /**
     * Every prompt, in the order of the list.
     *
     * @return list<PromptData>
     */
    public function all(): array
    {
        return array_map($this->find(...), array_keys(self::PROMPTS));
    }

    public function find(string $name): PromptData
    {
        [$group, $label, $description] = self::PROMPTS[$name];

        return new PromptData($name, $group, $label, $description, $this->defaultText($name));
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, self::PROMPTS);
    }

    /** The prompt's file in resources/prompts: its default text. */
    public function defaultText(string $name): string
    {
        return (string) file_get_contents($this->path($name));
    }

    public function path(string $name): string
    {
        return resource_path("prompts/{$name}.blade.php");
    }

    /**
     * The variables a prompt is given, from its file: an edit may use these
     * (and those its own loops make).
     *
     * @return list<string>
     */
    public function variables(string $name): array
    {
        return PromptTemplate::inputs($this->defaultText($name));
    }
}
