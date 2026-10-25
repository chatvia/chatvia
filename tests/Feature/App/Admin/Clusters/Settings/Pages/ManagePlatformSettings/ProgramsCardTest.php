<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Process;
use Tests\Support\Files\ProgramFolder;

/**
 * Settings → Platform → Programs: the paths of the programs that read files
 * and make PDFs. Only a program's own name or a full path that ends in it,
 * leading to a program on this server, is saved, so the panel cannot be used
 * to make the server run another command. "Detect" fills in what `which`
 * finds. Which paths a program takes is ServerProgramTest's.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');

    $this->folder = ProgramFolder::make(['pdfinfo', 'weasyprint'], notRunnable: ['pdftotext']);
});

it('saves a full path to a program and applies it right away', function (): void {
    $driver = app('laravel-pdf.driver.weasyprint');

    adminPage(ManagePlatformSettings::class, $this->admin)
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
    adminPage(ManagePlatformSettings::class, $this->admin)
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
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm([
            'pdfinfo_path' => "{$this->folder}/missing/pdfinfo",
            'pdftotext_path' => "{$this->folder}/pdftotext",
        ])
        ->call('save')
        ->assertHasFormErrors(['pdfinfo_path', 'pdftotext_path']);
});

it('takes the program name, which the PATH resolves', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['pandoc_path' => 'pandoc'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('services.bin.pandoc'))->toBe('pandoc');
});

it('shows whether this server finds each program', function (): void {
    app(PlatformSettings::class)->fill(['pdfinfo_path' => "{$this->folder}/pdfinfo"])->save();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSee('Programs')
        ->assertSee("Uses {$this->folder}/pdfinfo.");
});

it('marks a program the app can do without as not needed, and one it cannot as missing', function (): void {
    // pdfinfo has a built-in fallback; with no document reader on, pdftotext has none.
    config(['chatvia.ai.document_parser' => 'none']);
    app(PlatformSettings::class)->fill(['pdfinfo_path' => '/nowhere/pdfinfo', 'pdftotext_path' => '/nowhere/pdftotext'])->save();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSee('Not found · not needed')
        ->assertSee('Without it: PDF files cannot be trained again while no document reader is on.')
        ->assertSee('Not found: pdftotext');
});

it('says missing pandoc, catdoc and catppt cost nothing, since the app reads every document format itself', function (): void {
    app(PlatformSettings::class)->fill(['pandoc_path' => '/nowhere/pandoc', 'catdoc_path' => '/nowhere/catdoc', 'catppt_path' => '/nowhere/catppt'])->save();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertSee('Not found · not needed')
        ->assertDontSee('cannot be uploaded');
});

it('detects a program with which and puts its path in the field', function (): void {
    Process::fake(['*which*pdfinfo*' => Process::result(output: "{$this->folder}/pdfinfo\n")]);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->callAction(TestAction::make('detect_pdfinfo')->schemaComponent('pdfinfo_path', schema: 'form'))
        ->assertNotified("Found {$this->folder}/pdfinfo")
        ->assertSchemaStateSet(['pdfinfo_path' => "{$this->folder}/pdfinfo"], 'form')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('services.bin.pdfinfo'))->toBe("{$this->folder}/pdfinfo");
});

it('says so when which finds nothing', function (): void {
    Process::fake(['*which*catppt*' => Process::result(exitCode: 1)]);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->callAction(TestAction::make('detect_catppt')->schemaComponent('catppt_path', schema: 'form'))
        ->assertNotified('catppt was not found')
        ->assertSchemaStateSet(['catppt_path' => null], 'form');
});
