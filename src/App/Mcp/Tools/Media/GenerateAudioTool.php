<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Media;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * Asks for an audio file of a text read aloud, in the hosted chat, in the
 * agent's read-aloud voice. Like {@see GenerateImageTool}, it returns an
 * `audio` artifact the chat shows as a placeholder until the file is made in
 * the background; or the reason it was turned down.
 */
#[Name('generate-audio')]
#[Description('Make an audio file of a text read aloud, for the visitor to play or download. The file is made in the background and appears under your reply a moment later. Pass the exact words to read, in the language they should be spoken in.')]
#[IsOpenWorld]
class GenerateAudioTool extends Tool
{
    /** The longest text one file reads. */
    public const int MAX_TEXT = 4000;

    /** How much of the text the reply keeps as the artifact's caption. */
    private const int CAPTION = 200;

    public function handle(Request $request, McpCallerContext $caller, RequestMediaGenerationAction $generate): Response|ResponseFactory
    {
        $refusal = MediaToolCaller::refusal($caller);

        if ($refusal instanceof Response) {
            return $refusal;
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:'.self::MAX_TEXT],
        ]);

        $text = Str::trim($validated['text']);
        /** @var Agent $agent */
        $agent = $caller->agent;
        /** @var Conversation $conversation */
        $conversation = $caller->conversation;

        $result = $generate->execute($agent, $conversation, MediaRequestData::audio($text));

        if (! $result->isAccepted()) {
            return Response::make(Response::text((string) $result->refusal?->reason(GeneratedMediaKind::Audio)));
        }

        return Response::make(Response::text(
            'The audio file is being made and will appear under your reply in a moment. Tell the visitor in one short sentence that it is on its way. Do not say it is done.'
        ))->withStructuredContent(['audio' => $result->artifact(Str::limit($text, self::CAPTION))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()
                ->description('The exact words to read aloud, up to '.self::MAX_TEXT.' characters.')
                ->required(),
        ];
    }
}
