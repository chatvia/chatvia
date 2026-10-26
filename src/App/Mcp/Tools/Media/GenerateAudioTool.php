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
 * Asks for an audio file in the hosted chat, in the agent's voice: of a text
 * the agent passes, or of the PDF the chatter attached, whose text is read
 * on the server so a long document is never copied through the model. Like
 * {@see GenerateImageTool}, it returns an `audio` artifact the chat shows as
 * a placeholder until the file is made in the background; or the reason it
 * was turned down.
 */
#[Name('generate-audio')]
#[Description('Make an audio file for the visitor to play or download: a text read aloud, or the PDF file the visitor attached read aloud in full. The file is made in the background, and the chat shows it when it is ready. For a text, pass the exact words to read, in the language they should be spoken in. For the attached PDF, set source to "attachment" and pass no text: never copy the file\'s text yourself.')]
#[IsOpenWorld]
class GenerateAudioTool extends Tool
{
    /** The longest text the agent may pass itself; an attached PDF has the admin's limit. */
    public const int MAX_TEXT = 4000;

    public function handle(Request $request, McpCallerContext $caller, RequestMediaGenerationAction $generate): Response|ResponseFactory
    {
        $refusal = MediaToolCaller::refusal($caller);

        if ($refusal instanceof Response) {
            return $refusal;
        }

        $validated = $request->validate([
            'source' => ['nullable', 'in:text,attachment'],
            'text' => ['required_unless:source,attachment', 'nullable', 'string', 'max:'.self::MAX_TEXT],
        ]);

        /** @var Agent $agent */
        $agent = $caller->agent;
        /** @var Conversation $conversation */
        $conversation = $caller->conversation;

        $result = $generate->execute($agent, $conversation, ($validated['source'] ?? 'text') === 'attachment'
            ? MediaRequestData::audioOfAttachment()
            : MediaRequestData::audio(Str::trim((string) $validated['text'])));

        if (! $result->isAccepted()) {
            return Response::make(Response::text((string) $result->refusal?->reason(GeneratedMediaKind::Audio)));
        }

        return Response::make(Response::text(
            'The audio file is being made, and the chat shows it by itself when it is ready. Tell the visitor in one short sentence that it is on its way. Do not say where on the screen it will appear, and do not say it is done.'
        ))->withStructuredContent(['audio' => $result->artifact()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()
                ->enum(['text', 'attachment'])
                ->description('"text" (the default) reads the text you pass; "attachment" reads the PDF file the visitor attached to this chat.'),
            'text' => $schema->string()
                ->description('The exact words to read aloud, up to '.self::MAX_TEXT.' characters. Leave it out when source is "attachment".'),
        ];
    }
}
