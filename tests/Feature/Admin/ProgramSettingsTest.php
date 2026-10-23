<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

/**
 * Settings → Platform → Programs: the paths of the programs that read files
 * and make PDFs. Only a program's own name or a full path that ends in it,
 * leading to a program on this server, is saved, so the panel cannot be used
 * to make the server run another command. "Detect" fills in what `which`
 * finds.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');

    $this->folder = sys_get_temp_dir().'/chatvia-programs-'.uniqid();
    File::ensureDirectoryExists($this->folder);

    foreach (['pdfinfo', 'weasyprint'] as $name) {
        file_put_contents("{$this->folder}/{$name}", "#!/bin/sh\nexit 0\n");
        chmod("{$this->folder}/{$name}", 0755);
    }
});

afterEach(function (): void {
    File::deleteDirectory($this->folder);
});

it('saves a full path to a program and applies it right away', function (): void {
    $driver = app('laravel-pdf.driver.weasyprint');

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->fillForm([
            'pdfinfo_path' => "{$this->folder}/pdfinfo",
            'weasyprint_path' => "  {$this->folder}/weasyprint  ",
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(PlatformSettings::class)->pdfinfo_path)->toBe("{$this->folder}/pdfinfo")
        ->and(config('services.bin.pdfinfo'))->toBe("{$this->folder}/pdfinfo")
        ->and(config('laravel-pdf.weasyprint.binary'))->toBe("{$this->folder}/weasyprint")
        // The PDF driver keeps the path it was built with, so it is built again.
        ->and(app('laravel-pdf.driver.weasyprint'))->not->toBe($driver);
});

it('refuses a path to another program', function (string $path): void {
    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->fillForm(['pdfinfo_path' => $path])
        ->call('save')
        ->assertHasFormErrors(['pdfinfo_path']);

    expect(app(PlatformSettings::class)->pdfinfo_path)->toBeNull()
        ->and(config('services.bin.pdfinfo'))->toBe('pdfinfo');
})->with([
    'a shell' => '/bin/sh',
    'another program name' => 'sh',
    'a relative path' => 'bin/pdfinfo',
    'a name with arguments' => 'pdfinfo -v',
]);

it('refuses a full path that leads to no program', function (): void {
    file_put_contents("{$this->folder}/pdftotext", 'not a program');
    chmod("{$this->folder}/pdftotext", 0644);

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->fillForm([
            'pdfinfo_path' => "{$this->folder}/missing/pdfinfo",
            'pdftotext_path' => "{$this->folder}/pdftotext",
        ])
        ->call('save')
        ->assertHasFormErrors(['pdfinfo_path', 'pdftotext_path']);
});

it('takes the program name, which the PATH resolves', function (): void {
    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->fillForm(['pandoc_path' => 'pandoc'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('services.bin.pandoc'))->toBe('pandoc');
});

it('shows whether this server finds each program', function (): void {
    app(PlatformSettings::class)->fill(['pdfinfo_path' => "{$this->folder}/pdfinfo"])->save();

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->assertSee('Programs')
        ->assertSee("Runs {$this->folder}/pdfinfo.");
});

it('marks a program the app can do without as not needed, and one it cannot as missing', function (): void {
    // pdfinfo has a built-in fallback; with no document reader on, pdftotext has none.
    config(['chatvia.ai.document_parser' => 'none']);
    app(PlatformSettings::class)->fill(['pdfinfo_path' => '/nowhere/pdfinfo', 'pdftotext_path' => '/nowhere/pdftotext'])->save();

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->assertSee('Not found · not needed')
        ->assertSee('Now: PDF files cannot be trained again while no document reader is on.')
        ->assertSee('Not found: pdftotext');
});

it('says missing pandoc, catdoc and catppt cost nothing, since the app reads every document format itself', function (): void {
    app(PlatformSettings::class)->fill(['pandoc_path' => '/nowhere/pandoc', 'catdoc_path' => '/nowhere/catdoc', 'catppt_path' => '/nowhere/catppt'])->save();

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->assertSee('Not found · not needed')
        ->assertDontSee('cannot be uploaded');
});

it('says what each missing program stops, with the settings in use', function (): void {
    config(['chatvia.ai.transcriber' => 'none', 'laravel-pdf.driver' => 'gotenberg']);

    expect(ServerProgram::Pdfinfo->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Ffprobe->lossWhenMissing())->toBeNull()
        // Speech to text is off; another maker makes PDFs.
        ->and(ServerProgram::Ffmpeg->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Weasyprint->lossWhenMissing())->toBeNull()
        // The app reads every document format itself.
        ->and(ServerProgram::Pandoc->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Catdoc->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Catppt->lossWhenMissing())->toBeNull();

    config(['chatvia.ai.transcriber' => 'scaleway', 'services.scalewayai.api_key' => 'key', 'laravel-pdf.driver' => 'weasyprint']);

    expect(ServerProgram::Ffmpeg->lossWhenMissing())->toContain('Recordings over 25 MB')
        ->and(ServerProgram::Weasyprint->lossWhenMissing())->toContain('Chat PDF exports');
});

it('detects a program with which and puts its path in the field', function (): void {
    Process::fake(['*which*pdfinfo*' => Process::result(output: "{$this->folder}/pdfinfo\n")]);

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->callAction(TestAction::make('detect_pdfinfo')->schemaComponent('pdfinfo_path', schema: 'form'))
        ->assertNotified("Found {$this->folder}/pdfinfo")
        ->assertSchemaStateSet(['pdfinfo_path' => "{$this->folder}/pdfinfo"], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('services.bin.pdfinfo'))->toBe("{$this->folder}/pdfinfo");
});

it('says so when which finds nothing', function (): void {
    Process::fake(['*which*catppt*' => Process::result(exitCode: 1)]);

    Livewire::actingAs($this->admin, 'admin')->test(ManagePlatformSettings::class)
        ->callAction(TestAction::make('detect_catppt')->schemaComponent('catppt_path', schema: 'form'))
        ->assertNotified('catppt was not found')
        ->assertSchemaStateSet(['catppt_path' => null], 'form');
});

it('accepts only the program name or a full path that ends in it', function (string $path, bool $accepted): void {
    expect(ServerProgram::Pdfinfo->acceptsPath($path))->toBe($accepted);
})->with([
    ['pdfinfo', true],
    ['/usr/bin/pdfinfo', true],
    ['/opt/homebrew/bin/pdfinfo', true],
    ['/Applications/My Tools/pdfinfo', true],
    ['/bin/sh', false],
    ['/usr/bin/pdfinfo/', false],
    ['usr/bin/pdfinfo', false],
    ['./pdfinfo', false],
    ['/usr/bin/pdfinfo.sh', false],
    ["/usr/bin\n/pdfinfo", false],
    ['PDFINFO', false],
]);
