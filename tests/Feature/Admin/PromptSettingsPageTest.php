<?php

use App\Admin\Clusters\Settings\Pages\ManagePromptSettings;
use App\Admin\Settings\PromptCatalog;
use App\Admin\Settings\PromptSettings;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Settings → Prompts: every prompt the app uses, folded, with its default
 * text from its file. An admin changes one or restores its default; an edit
 * must keep to the safe part of Blade, and prompt() renders it in place of
 * the file.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    Filament::setCurrentPanel('admin');
});

function promptsPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManagePromptSettings::class);
}

it('shows every prompt with its default text from its file', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManagePromptSettings::getUrl())
        ->assertOk()
        ->assertSee(['Follow-up questions', 'Agent role', 'Conversation titles', 'Default']);

    promptsPage($this->admin)
        ->assertFormSet(['prompts.conversation_title' => app(PromptCatalog::class)->defaultText('conversation/title')]);
});

it('saves only the prompts that differ from their file, and prompt() renders the edit', function (): void {
    promptsPage($this->admin)
        ->fillForm(['prompts.knowledge_rewrite_query_user' => 'New: {!! $user_query !!} / Old: {!! $previous_query !!}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(PromptSettings::class)->overrides)->toBe([
        'knowledge/rewrite-query-user' => 'New: {!! $user_query !!} / Old: {!! $previous_query !!}',
    ])->and(prompt('knowledge/rewrite-query-user', ['user_query' => 'and the price?', 'previous_query' => 'What does Pro cost?']))
        ->toBe('New: and the price? / Old: What does Pro cost?');
});

it('refuses an edit that would run code', function (string $text): void {
    promptsPage($this->admin)
        ->fillForm(['prompts.knowledge_rewrite_query_user' => $text])
        ->call('save')
        ->assertHasFormErrors(['prompts.knowledge_rewrite_query_user']);

    expect(app(PromptSettings::class)->overrides)->toBeNull();
})->with([
    'a php block' => ['@php system("id") @endphp'],
    'a php tag' => ['<?php echo 1; ?>'],
    'a function call' => ['{!! system("id") !!}'],
    'an include' => ["@include('prompts::math')"],
    'a component' => ['<x-alert />'],
    'an unknown variable' => ['{!! $secret !!}'],
    'an open block' => ['@if ($user_query) x'],
]);

it('restores the default of one prompt', function (): void {
    promptsPage($this->admin)
        ->fillForm(['prompts.conversation_title' => 'Write a short title.'])
        ->call('save');

    expect(prompt('conversation/title'))->toBe('Write a short title.');

    promptsPage($this->admin)
        ->callAction(TestAction::make('restore_conversation_title')->schemaComponent('restore-conversation_title', schema: 'form'))
        ->assertFormSet(['prompts.conversation_title' => app(PromptCatalog::class)->defaultText('conversation/title')]);

    expect(app(PromptSettings::class)->overrides)->toBeNull()
        ->and(prompt('conversation/title'))->toBe(view('prompts::conversation.title')->render());
});

it('uses the file when a saved edit is not safe', function (): void {
    config(['chatvia.prompts' => ['conversation/title' => '{!! phpinfo() !!}']]);

    expect(prompt('conversation/title'))->toBe(view('prompts::conversation.title')->render());
});

it('lists every prompt the code renders, each with its file', function (): void {
    $catalog = app(PromptCatalog::class);

    $used = collect(File::allFiles(base_path('src')))
        ->flatMap(fn (SplFileInfo $file): array => preg_match_all("/\\bprompt\\('([a-z0-9\\/._-]+)'/", (string) file_get_contents($file->getPathname()), $m) ? $m[1] : [])
        ->map(fn (string $name): string => str_replace('.', '/', $name))
        ->unique()
        ->values();

    foreach ($used as $name) {
        expect($catalog->has($name))->toBeTrue("prompt('{$name}') is missing from PromptCatalog");
    }

    foreach ($catalog->all() as $prompt) {
        expect(is_file($catalog->path($prompt->name)))->toBeTrue("{$prompt->name} has no file");
    }
});
