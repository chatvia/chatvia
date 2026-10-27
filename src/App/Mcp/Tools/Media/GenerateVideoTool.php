<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Media;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Conversation;
use Domain\Llm\Enums\VideoShape;
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
 * Asks for a short video in the hosted chat. The tool checks who is asking
 * and where, hands the request to {@see RequestMediaGenerationAction}, and
 * returns a `video` artifact the chat shows as a placeholder until the
 * video is made in the background (a few minutes); or the reason it was
 * turned down.
 */
#[Name('generate-video')]
#[Description('Make a short video for the visitor from a description. The video is made in the background, takes a few minutes, and the chat shows it when it is ready. Write the description in English: the subject, what happens, the camera, the style and setting the visitor asked for.')]
#[IsOpenWorld]
class GenerateVideoTool extends Tool
{
    /** The longest description the video services all take. */
    public const int MAX_PROMPT = 1000;

    public function handle(Request $request, McpCallerContext $caller, RequestMediaGenerationAction $generate): Response|ResponseFactory
    {
        $refusal = MediaToolCaller::refusal($caller);

        if ($refusal instanceof Response) {
            return $refusal;
        }

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:'.self::MAX_PROMPT],
            'shape' => ['nullable', Rule::enum(VideoShape::class)],
        ]);

        $prompt = Str::squish($validated['prompt']);
        /** @var Agent $agent */
        $agent = $caller->agent;
        /** @var Conversation $conversation */
        $conversation = $caller->conversation;

        $result = $generate->execute($agent, $conversation, MediaRequestData::video(
            $prompt,
            VideoShape::tryFrom((string) ($validated['shape'] ?? '')) ?? VideoShape::Landscape,
        ));

        if (! $result->isAccepted()) {
            return Response::make(Response::text((string) $result->refusal?->reason(GeneratedMediaKind::Video)));
        }

        return Response::make(Response::text(
            'The video is being made, which takes a few minutes, and the chat shows it by itself when it is ready. Tell the visitor in one short sentence that it is on its way and may take a few minutes. Do not say where on the screen it will appear, do not say it is done, and do not describe it: you have not seen it.'
        ))->withStructuredContent(['video' => $result->artifact()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'prompt' => $schema->string()
                ->description('What the video shows, in English, up to '.self::MAX_PROMPT.' characters.')
                ->required(),
            'shape' => $schema->string()
                ->enum(array_map(static fn (VideoShape $shape): string => $shape->value, VideoShape::cases()))
                ->description('landscape (wide, the default) or portrait (tall, for phones).'),
        ];
    }
}
