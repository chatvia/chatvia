<?php

namespace Domain\Chat\Services;

use Domain\Agents\Models\Agent;
use Domain\Chat\Data\Messages\ChatInputPayload;
use Domain\Chat\Services\Data\StreamRequestData;
use Domain\Ingestion\Services\Search\KnowledgeBase;
use Domain\Sources\QA\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class MessageProcessor
{
    public function __construct(
        private KnowledgeBase $knowledgeBase
    ) {}

    public function prepareLlmMessages(StreamRequestData $data, ChatInputPayload $payload, array $messages): array
    {
        $systemPrompt = $this->buildSystemPrompt($data);

        // Get the current user message content (may include retrieved context)
        $userMessageContent = $payload->message_for_llm;

        // Check if the last message is already a user message
        $lastMessage = end($messages);
        $hasUserMessage = $lastMessage && $lastMessage['role'] === 'user';

        if ($hasUserMessage) {
            // Replace the last user message with the enriched one
            array_pop($messages);
        }

        // Add the current user message (either new or updated with context)
        $userMessage = [
            'role' => 'user',
            'content' => $userMessageContent,
        ];

        return array_merge([$systemPrompt], $messages, [$userMessage]);
    }

    /**
     * Search for a Q&A match using strict phrase matching.
     * Exact phrase match - word order matters.
     */
    public function searchQA(int $agentId, string $query): ?object
    {
        $results = AgentQuestionAnswer::query()
            ->phraseSearch($agentId, $query);

        if (empty($results)) {
            return null;
        }

        $qa = $results[0];
        $qa->confidence_score = 0.99;

        return $qa;
    }

    public function searchKnowledgeBase(Agent $agent, ChatInputPayload $payload, array $lastMessages): Collection
    {
        //        $shouldSearch = false;

        //        if (count($lastMessages) > 1) {
        $lastMessageContents = collect($lastMessages)->pluck('content')->toArray();
        $shouldSearch = $this->knowledgeBase->shouldContinueSearching(
            $lastMessageContents,
            $payload->message_for_llm
        );
        //        }

        if (! $shouldSearch) {
            return collect();
        }

        $searchResults = $this->knowledgeBase->search($agent, $payload->message_for_llm);

        return $this->enrichSourcesWithMetadata($searchResults);
    }

    private function buildSystemPrompt(StreamRequestData $data): array
    {
        $systemPrompt = $data->agent->resolveSystemInstructions();

        $systemPrompt .= prompt('knowledge/preconditions');

        $business = array_filter($data->agent->settings('business')->toArray(), static fn ($v): bool => filled($v));
        if ($business !== []) {
            $systemPrompt .= "\n\n".prompt('business/profile', [
                'email' => $business['email'] ?? null,
                'phone' => $business['phone'] ?? null,
                'website' => $business['website'] ?? null,
                'address' => $business['address'] ?? null,
                'opening_hours' => $business['opening_hours'] ?? null,
            ]);
        }

        return [
            'role' => 'system',
            'content' => $systemPrompt,
        ];
    }

    public function buildContextualUserMessage(ChatInputPayload $payload, Collection $sources): string
    {
        if ($sources->isEmpty()) {
            return $payload->message_for_llm;
        }

        $message = Str::of("### User Question:\n")
            ->append($payload->message_for_llm)
            ->append("\n\n### Retrieved Context\n");

        // Check if any sources have images
        $hasImages = $sources->contains(fn ($chunk): bool => ! empty($chunk['images']));

        if ($hasImages) {
            $message = $message->append("\n⚠️ CRITICAL INSTRUCTION: This context includes images. You MUST include relevant images in your response using markdown format: ![description](url)\n\n");
        }

        $message = $message->append($sources->map(function ($chunk, $i) {
            $text = "Context {$i}:\n".$chunk['content']."\n";

            if (! empty($chunk['images'])) {
                $text .= "\n📷 IMAGES FOR THIS CONTEXT (REQUIRED - Include in response):\n";

                $imageIndex = 0;
                foreach ($chunk['images'] as $description) {
                    // Always use main domain URL instead of subdomain-based route()
                    $path = '/my/chunks/'.$chunk['external_id'].'/'.$imageIndex.'.png';
                    $imageUrl = rtrim(config('app.url'), '/').$path;
                    $text .= "- Image {$imageIndex}: {$description}\n";
                    $text .= "  Markdown: ![{$description}]({$imageUrl})\n";
                    $imageIndex++;
                }
                $text .= "\n";
            }

            return $text;
        })->implode("\n"));

        return $message->toString();
    }

    private function enrichSourcesWithMetadata(array $searchResults): Collection
    {
        if (empty($searchResults)) {
            return collect();
        }

        // Get unique chunkable IDs and their types
        $chunkableItems = collect($searchResults)
            ->groupBy('chunkable_type')
            ->map(function ($results, string $type) {
                $ids = $results->pluck('chunkable_id')->unique();

                /** @var class-string<Model> $type */
                return $type::whereIn('id', $ids)->get()->keyBy('id');
            });

        return collect($searchResults)->map(function ($result) use ($chunkableItems) {
            $chunkable = $chunkableItems[$result->chunkable_type]?->get($result->chunkable_id);
            $metadata = json_decode($result->metadata, true);

            return [
                'id' => $result->id,
                'external_id' => $result->external_id,
                'content' => $result->chunk,
                'confidence_score' => $result->confidence_score ?? null,
                'metadata' => [
                    'filename' => $chunkable->filename ?? ($chunkable->url ?? 'Unknown'),
                    'document_id' => $result->id,
                    'chunkable_type' => $result->chunkable_type,
                    'chunkable_id' => $result->chunkable_id,
                ],
                'images' => $metadata['images'] ?? [],
            ];
        });
    }
}
