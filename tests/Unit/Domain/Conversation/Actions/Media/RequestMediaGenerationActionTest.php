<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Llm\FakeSpeaker;
use Tests\Support\Llm\FakeVideoGenerator;

/**
 * An agent's request for a picture, an audio file or a video is turned down
 * outside the hosted chat, while no model is listed, past the chat's hourly
 * limit (videos have their own, and a failed video gives its slot back), or
 * past the workspace's monthly limit (videos again have their own).
 */
beforeEach(function (): void {
    Queue::fake();
    FakeImageGenerator::install();
    FakeVideoGenerator::install();
    app()->instance(AudioGeneration::class, new AudioGeneration(new FakeSpeaker));
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::HostedChat]);
});

function rmgRequest(MediaRequestData $request, ?Conversation $conversation = null): mixed
{
    return app(RequestMediaGenerationAction::class)->execute(test()->agent, $conversation ?? test()->conversation, $request);
}

it('turns down a request outside the hosted chat or while no model is listed, storing nothing', function (): void {
    $widget = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::Widget]);

    $wrongSurface = rmgRequest(MediaRequestData::image('A fox'), $widget)->refusal;
    FakeImageGenerator::install(enabled: false);
    $notSetUp = rmgRequest(MediaRequestData::image('A fox'))->refusal;

    expect($wrongSurface)->toBe(MediaRequestRefusal::WrongSurface)
        ->and($notSetUp)->toBe(MediaRequestRefusal::NotSetUp)
        ->and(GeneratedMedia::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('keeps an hourly limit per kind, and gives back the slot of a video that failed', function (): void {
    config(['chatvia.ai.images_per_chat_per_hour' => 2, 'chatvia.ai.videos_per_chat_per_hour' => 1]);
    Event::fake([GeneratedMediaUpdated::class]);

    rmgRequest(MediaRequestData::image('One'));
    rmgRequest(MediaRequestData::image('Two'));
    $video = GeneratedMedia::query()->where('external_id', rmgRequest(MediaRequestData::video('One'))->mediaId)->sole();

    expect(rmgRequest(MediaRequestData::image('Three'))->refusal)->toBe(MediaRequestRefusal::HourlyLimit)
        ->and(rmgRequest(MediaRequestData::video('Two'))->refusal)->toBe(MediaRequestRefusal::HourlyLimit)
        ->and(rmgRequest(MediaRequestData::audio('Audio has its own limit.'))->isAccepted())->toBeTrue();

    app(FailGeneratedMediaAction::class)->execute($video, GeneratedMediaFailure::Refused);

    expect(rmgRequest(MediaRequestData::video('One again'))->isAccepted())->toBeTrue();

    $this->travel(61)->minutes();

    expect(rmgRequest(MediaRequestData::image('Four'))->isAccepted())->toBeTrue();
});

it('turns down a request past the workspace\'s monthly limit, with videos counted apart', function (): void {
    GenerationLimit::factory()->create(['workspace_id' => $this->agent->workspace_id, 'monthly_limit' => 1, 'monthly_video_limit' => 1]);

    expect(rmgRequest(MediaRequestData::image('One'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::audio('Two'))->refusal)->toBe(MediaRequestRefusal::MonthlyLimit)
        ->and(rmgRequest(MediaRequestData::video('One'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::video('Two'))->refusal)->toBe(MediaRequestRefusal::MonthlyLimit)
        ->and(GenerationLimit::query()->where('workspace_id', $this->agent->workspace_id)->value('generations_used'))->toBe(1);
});
