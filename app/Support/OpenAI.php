<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Intervention\Image\Laravel\Facades\Image;

class OpenAI
{
    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openai.completion_endpoint'), $data)
            ->throw()
            ->json('choices.0.message.content');
    }

    public static function vision(string $prompt, string $image)
    {
        $imageUrl = $image;

        if (file_exists($image)) {
            $imageUrl = Image::read(file_get_contents($image))->toJpeg()->toDataUri();
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openai.api_endpoint').'/responses', [
                'model' => 'gpt-4.1-mini',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => $imageUrl,
                            ],
                        ],
                    ],
                ],
            ])
            ->json('output.0.content.0.text');
    }

    public static function analyzeFile(string $fileUrl): array
    {
        $prompt = config('prompts.file-description');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openai.api_endpoint').'/responses', [
                'model' => 'gpt-4.1',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'input_file',
                                'file_url' => $fileUrl,
                            ],
                        ],
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'name' => 'analyze_document',
                        'description' => 'Analyze uploaded document and provide comprehensive structured analysis',
                        'parameters' => self::fileAnalysisSchema(),
                    ],
                ],
            ])
            ->throw()
            ->json('output.0.arguments');

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function analyzeContent(string $content): array
    {
        $prompt = template(config('prompts.file-description'), ['text' => $content]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openai.api_endpoint').'/responses', [
                'model' => 'gpt-5-mini',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'name' => 'analyze_document',
                        'description' => 'Analyze uploaded document and provide comprehensive structured analysis',
                        'parameters' => self::fileAnalysisSchema(),
                    ],
                ],
            ])
            ->throw()
            ->json('output');

        $completedItem = collect($response)->firstWhere('status', 'completed');

        if (! $completedItem || ! isset($completedItem['arguments'])) {
            throw new \Exception('No completed function call found in response');
        }

        return is_string($completedItem['arguments'])
            ? json_decode($completedItem['arguments'], true, 512, JSON_THROW_ON_ERROR)
            : $completedItem['arguments'];
    }

    private static function fileAnalysisSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => 'Comprehensive detailed summary of the document. Must include main purpose, key findings, important details, conclusions, specific data/statistics, document structure, and significant insights. Must be prefixed with "Summary: ".',
                ],
                'questions' => [
                    'type' => 'string',
                    'description' => 'Generate a few questions about the document.',
                ],
                'document_type' => [
                    'type' => 'string',
                    'enum' => [
                        'academic_paper',
                        'technical_documentation',
                        'api_documentation',
                        'tutorial',
                        'knowledge_base',
                        'standard_operating_procedure',
                        'user_manual',
                        'business_report',
                        'financial_report',
                        'legal_document',
                        'contract',
                        'policy_document',
                        'email',
                        'memo',
                        'presentation',
                        'research_report',
                        'case_study',
                        'white_paper',
                        'specification',
                        'requirements_document',
                        'meeting_notes',
                        'training_material',
                        'marketing_material',
                        'news_article',
                        'blog_post',
                        'faq',
                        'troubleshooting_guide',
                        'installation_guide',
                        'configuration_guide',
                        'other',
                    ],
                    'description' => 'Most appropriate document type based on purpose, structure, and intended use. Choose the most specific applicable type, not generic classifications.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'complexity_level' => [
                            'type' => 'string',
                            'enum' => ['beginner', 'intermediate', 'advanced', 'expert'],
                            'description' => 'Document complexity based on vocabulary, concepts, and structure',
                        ],
                        'target_audience' => [
                            'type' => 'string',
                            'enum' => [
                                'general_public',
                                'students',
                                'professionals',
                                'technical_experts',
                                'business_users',
                                'developers',
                                'researchers',
                                'administrators',
                                'end_users',
                                'executives',
                                'other',
                            ],
                            'description' => 'Primary intended audience for the document',
                        ],
                        'writing_style' => [
                            'type' => 'string',
                            'enum' => [
                                'academic',
                                'technical',
                                'casual',
                                'formal',
                                'conversational',
                                'instructional',
                                'persuasive',
                                'informative',
                                'analytical',
                                'narrative',
                                'other',
                            ],
                            'description' => 'Primary writing style used in the document',
                        ],
                        'formatting_elements' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => [
                                    'headers',
                                    'bullet_points',
                                    'numbered_lists',
                                    'tables',
                                    'charts',
                                    'images',
                                    'diagrams',
                                    'code_blocks',
                                    'footnotes',
                                    'citations',
                                    'hyperlinks',
                                    'bold_text',
                                    'italic_text',
                                    'highlighted_text',
                                    'page_numbers',
                                    'table_of_contents',
                                    'index',
                                    'appendices',
                                    'other',
                                ],
                            ],
                            'description' => 'Special formatting, visual elements, or structural components present in the document',
                        ],
                        'estimated_reading_time_minutes' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'description' => 'Estimated reading time in minutes for an average reader',
                        ],
                        'key_topics' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'maxItems' => 10,
                            'description' => 'Main topics or themes covered in the document (maximum 10)',
                        ],
                        'contains_sensitive_info' => [
                            'type' => 'boolean',
                            'description' => 'Whether the document contains potentially sensitive or confidential information',
                        ],
                    ],
                    'required' => [
                        'complexity_level',
                        'target_audience',
                        'writing_style',
                        'formatting_elements',
                        'estimated_reading_time_minutes',
                        'key_topics',
                        'contains_sensitive_info',
                    ],
                ],
                'language' => [
                    'type' => 'string',
                    'pattern' => '^[a-z]{2}$',
                    'description' => 'Primary language as ISO 639-1 language code (e.g., en, ar, fr, es, de, it, pt, ru, ja, ko, zh, hi). For mixed languages, use the predominant language.',
                ],
                'confidence_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence level in the analysis accuracy (0.0 to 1.0)',
                ],
            ],
            'required' => [
                'summary',
                'document_type',
                'metadata',
                'language',
                'confidence_score',
            ],
            'additionalProperties' => false,
        ];

    }

    public static function calculateCostInCents(string $inputText, string $outputText, float $pricePerInputMilToken = 0.40, float $pricePerOutputMilToken = 1.60): float
    {
        $inputRate = $pricePerInputMilToken * 100 / 1_000_000;
        $outputRate = $pricePerOutputMilToken * 100 / 1_000_000;

        $inputCost = TikToken::countTokens($inputText) * $inputRate;
        $outputCost = TikToken::countTokens($outputText) * $outputRate;

        return round($inputCost + $outputCost, 2);
    }

    public static function rewriteQuery(string $userQuery, $previousQuery = null): array
    {
        $response = app(OpenAI::class)->completion([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
You are an expert at world knowledge. Your task is to step back and paraphrase a question to a more generic step-back question, which is easier to answer. Here are a few examples

"input": "Could the members of The Police perform lawful arrests?"
"output": "what can the members of The Police do?"

"input": "Jan Sindel’s was born in what country?"
"output": "what is Jan Sindel’s personal history?"

If the query is already clear and unambiguous, return it as-is.

Return only the rewritten query in plain text. Do not include any prefixes, suffixes, quotes, or formatting — just the clean, rewritten query.

You must preserve and use the exact language of the original content. Always match the original content\'s language exactly.

Return the detected content language as an ISO 639-1 language code, followed by a pipe character (|), then the response text.
Example: en|Here is my text

',
                ],
                [
                    'role' => 'user',
                    'content' => '
                        User Query is: <UserQuery>'.$userQuery.'</UserQuery>.
                        Previous Query is: <PreviousQuery>'.$previousQuery.'</PreviousQuery>.
                    ',
                ],
            ],
        ]);

        [$lang, $query] = explode('|', $response);

        return [
            'lang' => $lang,
            'query' => $query,
        ];
    }
}
