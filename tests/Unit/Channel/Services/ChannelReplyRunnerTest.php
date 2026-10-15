<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Llm\Contracts\LlmProviderInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Forms render as a web-only inline artifact. The forms prompt is withheld off
 * the web, but as a backstop the channel runner also strips any stray
 * `<render-form …>` tag the model writes as text, so the raw tag never reaches
 * a text-only channel (Telegram, Slack, …). Each case runs a real turn whose
 * model streams the given answer.
 */
function crrReplyTo(string $answer): string
{
    $sse = 'data: '.json_encode(['id' => 'gen-test', 'choices' => [['delta' => ['content' => $answer]]]])."\n\n"
        ."data: [DONE]\n\n";

    bindFakeLlmProvider(new readonly class($sse) implements LlmProviderInterface
    {
        public function __construct(private string $sse) {}

        public function stream(string $model, array $messages, array $settings = []): ResponseInterface
        {
            return new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $this->sse);
        }

        public function client(): PendingRequest
        {
            throw new RuntimeException('The channel turn only streams.');
        }

        public function completion(array $data): never
        {
            throw new RuntimeException('The channel turn only streams.');
        }

        public function headers(): array
        {
            return [];
        }

        public function getGenerationCost(string $generationId): ?array
        {
            return null;
        }

        public function getCompletionEndpoint(): string
        {
            return 'https://llm.example.test/v1/chat/completions';
        }

        public function supportsCostTracking(): bool
        {
            return false;
        }
    });

    $agent = Agent::factory()->create(['is_public' => true, 'llm_id' => testLlm()->id]);
    $agent->setSetting('enabled', false, 'guardrails');
    $bot = AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
    $chat = app(TelegramChatResolver::class)->resolve($bot, '555', new TelegramSenderData('Sam'));

    return app(ChannelReplyRunner::class)->reply(new ChannelTurnData(
        agent: $agent->fresh(),
        sessionExternalId: $chat->sessionExternalId(),
        conversationExternalId: $chat->conversationExternalId(),
        message: 'I would like a demo',
        source: ConversationSource::Telegram,
        requestIdPrefix: 'tg_',
    ));
}

it('strips a self-closing render-form tag and tidies the gap it leaves', function (): void {
    expect(crrReplyTo("Here are your details.\n\n<render-form slug=\"book-a-demo\"/>\n\nOur team will follow up."))
        ->toBe("Here are your details.\n\nOur team will follow up.");
});

it('strips a paired render-form tag with inner content', function (): void {
    expect(crrReplyTo('Please fill this: <render-form slug="lead-capture">fallback</render-form> thanks!'))
        ->toBe('Please fill this:  thanks!');
});

it('leaves ordinary replies untouched', function (): void {
    $answer = 'Sure — could you share your name and email so the team can follow up?';

    expect(crrReplyTo($answer))->toBe($answer);
});
