<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use App\Services\Chat\Data\StreamRequestData;
use App\Services\Prompts\PromptLoader;
use App\Services\Search\KnowledgeBase;
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

        // Get the current user message content (may include retrieved context)
        $userMessageContent = $payload->message_for_llm;

        ray($userMessageContent);

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
        $systemPrompt = $data->agent->system_instructions;

        $systemPrompt .= PromptLoader::load('knowledge/preconditions');

        if ($data->canCollectLead) {
            if ($data->leadAlreadyCollected) {
                $systemPrompt .= "\n\n".PromptLoader::load('leads/lead-collected');
            } else {
                $systemPrompt .= "\n\n".PromptLoader::load('leads/lead-collect');
            }
        }

        return [
            'role' => 'system',
            'content' => $systemPrompt,
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
                $text = "{$i}: ".$chunk['content']."\n";

                if (! empty($chunk['images'])) {
                    $text .= "These are the images associated with chunk {$i}:\n";
                    $text .= "You MUST Include one or more of these images in markdown format if it does support the context and makes sense\n";

                    $imageIndex = 1;
                    foreach ($chunk['images'] as $image => $description) {
                        $text .= "Image {$imageIndex}: ".Storage::disk('public')->url($image)."\n";
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
                'confidence_score' => $result->confidence_score ?? null,
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
