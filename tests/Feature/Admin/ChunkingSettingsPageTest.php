<?php

use App\Admin\Clusters\Settings\Pages\ManageChunkingSettings;
use App\Admin\Settings\ChunkingSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The Chunking page of the admin panel: the chunk size and its unit, how
 * each kind of content is split (with a preview of the unsaved options), and,
 * under Advanced, the strategy for each kind and the tokenizer. Splitting
 * runs in-process: the page calls no outside service.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    Http::preventStrayRequests();
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

it('shows the chunk size, how each kind of content is split, and Advanced', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageChunkingSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder([
            'Chunk size', 'Up to 400 tokens · no overlap',
            'How content is split',
            'Markdown', 'Web pages, Markdown files and ebooks', 'One section per heading',
            'Text', 'Typed texts and .txt files', 'Paragraphs, then sentences',
            'Transcript', 'Audio and video transcripts', 'Whole sentences',
            'Documents', 'One chunk per page',
            'Try it',
            'Advanced',
        ])
        ->assertDontSee('Chunker')
        ->assertDontSee('github.com/chatvia/chunker');
});

it('offers each kind only the strategies that suit it', function (): void {
    $fields = chunkingPage($this->admin)->instance()->form->getFlatFields(withHidden: true);

    $options = fn (string $field): array => array_keys($fields[$field]->getOptions());

    expect($options('markdown_strategy'))->toBe(['markdown', 'recursive'])
        ->and($options('text_strategy'))->toBe(['recursive', 'sentence', 'fixed'])
        ->and($options('transcript_strategy'))->toBe(['sentence', 'recursive', 'fixed'])
        ->and(array_keys($fields['tokenizer']->getOptions()))->toBe(['cl100k_base', 'o200k_base', 'p50k_base', 'r50k_base']);
});

it('refuses a strategy that does not suit the kind of content', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['transcript_strategy' => 'markdown'])
        ->call('save')
        ->assertHasFormErrors(['transcript_strategy']);
});

it('saves the unit, the size, the strategies and the tokenizer, and the splitter uses them', function (): void {
    chunkingPage($this->admin)
        ->fillForm([
            'chunk_unit' => 'characters',
            'chunk_size' => 1200,
            'chunk_overlap' => 100,
            'markdown_strategy' => 'recursive',
            'transcript_strategy' => 'fixed',
            'tokenizer' => 'o200k_base',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $markdown = ChunkingConfigData::forKind(ContentKind::Markdown);

    expect(chunkingSetting('chunk_unit'))->toBe('characters')
        ->and(chunkingSetting('chunk_size'))->toBe(1200)
        ->and($markdown->unit)->toBe(ChunkSizeUnit::Characters)
        ->and($markdown->size)->toBe(1200)
        ->and($markdown->overlap)->toBe(100)
        ->and($markdown->strategy)->toBe(SplitStrategy::Recursive)
        ->and(ChunkingConfigData::forKind(ContentKind::Transcript)->strategy)->toBe(SplitStrategy::Fixed)
        ->and(ChunkingConfigData::forKind(ContentKind::Text)->encoding)->toBe('o200k_base');
});

it('limits the size by its unit', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['chunk_unit' => 'tokens', 'chunk_size' => 9000])
        ->call('save')
        ->assertHasFormErrors(['chunk_size']);

    chunkingPage($this->admin)
        ->fillForm(['chunk_unit' => 'characters', 'chunk_size' => 9000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(chunkingSetting('chunk_size'))->toBe(9000);
});

it('keeps the defaults unsaved when they are not changed', function (): void {
    chunkingPage($this->admin)->call('save')->assertHasNoFormErrors();

    expect(chunkingSetting('chunk_unit'))->toBeNull()
        ->and(chunkingSetting('chunk_size'))->toBeNull()
        ->and(chunkingSetting('markdown_strategy'))->toBeNull()
        ->and(chunkingSetting('tokenizer'))->toBeNull();
});

it('refuses an overlap that is not smaller than the chunk size', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['chunk_size' => 100, 'chunk_overlap' => 100])
        ->call('save')
        ->assertHasFormErrors(['chunk_overlap']);

    expect(chunkingSetting('chunk_size'))->toBeNull();
});

it('previews a kind of content with the unsaved options, in the unit on the page', function (): void {
    chunkingPage($this->admin)
        ->fillForm([
            'chunk_unit' => 'characters',
            'chunk_size' => 30,
            'transcript_strategy' => 'sentence',
            'preview_kind' => 'transcript',
            'preview_text' => 'Returns within 30 days. Refunds take five days.',
        ])
        ->callAction(previewAction())
        ->assertSee('2 chunks, split as Transcript with Whole sentences')
        ->assertSee('23 to 23 characters, 23 on average.')
        ->assertSee('Refunds take five days.');

    // A preview saves nothing.
    expect(chunkingSetting('transcript_strategy'))->toBeNull()
        ->and(chunkingSetting('chunk_unit'))->toBeNull();
});

it('opens the whole chunk in a modal from its card', function (): void {
    $long = str_repeat('Refunds go back to the card you paid with. ', 20)."\n\nThe last line of the chunk.";

    chunkingPage($this->admin)
        ->fillForm(['chunk_size' => 1000, 'preview_kind' => 'text', 'preview_text' => $long])
        ->callAction(previewAction())
        // The card shows only the start of the chunk.
        ->assertSee('View whole chunk')
        ->assertDontSee('The last line of the chunk.')
        ->mountAction(TestAction::make('viewChunk')->schemaComponent('preview-chunk-0', schema: 'form'))
        ->assertMountedActionModalSee(['Chunk 1', mb_strlen($long).' characters', 'The last line of the chunk.']);
});

it('previews the sample as Markdown, one section per heading, by default', function (): void {
    chunkingPage($this->admin)
        ->callAction(previewAction())
        ->assertSee('split as Markdown with One section per heading')
        ->assertSee('tokens');
});

it('starts the preview with the sample help centre, split as Markdown', function (): void {
    chunkingPage($this->admin)
        ->assertSchemaStateSet([
            'chunk_unit' => 'tokens',
            'preview_kind' => 'markdown',
            'preview_text' => File::get(resource_path('chunking/markdown.md')),
        ]);
});

it('shows the sample of the kind to split as', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['preview_kind' => 'transcript'])
        ->assertSchemaStateSet(['preview_text' => File::get(resource_path('chunking/transcript.txt'))])
        ->fillForm(['preview_kind' => 'text'])
        ->assertSchemaStateSet(['preview_text' => File::get(resource_path('chunking/text.txt'))])
        ->fillForm(['preview_kind' => 'markdown'])
        ->assertSchemaStateSet(['preview_text' => File::get(resource_path('chunking/markdown.md'))]);
});

it('keeps the admin\'s own text when the kind changes, and clears the last result', function (): void {
    $page = chunkingPage($this->admin)
        ->fillForm(['preview_text' => 'My own text. It stays.'])
        ->callAction(previewAction())
        ->assertSee('1 chunk, split as Markdown');

    $page->fillForm(['preview_kind' => 'transcript'])
        ->assertSchemaStateSet(['preview_text' => 'My own text. It stays.'])
        ->assertDontSee('1 chunk, split as');

    expect($page->instance()->preview)->toBeNull();
});

it('shows why a preview failed', function (): void {
    chunkingPage($this->admin)
        ->fillForm(['chunk_size' => 0, 'chunk_overlap' => 500])
        ->callAction(previewAction())
        ->assertSee('The text could not be split')
        ->assertSee('The overlap must be smaller than the chunk size.');
});
