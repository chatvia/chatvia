<?php

use App\Admin\Clusters\Settings\Pages\ManageChunkingSettings;
use App\Admin\Settings\ChunkingSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkerOption;
use Domain\Knowledge\Ingestion\Enums\ChunkerStrategy;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Chunking page of the admin panel: the splitter and tokenizer, the
 * chunker server, the chunker's strategy with only its own options, and a
 * preview that splits a sample with the unsaved options.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    config(['services.chunkfit.base_url' => 'http://chunker:8000', 'services.chunkfit.api_key' => null]);

    Http::fake([
        'chunker:8000/v1/engines' => Http::response([
            'chunkers' => array_map(
                fn (ChunkerStrategy $strategy): array => ['engine' => $strategy->engine(), 'strategy' => $strategy->strategy()],
                ChunkerStrategy::cases(),
            ),
            'encodings' => array_keys(ChunkerOption::ENCODINGS),
            'embeddings' => ['configured' => true, 'model' => 'qwen3-embedding-8b'],
        ]),
        'chunker:8000/v1/chunk' => Http::response(['chunks' => [
            ['text' => 'Returns within 30 days.', 'token_count' => 6],
            ['text' => 'Refunds take five days.', 'token_count' => 5],
        ]]),
        'chunker:8000/v1/tokens' => Http::response(['data' => [['index' => 0, 'tokens' => 7]]]),
    ]);
});

function chunkingSetting(string $property): mixed
{
    app()->forgetScopedInstances();

    return app(ChunkingSettings::class)->{$property};
}

function chunkingPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageChunkingSettings::class);
}

function previewAction(): TestAction
{
    return TestAction::make('runPreview')->schemaComponent('preview-actions', schema: 'form');
}

it('shows a section for each part, with what is in use', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageChunkingSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Splitter and tokenizer', 'Automatic · cl100k_base', 'Chunker server', 'Connected: 12 strategies. Embeddings: qwen3-embedding-8b.', 'Strategy', 'Recursive (LangChain) · 400 tokens · no overlap']);
});

it('shows the values in use and the options of the default strategy', function (): void {
    chunkingPage($this->admin)
        ->assertSchemaStateSet(['text_splitter' => 'auto', 'tokenizer' => null]);

    chunkingPage($this->admin)
        ->assertSchemaStateSet(['engine' => 'langchain', 'strategy' => 'langchain.recursive', 'length_unit' => 'tokens'])
        ->assertFormFieldVisible('chunk_size')
        ->assertFormFieldVisible('language')
        ->assertFormFieldHidden('threshold');
});

it('shows only the options of the chosen strategy', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['engine' => 'chonkie', 'strategy' => 'chonkie.semantic'])
        ->assertFormFieldVisible('threshold')
        ->assertFormFieldVisible('similarity_window')
        ->assertFormFieldHidden('chunk_overlap')
        ->assertFormFieldHidden('language');
});

it('picks the first strategy of a library when the library changes', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['engine' => 'llamaindex'])
        ->assertSchemaStateSet(['strategy' => 'llamaindex.sentence']);
});

it('hides the chunker sections when the built-in splitter is chosen', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['text_splitter' => 'local'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageChunkingSettings::getUrl())
        ->assertSee('Built-in splitter')
        ->assertDontSee('chunking-strategy')
        ->assertDontSee('chunking-server');
});

it('saves the strategy, its options and the tokenizer, and they reach the chunker', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['text_splitter' => 'chunkfit', 'tokenizer' => 'o200k_base'])
        ->call('save')
        ->assertHasNoFormErrors();

    chunkingPage($this->admin)
        ->fillForm([
            'engine' => 'chonkie',
            'strategy' => 'chonkie.sentence',
            'chunk_size' => 256,
            'chunk_overlap' => 32,
            'min_sentences_per_chunk' => 2,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $config = ChunkerConfigData::fromConfig();

    expect(chunkingSetting('strategy'))->toBe('chonkie.sentence')
        ->and(chunkingSetting('chunk_size'))->toBe(256)
        ->and(config('chatvia.ai.text_splitter'))->toBe('chunkfit')
        ->and(config('chatvia.ai.tokenizer'))->toBe('o200k_base')
        ->and($config->encoding)->toBe('o200k_base')
        ->and($config->toChunker())->toBe([
            'engine' => 'chonkie',
            'strategy' => 'sentence',
            'chunk_size' => 256,
            'chunk_overlap' => 32,
            'min_sentences_per_chunk' => 2,
        ]);
});

it('offers the tokenizers the chunker reports', function (): void {
    // Another chunker: the fakes above answer for chunker:8000 (and any host ending so).
    config(['services.chunkfit.base_url' => 'http://older.test']);
    Http::fake(['older.test/v1/engines' => Http::response(['chunkers' => [], 'encodings' => ['o200k_base'], 'embeddings' => ['configured' => false]])]);

    chunkingPage($this->admin)
        ->fillForm(['tokenizer' => 'cl100k_base'])
        ->call('save')
        ->assertHasFormErrors(['tokenizer']);
});

it('keeps the defaults unsaved when they are not changed', function (): void {
    chunkingPage($this->admin)->call('save')->assertHasNoFormErrors();

    expect(chunkingSetting('text_splitter'))->toBeNull()
        ->and(chunkingSetting('strategy'))->toBeNull()
        ->and(chunkingSetting('length_unit'))->toBeNull();
});

it('refuses an overlap that is not smaller than the chunk size', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['chunk_size' => 100, 'chunk_overlap' => 100])
        ->call('save')
        ->assertHasFormErrors(['chunk_overlap']);

    expect(chunkingSetting('chunk_size'))->toBeNull();
});

it('previews the unsaved options with the chunker', function (): void {
    chunkingPage($this->admin)
        ->fillForm([
            'engine' => 'llamaindex',
            'strategy' => 'llamaindex.token',
            'chunk_size' => 128,
            'preview_text' => 'Returns within 30 days. Refunds take five days.',
        ])
        ->callAction(previewAction())
        ->assertSee('2 chunks with the Fixed tokens strategy')
        ->assertSee('5 to 6 tokens, 6 on average.')
        ->assertSee('Refunds take five days.');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://chunker:8000/v1/chunk'
        && $request['text'] === 'Returns within 30 days. Refunds take five days.'
        && $request['chunker'] === ['engine' => 'llamaindex', 'strategy' => 'token', 'chunk_size' => 128, 'chunk_overlap' => 0]);

    // A preview saves nothing.
    expect(chunkingSetting('strategy'))->toBeNull();
});

it('previews with the built-in splitter when no chunker is set up', function (): void {
    config(['services.chunkfit.base_url' => '']);

    chunkingPage($this->admin)
        ->fillForm(['preview_text' => "First paragraph.\n\nSecond paragraph."])
        ->callAction(previewAction())
        ->assertSee('1 chunk with the built-in splitter');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/chunk'));
});

it('shows why the chunker refused a preview', function (): void {
    config(['services.chunkfit.base_url' => 'http://refusing.test']);
    Http::fake(['refusing.test/v1/chunk' => Http::response(['detail' => 'This strategy needs an embeddings model'], 503)]);

    chunkingPage($this->admin)
        ->callAction(previewAction())
        ->assertSee('The text could not be split')
        ->assertSee('This strategy needs an embeddings model');
});
