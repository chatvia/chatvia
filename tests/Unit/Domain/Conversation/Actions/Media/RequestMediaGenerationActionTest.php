<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Actions\Media\RequestMediaGenerationAction;
use Domain\Conversation\Data\Media\MediaRequestData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Enums\MediaRequestRefusal;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Operations\Media\NullSpeaker;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Llm\FakeSpeaker;
use Tests\Support\Llm\FakeVideoGenerator;

/**
 * An agent's request for a picture, an audio file or a video: stored as
 * pending with the service and model that make it and its job queued, or
 * turned down outside the hosted chat, while no model is listed, past the chat's hourly limit, or past the workspace's
 * monthly one (videos have their own). An audio file of the attached PDF
 * reads the PDF's text on the server. A queued job that dies marks its item
 * timed out, so the chat never waits for good.
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

it('stores a pending picture under the workspace and queues its job', function (): void {
    $result = rmgRequest(MediaRequestData::image('A red fox', ImageShape::Landscape));

    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($result->isAccepted())->toBeTrue()
        ->and($media->workspace_id)->toBe($this->agent->workspace_id)
        ->and($media->chat_conversation_id)->toBe($this->conversation->id)
        ->and($media->kind)->toBe(GeneratedMediaKind::Image)
        ->and($media->status)->toBe(GeneratedMediaStatus::Pending)
        ->and($media->metadata)->toEqual(['shape' => 'landscape', 'model' => 'fal-ai/flux-2'])
        ->and($media->service)->toBe('fal')
        ->and($result->artifact())->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A red fox', 'model_name' => null]);

    Queue::assertPushedOn('generation', GenerateMediaJob::class, fn (GenerateMediaJob $job): bool => $job->media->is($media));
});

it('stores an audio file with the agent\'s voice', function (): void {
    $result = rmgRequest(MediaRequestData::audio('Welcome to the shop.'));

    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($media->kind)->toBe(GeneratedMediaKind::Audio)
        ->and($media->metadata)->toBe(['voice' => 'alto'])
        ->and($media->service)->toBe('fake tts');
});

it('turns down a request outside the hosted chat', function (): void {
    $widget = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::Widget]);

    expect(rmgRequest(MediaRequestData::image('A fox'), $widget)->refusal)->toBe(MediaRequestRefusal::WrongSurface)
        ->and(GeneratedMedia::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('turns down a request while no model is listed', function (MediaRequestData $request): void {
    FakeImageGenerator::install(enabled: false);
    FakeVideoGenerator::install(enabled: false);
    app()->instance(AudioGeneration::class, new AudioGeneration(new NullSpeaker));

    expect(rmgRequest($request)->refusal)->toBe(MediaRequestRefusal::NotSetUp);
})->with([
    'image' => [fn (): MediaRequestData => MediaRequestData::image('A fox')],
    'audio' => [fn (): MediaRequestData => MediaRequestData::audio('Hello.')],
    'video' => [fn (): MediaRequestData => MediaRequestData::video('A fox running')],
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

function rmgAttachPdf(string $text): Attachment
{
    Storage::fake(Attachment::DISK);
    Process::fake(['*pdftotext*' => Process::result($text)]);

    return Attachment::factory()->forConversation(test()->conversation)->stored()->create(['filename' => 'Handbook.pdf']);
}

it('reads the attached PDF into the audio file, with its name as the caption', function (): void {
    rmgAttachPdf("Page one text.\fPage two text.\f");

    $result = rmgRequest(MediaRequestData::audioOfAttachment());
    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($media->prompt)->toBe("Page one text.\n\nPage two text.")
        ->and($result->artifact()['prompt'])->toBe('Handbook.pdf');
    Process::assertRan(fn ($process): bool => Str::contains(implode(' ', (array) $process->command), 'pdftotext'));
});

it('turns down an attached-file request it cannot read aloud', function (?string $text, MediaRequestRefusal $refusal): void {
    config(['chatvia.ai.audio_max_characters' => 20]);

    if ($text !== null) {
        rmgAttachPdf($text);
    }

    expect(rmgRequest(MediaRequestData::audioOfAttachment())->refusal)->toBe($refusal)
        ->and(GeneratedMedia::query()->count())->toBe(0);
})->with([
    'no PDF attached' => [null, MediaRequestRefusal::NoAttachment],
    'a scan with no text' => ["  \f\f", MediaRequestRefusal::NoReadableText],
    'longer than one file reads' => [Str::repeat('A long sentence. ', 5), MediaRequestRefusal::TooLong],
]);

it('stores a pending video in the shape asked for, with the admin\'s length and resolution, and queues its job', function (): void {
    config(['chatvia.ai.video_duration' => 6, 'chatvia.ai.video_resolution' => '1080p']);
    $result = rmgRequest(MediaRequestData::video('A fox running', VideoShape::Portrait));

    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($result->isAccepted())->toBeTrue()
        ->and($media->kind)->toBe(GeneratedMediaKind::Video)
        ->and($media->metadata)->toEqual(['shape' => 'portrait', 'seconds' => 6, 'resolution' => '1080p', 'model' => 'fal-ai/wan/v2.2-a14b/text-to-video'])
        ->and($media->service)->toBe('fal')
        ->and($result->artifact())->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A fox running', 'model_name' => null]);

    Queue::assertPushedOn('generation', GenerateMediaJob::class, fn (GenerateMediaJob $job): bool => $job->media->is($media));
});

it('turns down a video past its own hourly limit', function (): void {
    config(['chatvia.ai.videos_per_chat_per_hour' => 1]);

    expect(rmgRequest(MediaRequestData::video('One'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::video('Two'))->refusal)->toBe(MediaRequestRefusal::HourlyLimit)
        ->and(rmgRequest(MediaRequestData::image('Pictures have their own limit.'))->isAccepted())->toBeTrue();
});

it('gives back the hourly slot of a video that failed, so the chatter can try again', function (): void {
    config(['chatvia.ai.videos_per_chat_per_hour' => 1]);
    Event::fake([GeneratedMediaUpdated::class]);

    $failed = GeneratedMedia::query()->where('external_id', rmgRequest(MediaRequestData::video('One'))->mediaId)->sole();
    app(FailGeneratedMediaAction::class)->execute($failed, GeneratedMediaFailure::Refused);

    expect(rmgRequest(MediaRequestData::video('One again'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::video('Two'))->refusal)->toBe(MediaRequestRefusal::HourlyLimit);
});

it('counts videos against the workspace\'s video limit, apart from pictures', function (): void {
    GenerationLimit::factory()->create(['workspace_id' => $this->agent->workspace_id, 'monthly_limit' => 5, 'monthly_video_limit' => 1]);

    expect(rmgRequest(MediaRequestData::video('One'))->isAccepted())->toBeTrue()
        ->and(rmgRequest(MediaRequestData::video('Two'))->refusal)->toBe(MediaRequestRefusal::MonthlyLimit)
        ->and(rmgRequest(MediaRequestData::image('A picture'))->isAccepted())->toBeTrue()
        ->and(MediaRequestRefusal::MonthlyLimit->reason(GeneratedMediaKind::Video))->toContain('limit of videos');
});

it('marks the item timed out when its queued job dies', function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
    $result = rmgRequest(MediaRequestData::image('A red fox', ImageShape::Landscape));
    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();
    $job = Queue::pushed(GenerateMediaJob::class)->sole();

    $job->failed(new RuntimeException('Timed out'));

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe(GeneratedMediaFailure::TimedOut);
});

function rmgOfferImageModels(): void
{
    config([
        'chatvia.ai.image_models' => ['fal:fal-ai/flux-2', 'openai:gpt-image-2'],
        'chatvia.ai.default_image_model' => 'fal:fal-ai/flux-2',
    ]);
    test()->agent->setSetting('image', true, OfferedMediaModels::SETTINGS_GROUP);
    $server = AgentMcpServer::factory()->builtin('image-generation')->create(['workspace_id' => test()->agent->workspace_id]);
    test()->agent->mcpServers()->attach($server->id);
}

it('stores the model the chatter picked while the agent offers it', function (): void {
    rmgOfferImageModels();
    $this->conversation->update(['media_models' => ['image' => 'openai:gpt-image-2']]);

    $result = rmgRequest(MediaRequestData::image('A red fox'));
    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($media->metadata)->toEqual(['shape' => 'square', 'model' => 'gpt-image-2', 'model_name' => 'GPT Image 2'])
        ->and($media->service)->toBe('openai')
        ->and($result->artifact()['model_name'])->toBe('GPT Image 2');
});

it('uses the admin\'s model for a pick the agent does not offer', function (): void {
    rmgOfferImageModels();
    $this->conversation->update(['media_models' => ['image' => 'fal:fal-ai/flux-2-pro']]);

    $result = rmgRequest(MediaRequestData::image('A red fox'));
    $media = GeneratedMedia::query()->where('external_id', $result->mediaId)->sole();

    expect($media->metadata)->toEqual(['shape' => 'square', 'model' => 'fal-ai/flux-2'])
        ->and($media->service)->toBe('fal')
        ->and($result->artifact()['model_name'])->toBeNull();
});
