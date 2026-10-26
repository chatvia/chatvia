<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Media;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Conversation;
use Domain\Llm\Enums\ImageShape;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * Asks for a picture in the hosted chat. The tool checks who is asking and
 * where, hands the request to {@see RequestMediaGenerationAction}, and returns
 * an `image` artifact the chat shows as a placeholder until the picture is
 * made in the background; or the reason it was turned down.
 */
#[Name('generate-image')]
#[Description('Make a picture for the visitor from a description. The picture is made in the background, and the chat shows it when it is ready. Write the description in English, with the subject, style, colours and setting the visitor asked for.')]
#[IsOpenWorld]
class GenerateImageTool extends Tool
{
    /** The longest description the image services all take. */
    public const int MAX_PROMPT = 1000;

    public function handle(Request $request, McpCallerContext $caller, RequestMediaGenerationAction $generate): Response|ResponseFactory
    {
        $refusal = MediaToolCaller::refusal($caller);

        if ($refusal instanceof Response) {
            return $refusal;
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT],
            'shape' => ['nullable', Rule::enum(ImageShape::class)],
        ]);

        $prompt = Str::squish($validated['prompt']);
        /** @var Agent $agent */
        $agent = $caller->agent;
        /** @var Conversation $conversation */
        $conversation = $caller->conversation;

        $result = $generate->execute($agent, $conversation, MediaRequestData::image(
            $prompt,
            ImageShape::tryFrom((string) ($validated['shape'] ?? '')) ?? ImageShape::Square,
        ));

        if (! $result->isAccepted()) {
            return Response::make(Response::text((string) $result->refusal?->reason(GeneratedMediaKind::Image)));
        }

        return Response::make(Response::text(
            'The picture is being made, and the chat shows it by itself when it is ready. Tell the visitor in one short sentence that it is on its way. Do not say where on the screen it will appear, do not say it is done, and do not describe it: you have not seen it.'
        ))->withStructuredContent(['image' => $result->artifact()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->description('What to draw, in English, up to '.self::MAX_PROMPT.' characters.')
                ->required(),
            'shape' => $schema->string()
                ->enum(array_map(static fn (ImageShape $shape): string => $shape->value, ImageShape::cases()))
                ->description('square (the default), landscape (wide) or portrait (tall).'),
        ];
    }
}
