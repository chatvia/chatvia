<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Models\Agent;
use App\Services\Chat\Data\StreamRequestData;
use App\Support\KnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

readonly class MessageProcessor
{
    public function __construct(
        private KnowledgeBase $knowledgeBase
    ) {}

    public function prepareLlmMessages(StreamRequestData $data, ChatInputPayload $payload, array $messages): array
    {
        $systemPrompt = $this->buildSystemPrompt($data);
        $userMessageContent = $this->buildUserMessage($data->agent, $payload);

        $userMessage = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    public function searchKnowledgeBase(Agent $agent, ChatInputPayload $payload, array $lastMessages): Collection
    {
        $shouldSearch = true;

        if (count($lastMessages) > 1) {
            $lastMessageContents = collect($lastMessages)->pluck('content')->toArray();
            $shouldSearch = $this->knowledgeBase->shouldContinueSearching(
                $lastMessageContents,
                $payload->message_for_llm
            );
        }

        if (! $shouldSearch) {
            return collect();
        }

        $searchResults = $this->knowledgeBase->search($agent, $payload->message_for_llm);

        return $this->enrichSourcesWithMetadata($searchResults);
    }

    private function buildSystemPrompt(StreamRequestData $data): array
    {
        //        $preconditions = '
        // If the response contains mathematical expression, then you must use the following format:
        // Use this format for mathematical expressions:
        // Inline math: $E = mc^2$
        // Display/block math: $$\int_{-\infty}^{\infty} e^{-x^2} dx = \sqrt{\pi}$$
        // Do NOT use: \[...\] or [...] for math. Example: \[x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}\]
        // Always use this format: $$x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$$
        //
        // For right-to-left languages, always place equations on a new line and format them using LaTeX syntax.
        //
        // Important: Your response will be used in a Retrieval-Augmented Generation (RAG) system. Do not reference or reveal internal processes, technical terms, or implementation details (e.g., data segmentation, embeddings, or indexing). Focus only on providing a clear, natural, and helpful answer to the user\'s query.
        // ';
        $preconditions = '';

        if ($data->canCollectLead) {
            $id = '<ShowLeadForm/>';

            if ($data->leadAlreadyCollected) {
                $preconditions .= "\n\n
You should never say that you do not know the answer unless you follow these instructions

LEAD ALREADY COLLECTED: The user has already provided their contact information in this conversation.

Focus on providing helpful information and assistance.

If the user asks about getting in touch or providing contact information again, remind them that their information has been received and someone will be in touch soon.

";
            } else {
                $preconditions .= "\n\n
You should never say that you do not know the answer unless you follow these instructions

You must also collect leads. Here is the LEAD COLLECTION INSTRUCTIONS:
- Only include $id when the user shows clear buying intent or requests direct contact
- Include $id if user asks for: pricing details, quotes, demos, trials, or to speak with sales
- Include $id if user asks: 'How do I buy?', 'Can I get a demo?', 'What does it cost?'
- Include $id if user provides contact details and expresses interest in purchasing
- Include $id if user asks about custom plans, enterprise solutions, or implementation services
- DO NOT include $id for general questions, feature inquiries, or basic information requests
- DO NOT include $id if user is just browsing or asking 'What is this product?'
- Only include $id once per conversation when genuine sales interest is shown

PERMISSION RULES:
- If user explicitly asks to contact someone, speak to sales, get a demo, or connect directly: Include $id immediately without asking
- If user asks about pricing or costs but doesn't explicitly request contact: Ask for permission first
- If user provides contact info voluntarily: Include $id immediately
- If user says 'I want to buy' or 'How do I purchase': Include $id immediately

- Place $id on a new line at the very end of your response
- Do not mention the $id command in your actual response text

Example - Direct request (no permission needed):
User: 'Can I speak to someone about pricing?'
Response: 'I'll connect you with our sales team right away.'
$id

Example - Pricing inquiry (ask permission):
User: 'How much does your premium plan cost?'
Response: 'Our premium plan is $99/month and includes advanced features... Would you like me to connect you with our sales team for a personalized quote?'
(Wait for user confirmation before adding $id)

Example - Explicit purchase intent (no permission needed):
User: 'I want to buy this, how do I get started?'
Response: 'Great! I'll connect you with our team to get you started.'
$id

Example of when NOT to include $id:
User: 'What features do you offer?'
Response: 'We offer many great features including...'
(No $id needed - just informational)
";
            }
        }

        return [
            'role' => 'system',
            'content' => $data->agent->system_instructions."\n".$preconditions,
        ];
    }

    private function buildUserMessage(Agent $agent, ChatInputPayload $payload): string
    {
        $userMessageContent = $payload->message_for_llm;

        if (! $agent->isStandalone()) {
            return $userMessageContent;
        }

        // This will be set by the ChatService after knowledge base search
        return $userMessageContent;
    }

    public function buildContextualUserMessage(ChatInputPayload $payload, Collection $sources): string
    {
        if ($sources->isEmpty()) {
            return $payload->message_for_llm;
        }

        return Str::of("### User Question:\n")
            ->append($payload->message_for_llm)
            ->append("\n### Retrieved Context\n")
            ->append($sources->map(function ($chunk, $i) {
                $text = "Chunk {$i}: ".$chunk['content']."\n";

                if (! empty($chunk['images'])) {
                    $text .= "These are the images associated with chunk {$i}:\n";
                    $text .= "You MUST Include one or more of these images in markdown format if it does support the context and makes sense\n";

                    $imageIndex = 1;
                    foreach ($chunk['images'] as $image => $description) {
                        $text .= "Image {$imageIndex}: ".Storage::url($image)."\n";
                        $text .= "Description for image {$imageIndex}: {$description}\n";
                        $imageIndex++;
                    }
                }

                return $text;
            })
                ->implode("\n"))
            ->toString();
    }

    private function enrichSourcesWithMetadata(array $searchResults): Collection
    {
        if (empty($searchResults)) {
            return collect();
        }

        // Get unique chunkable IDs and their types
        $chunkableItems = collect($searchResults)
            ->groupBy('chunkable_type')
            ->map(function ($results, $type) {
                $ids = $results->pluck('chunkable_id')->unique();

                return $type::whereIn('id', $ids)->get()->keyBy('id');
            });

        return collect($searchResults)->map(function ($result) use ($chunkableItems) {
            $chunkable = $chunkableItems[$result->chunkable_type]?->get($result->chunkable_id);
            $metadata = json_decode($result->metadata, true);

            return [
                'content' => $result->chunk,
                'metadata' => [
                    'filename' => $chunkable?->filename ?? ($chunkable?->url ?? 'Unknown'),
                    'document_id' => $result->id,
                    'chunkable_type' => $result->chunkable_type,
                    'chunkable_id' => $result->chunkable_id,
                ],
                'images' => $metadata['images'] ?? [],
            ];
        });
    }
}
