<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Operations\Media\NullSpeaker;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Llm\FakeSpeaker;

/**
 * An agent's request for a picture or an audio file: stored as pending with
 * its job queued, or turned down outside the hosted chat, while the service
 * is off, past the chat's hourly limit, or past the workspace's monthly one.
 */
beforeEach(function (): void {
    Queue::fake();
    FakeImageGenerator::install();
    app()->instance(Speaker::class, new FakeSpeaker);
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::HostedChat]);
});

function rmgRequest(MediaRequestData $request, ?Conversation $conversation = null): mixed
{
    return app(RequestMediaGenerationAction::class)->execute(test()->agent, $conversation ?? test()->conversation, $request);
}

it('stores a pending picture under the workspace and queues its job', function (): void {
    $result = rmgRequest(MediaRequestData::image('A red fox', ImageShape::Landscape));

    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($result->isAccepted())->toBeTrue()
        ->and($media->workspace_id)->toBe($this->agent->workspace_id)
        ->and($media->chat_conversation_id)->toBe($this->conversation->id)
        ->and($media->kind)->toBe(GeneratedMediaKind::Image)
        ->and($media->status)->toBe(GeneratedMediaStatus::Pending)
        ->and($media->shape)->toBe('landscape')
        ->and($media->service)->toBe('fake image-model')
        ->and($result->artifact('A red fox'))->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A red fox']);

    Queue::assertPushedOn('generation', GenerateMediaJob::class, fn (GenerateMediaJob $job): bool => $job->media->is($media));
});

it('stores an audio file with the agent\'s voice', function (): void {
    $result = rmgRequest(MediaRequestData::audio('Welcome to the shop.'));

    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($media->kind)->toBe(GeneratedMediaKind::Audio)
        ->and($media->voice)->toBe('alto')
        ->and($media->shape)->toBeNull()
        ->and($media->service)->toBe('fake tts');
});

it('turns down a request outside the hosted chat', function (): void {
    $widget = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::Widget]);

    expect(rmgRequest(MediaRequestData::image('A fox'), $widget)->refusal)->toBe(MediaRequestRefusal::WrongSurface)
        ->and(GeneratedMedia::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('turns down a request while the service is off', function (MediaRequestData $request): void {
    FakeImageGenerator::install(enabled: false);
    app()->instance(Speaker::class, new NullSpeaker);

    expect(rmgRequest($request)->refusal)->toBe(MediaRequestRefusal::NotSetUp);
})->with([
    'image' => [fn (): MediaRequestData => MediaRequestData::image('A fox')],
    'audio' => [fn (): MediaRequestData => MediaRequestData::audio('Hello.')],
]);

it('turns down a request past the chat\'s hourly limit', function (): void {
    config(['chatvia.ai.images_per_chat_per_hour' => 2]);

    rmgRequest(MediaRequestData::image('One'));
    rmgRequest(MediaRequestData::image('Two'));

    expect(rmgRequest(MediaRequestData::image('Three'))->refusal)->toBe(MediaRequestRefusal::HourlyLimit)
        ->and(rmgRequest(MediaRequestData::audio('Audio has its own limit.'))->isAccepted())->toBeTrue();

    $this->travel(61)->minutes();

    expect(rmgRequest(MediaRequestData::image('Four'))->isAccepted())->toBeTrue();
});

it('turns down a request past the workspace\'s monthly limit, and counts the ones it takes', function (): void {
    GenerationLimit::factory()->create(['workspace_id' => $this->agent->workspace_id, 'monthly_limit' => 1]);

    expect(rmgRequest(MediaRequestData::image('One'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::audio('Two'))->refusal)->toBe(MediaRequestRefusal::MonthlyLimit)
        ->and(GenerationLimit::query()->where('workspace_id', $this->agent->workspace_id)->value('generations_used'))->toBe(1);
});

it('gives a reason the agent can pass on', function (): void {
    expect(MediaRequestRefusal::HourlyLimit->reason(GeneratedMediaKind::Image))->toContain('images for this hour')
        ->and(MediaRequestRefusal::WrongSurface->reason(GeneratedMediaKind::Audio))->toStartWith('Audio files can only be made in the hosted chat');
});
