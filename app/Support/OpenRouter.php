<?php

namespace App\Support;

use App\Data\Chunk;
use GuzzleHttp\Client;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OpenRouter
{
    public function stream($model, $llmMessages, array $settings = [])
    {
        $client = app(Client::class);

        //        $options = [
        //            'reasoning' => [
        //                'effort' => 'high',
        //                'exclude' => false,
        //            ],
        //        ];

        return $client->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $llmMessages,
                'stream' => true,
                //                ...$options,
            ],
            'stream' => true,
            'provider' => [
                'sort' => 'latency', // https://openrouter.ai/docs/features/provider-routing
            ],
            ...$settings,
        ]);
    }

    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openrouter.completion_endpoint'), $data)
            ->throw();
    }

    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param  array<Chunk>  $chunks
     */
    public function bulkEnhance(Collection $chunks): Collection
    {
        $responses = Http::pool(function (Pool $pool) use ($chunks) {
            foreach ($chunks as $chunk) {
                $pool
                    ->as($chunk->chunk_id)
                    ->withHeaders($this->headers())
                    ->post(config('services.openrouter.completion_endpoint'), [
                        'model' => 'openai/gpt-4.1-mini',
                        'max_tokens' => 1000,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => config('prompts.enhance'),
                            ],
                            [
                                'role' => 'user',
                                'content' => 'Here is the original chunk: '.$chunk->original_content,
                            ],
                        ],
                    ]);
            }
        });

        return collect($chunks)->map(function (Chunk $chunk) use ($responses) {
            $chunk->enhanced_content = $responses[$chunk->chunk_id]->json('choices.0.message.content');

            return $chunk;
        });
    }

    public function pdf(string $contents)
    {
        $dataUrl = 'data:application/pdf;base64,'.base64_encode($contents);

        return $this->completion([
            'model' => 'google/gemma-3-27b-it',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'What are the main points in this document?',
                        ],
                        [
                            'type' => 'file',
                            'file' => [
                                'name' => 'document.pdf',
                                'file' => $dataUrl,
                            ],
                        ],
                    ],
                ],
            ],
            'plugins' => [
                [
                    'id' => 'file-parser',
                    'pdf' => [
                        'engine' => 'pdf-text',
                    ],
                ],
            ],
        ])->json();
    }

    public function chunkWithPages(string $content, string $model = 'openai/gpt-4o-mini'): array
    {
        $response = $this->completion([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => config('prompts.enhance_function'),
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'create_chunks',
                        'description' => 'Create chunks from the provided content with their associated pages',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'chunks' => [
                                    'type' => 'array',
                                    'description' => 'Array of chunks with their content and associated pages',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'original_chunk' => [
                                                'type' => 'string',
                                                'description' => 'The content of the original chunk',
                                            ],
                                            'enhanced_chunk' => [
                                                'type' => 'string',
                                                'description' => 'The content of the enhanced chunk',
                                            ],
                                            'pages' => [
                                                'type' => 'array',
                                                'description' => 'Array of page numbers this chunk belongs to',
                                                'items' => [
                                                    'type' => 'integer',
                                                ],
                                            ],
                                        ],
                                        'required' => ['chunk', 'pages'],
                                    ],
                                ],
                            ],
                            'required' => ['chunks'],
                        ],
                    ],
                ],
            ],
            'tool_choice' => [
                'type' => 'function',
                'function' => [
                    'name' => 'create_chunks',
                ],
            ],
        ]);

        $toolCall = $response->json('choices.0.message.tool_calls.0');

        if ($toolCall && $toolCall['function']['name'] === 'create_chunks') {
            $arguments = json_decode($toolCall['function']['arguments'], true);

            return $arguments['chunks'] ?? [];
        }

        return [];
    }
}
