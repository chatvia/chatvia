<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use Filament\Facades\Filament;
use Tests\Support\Files\ProgramFolder;

/**
 * Settings → Platform → Programs: the paths of the programs that read files
 * and make PDFs. Only a program's own name or a full path that ends in it,
 * leading to a program on this server, is saved, so the panel cannot be used
 * to make the server run another command. Which paths a program takes is
 * ServerProgramTest's.
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
        ->and(app('laravel-pdf.driver.weasyprint'))->not->toBe($driver);
});

it('refuses a path to another program and keeps the old one', function (string $path): void {
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

it('refuses a full path that leads to no program or to a file that does not run', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm([
            'pdfinfo_path' => "{$this->folder}/missing/pdfinfo",
            'pdftotext_path' => "{$this->folder}/pdftotext",
        ])
        ->call('save')
        ->assertHasFormErrors(['pdfinfo_path', 'pdftotext_path']);

    expect(app(PlatformSettings::class)->pdfinfo_path)->toBeNull()
        ->and(app(PlatformSettings::class)->pdftotext_path)->toBeNull();
});
