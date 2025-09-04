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
        $preconditions = '
If the response contains mathematical expression, then you must use the following format:
Use this format for mathematical expressions:
Inline math: $E = mc^2$
Display/block math: $$\int_{-\infty}^{\infty} e^{-x^2} dx = \sqrt{\pi}$$
Do NOT use: \[...\] or [...] for math. Example: \[x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}\]
Always use this format: $$x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$$

For right-to-left languages, always place equations on a new line and format them using LaTeX syntax.

Important: Your response will be used in a Retrieval-Augmented Generation (RAG) system. Do not reference or reveal internal processes, technical terms, or implementation details (e.g., data segmentation, embeddings, or indexing). Focus only on providing a clear, natural, and helpful answer to the user\'s query.
';

        if (! $data->leadAlreadyCollected) {
            $id = '<ShowLeadForm/>';
            $preconditions .= "\n\nLEAD COLLECTION INSTRUCTIONS:
- If the user asks about pricing, costs, or plans, include $id at the end of your response
- If the user asks to contact sales, get a demo, or speak to someone, include $id at the end of your response
- If the user asks how to get started, sign up, or purchase, include $id at the end of your response
- If the user provides contact information (name, email, phone), include $id at the end of your response
- If the user asks about implementation, setup, or onboarding help, include $id at the end of your response
- Only include $id once per conversation
- Place $id on a new line at the very end of your response
- Do not mention the $id command in your actual response text

Example response format:
'I'd be happy to help you with pricing information. Our plans start at $29/month...'
$id
";
        }

        ray($preconditions);

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
