<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\AiProvider;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\OldOffice\PowerPointBinaryFile;
use Tests\Support\OldOffice\WordBinaryFile;

/**
 * An admin chooses the file types workspaces may upload as knowledge
 * (Settings → Ingestion → File types). The upload endpoint refuses the others,
 * and the files this server cannot read (a recording that needs ffmpeg), and
 * the dashboard gets the accepted types with the extensions it can read.
 * Documents and ebooks need no program.
 */
beforeEach(function (): void {
    Storage::fake();
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('refuses an upload whose file type the admin turned off', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Unsupported file type.');

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});

it('accepts an upload whose file type is on', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
        ])
        ->assertOk();

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->value('filetype'))->toBe(KnowledgeFileType::Text);
});

it('shares the accepted file types with their extensions', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf', 'audio'], 'chatvia.ai.transcriber' => 'scaleway']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeFileTypes.pdf', ['pdf'])
            ->has('knowledgeFileTypes.audio')
            ->missing('knowledgeFileTypes.word')
            ->missing('knowledgeFileTypes.video'));
});

it('offers no audio or video while no speech-to-text service is set up', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf', 'audio', 'video'], 'chatvia.ai.transcriber' => 'none']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('knowledgeFileTypes.pdf')
            ->missing('knowledgeFileTypes.audio')
            ->missing('knowledgeFileTypes.video'));
});

it('says whether websites can be imported', function (): void {
    config(['services.firecrawl.key' => null]);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canImportWebsites', false));

    config(['services.firecrawl.key' => 'fc-key']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canImportWebsites', true));
});

it('refuses a file the workspace already uploaded', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);
    $upload = fn () => $this->actingAs($this->owner)->postJson(route('agents.sources.files.upload', $this->agent), [
        'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
    ]);

    $upload()->assertOk()->assertJsonPath('length', 1);
    $upload()->assertStatus(422)->assertJsonPath('errors.file.0', 'You have already uploaded this file.');
});

it('offers and takes old Word, RTF and PowerPoint files on a server without catdoc, catppt or pandoc', function (): void {
    config([
        'chatvia.knowledge.file_types' => ['word', 'presentation'],
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/nowhere/catppt',
        'services.bin.pandoc' => '/nowhere/pandoc',
    ]);

    // The app reads them itself; Mistral OCR reads .docx and .pptx.
    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeFileTypes.word', ['doc', 'docx', 'rtf'])
            ->where('knowledgeFileTypes.presentation', ['ppt', 'pptx']));

    $doc = new WordBinaryFile()->paragraph('Minutes of the board meeting.')->save();
    $ppt = new PowerPointBinaryFile()->slide(['Welcome'])->slide(['Prices'])->save();

    foreach ([
        [new UploadedFile($doc, 'minutes.doc', 'application/msword', null, true), 1],
        [new UploadedFile($ppt, 'deck.ppt', 'application/vnd.ms-powerpoint', null, true), 2],
        [UploadedFile::fake()->createWithContent('letter.rtf', '{\\rtf1\\ansi\\pard Dear visitor,\\par}'), 1],
    ] as [$file, $pages]) {
        $this->actingAs($this->owner)
            ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('length', $pages);
    }

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->pluck('filetype')->all())
        ->toBe([KnowledgeFileType::Word, KnowledgeFileType::Presentation, KnowledgeFileType::Word]);

    @unlink($doc);
    @unlink($ppt);
});

it('offers ebooks and takes them on a server without pandoc, since the app reads them itself', function (): void {
    config(['chatvia.knowledge.file_types' => ['ebook'], 'services.bin.pandoc' => '/nowhere/pandoc']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('knowledgeFileTypes.ebook', ['epub', 'fb2']));

    $epub = create_temp_file('garden.epub', '');
    $zip = new ZipArchive;
    $zip->open($epub, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="content.opf"/></rootfiles></container>');
    $zip->addFromString('content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf"><manifest><item id="c1" href="one.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="c1"/></spine></package>');
    $zip->addFromString('one.xhtml', '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>t</title></head><body><h1>Spring</h1><p>Plant potatoes.</p></body></html>');
    $zip->close();
    $fb2 = '<?xml version="1.0" encoding="utf-8"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section><p>Sow peas in April.</p></section></body></FictionBook>';

    foreach ([new UploadedFile($epub, 'garden.epub', 'application/epub+zip', null, true), UploadedFile::fake()->createWithContent('peas.fb2', $fb2)] as $file) {
        $this->actingAs($this->owner)
            ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('length', 1);
    }

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->pluck('filetype')->all())
        ->toBe([KnowledgeFileType::Ebook, KnowledgeFileType::Ebook]);

    @unlink($epub);
});

it('refuses a recording the speech service does not take as it is, on a server without ffmpeg', function (string $name, string $content, string $message): void {
    config(['chatvia.knowledge.file_types' => ['audio'], 'services.bin.ffmpeg' => '/nowhere/ffmpeg']);
    // Takes MP3 files up to 100 bytes as they are.
    app()->instance(Transcriber::class, new class implements Transcriber
    {
        public function transcribe(string $filePath): string
        {
            return '';
        }

        public function takesAsIs(string $extension, int $bytes): bool
        {
            return $extension === 'mp3' && $bytes <= 100;
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'fake';
        }
    });

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', $message);
})->with([
    'too large' => ['call.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 64), 'This recording is too large to turn into text on this server. Upload a shorter recording.'],
    'another format' => ['call.wav', 'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16).pack('vvVVvv', 1, 1, 16000, 32000, 2, 16).'data'.pack('V', 0), 'This server can\'t turn .wav files into text. Upload an MP3 file instead.'],
]);
