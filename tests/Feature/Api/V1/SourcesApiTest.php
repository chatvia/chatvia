<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Enums\AiProvider;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/*
|--------------------------------------------------------------------------
| Public API — knowledge sources
|--------------------------------------------------------------------------
|
| A source is named by its public id (`external_id`), never the integer key:
| the create answers and the list carry it, and delete takes it. A delete
| finds the source through the agent's own sources, so another agent's source
| is a 404 even inside the same workspace.
*/

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources";
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

function sourcesApiText(int $workspaceId): SourceText
{
    return SourceText::query()->create([
        'workspace_id' => $workspaceId,
        'filepath' => 'workspaces/test/texts/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'Our refund policy',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
    ]);
}

it('adds a file and answers 201 with its public id', function (): void {
    connectAiProviders(AiProvider::Mistral);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC');

    $response = $this->withToken($this->token)
        ->post($this->uri.'/files', ['file' => UploadedFile::fake()->createWithContent('cover.png', $png)])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'file')
        ->assertJsonPath('data.name', 'cover.png');

    $file = SourceFile::query()->where('external_id', $response->json('data.id'))->firstOrFail();

    expect($this->agent->files()->whereKey($file->id)->exists())->toBeTrue();
});

it('answers an unsupported file with the standard 422 field error', function (): void {
    $this->withToken($this->token)
        ->post($this->uri.'/files', ['file' => UploadedFile::fake()->createWithContent('tool.exe', 'MZ')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'Unsupported file type.']);

    expect(SourceFile::query()->where('workspace_id', $this->agent->workspace_id)->exists())->toBeFalse();
});

it('adds a text and answers 201 with its public id', function (): void {
    $response = $this->withToken($this->token)
        ->postJson($this->uri.'/texts', ['content' => Str::repeat('Refunds take five business days. ', 20)])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'text');

    expect(SourceText::query()->where('external_id', $response->json('data.id'))->exists())->toBeTrue()
        ->and($response->json('data.id'))->toBeString()->not->toBeNumeric();
});

it('adds a website and answers 201 with its public id', function (): void {
    Event::fake([WebsiteCrawlStarted::class]);
    Http::fake(['https://docs.example.test/*' => Http::response('<html></html>', 200)]);
    SafeUrl::resolveHostsUsing(fn (): array => ['93.184.216.34']);

    $response = $this->withToken($this->token)
        ->postJson($this->uri.'/urls', ['url' => 'https://docs.example.test/start'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'url')
        ->assertJsonPath('data.name', 'https://docs.example.test/start');

    expect(SourceWebsite::query()->where('external_id', $response->json('data.id'))->exists())->toBeTrue();
});

it('lists sources by their public ids', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);

    $this->withToken($this->token)->getJson($this->uri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $file->external_id)
        ->assertJsonPath('data.0.kind', 'file')
        ->assertJsonPath('data.0.mime_type', $file->mime_type)
        ->assertJsonPath('data.0.length_pages', null);
});

it('deletes each kind of source by its public id', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $text = sourcesApiText($this->agent->workspace_id);
    $website = SourceWebsite::query()->create(['workspace_id' => $this->agent->workspace_id, 'url' => 'https://docs.example.test/', 'status' => 'ingested']);
    $this->agent->files()->attach($file->id);
    $this->agent->texts()->attach($text->id);
    $this->agent->websites()->attach($website->id);

    $this->withToken($this->token)->deleteJson("{$this->uri}/files/{$file->external_id}")->assertNoContent();
    $this->withToken($this->token)->deleteJson("{$this->uri}/texts/{$text->external_id}")->assertNoContent();
    $this->withToken($this->token)->deleteJson("{$this->uri}/urls/{$website->external_id}")->assertNoContent();

    expect($this->agent->files()->exists())->toBeFalse()
        ->and($this->agent->texts()->exists())->toBeFalse()
        ->and($this->agent->websites()->exists())->toBeFalse();
});

it('404s a source of another agent of the same workspace', function (): void {
    $text = sourcesApiText($this->agent->workspace_id);
    createAgent($this->owner)->texts()->attach($text->id);

    $this->withToken($this->token)->deleteJson("{$this->uri}/texts/{$text->external_id}")->assertNotFound();

    expect(SourceText::query()->whereKey($text->id)->exists())->toBeTrue();
});
