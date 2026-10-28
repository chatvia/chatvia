<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Document\EpubFile;
use Tests\Support\Knowledge\Document\OldOffice\PowerPointBinaryFile;
use Tests\Support\Knowledge\Document\OldOffice\WordBinaryFile;
use Tests\Support\Llm\FakeTranscriber;

/**
 * POST agents.sources.files.upload takes a knowledge file of a type the admin
 * turned on (Settings → Ingestion → File types) and refuses the others, a file
 * the workspace already uploaded, and a recording this server cannot turn
 * into text (one that needs ffmpeg). Documents and ebooks need no program:
 * the app reads them itself.
 */
beforeEach(function (): void {
    Storage::fake();
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);

    $this->owner = User::factory()->create();
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

it('refuses a file larger than the parser takes, before reading it', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->create('handbook.txt', SourceFileTypes::MAX_UPLOAD_KB + 1),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});

it('refuses a file the workspace already uploaded', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);
    $upload = fn () => $this->actingAs($this->owner)->postJson(route('agents.sources.files.upload', $this->agent), [
        'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
    ]);

    $upload()->assertOk()->assertJsonPath('length', 1);
    $upload()->assertStatus(422)->assertJsonPath('errors.file.0', 'You have already uploaded this file.');
});

it('takes old Word, RTF and PowerPoint files on a server without catdoc, catppt or pandoc', function (Closure $file, int $pages, KnowledgeFileType $type): void {
    config([
        'chatvia.knowledge.file_types' => ['word', 'presentation'],
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/nowhere/catppt',
        'services.bin.pandoc' => '/nowhere/pandoc',
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => $file()])
        ->assertOk()
        ->assertJsonPath('length', $pages);

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->sole()->filetype)->toBe($type);
})->with([
    'an old Word file' => [fn (): UploadedFile => new UploadedFile(deleteAfterTest(new WordBinaryFile()->paragraph('Minutes of the board meeting.')->save()), 'minutes.doc', 'application/msword', null, true), 1, KnowledgeFileType::Word],
    'an old PowerPoint file' => [fn (): UploadedFile => new UploadedFile(deleteAfterTest(new PowerPointBinaryFile()->slide(['Welcome'])->slide(['Prices'])->save()), 'deck.ppt', 'application/vnd.ms-powerpoint', null, true), 2, KnowledgeFileType::Presentation],
    'an RTF file' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('letter.rtf', '{\\rtf1\\ansi\\pard Dear visitor,\\par}'), 1, KnowledgeFileType::Word],
]);

it('takes ebooks on a server without pandoc, since the app reads them itself', function (Closure $file): void {
    config(['chatvia.knowledge.file_types' => ['ebook'], 'services.bin.pandoc' => '/nowhere/pandoc']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => $file()])
        ->assertOk()
        ->assertJsonPath('length', 1);

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->sole()->filetype)->toBe(KnowledgeFileType::Ebook);
})->with([
    'an EPUB book' => [fn (): UploadedFile => new UploadedFile(deleteAfterTest(new EpubFile()->chapter('one.xhtml', '<h1>Spring</h1><p>Plant potatoes.</p>')->save()), 'garden.epub', 'application/epub+zip', null, true)],
    'an FB2 book' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('peas.fb2', '<?xml version="1.0" encoding="utf-8"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section><p>Sow peas in April.</p></section></body></FictionBook>')],
]);

it('refuses a recording the speech service does not take as it is, on a server without ffmpeg', function (string $name, string $content, string $message): void {
    config(['chatvia.knowledge.file_types' => ['audio'], 'services.bin.ffmpeg' => '/nowhere/ffmpeg']);
    app()->instance(Transcriber::class, FakeTranscriber::takingAsIs(['mp3'], maxBytes: 100));

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', $message);
})->with([
    'too large' => ['call.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".Str::repeat("\xFF\xFB\x90\x00", 64), 'This recording is too large to turn into text on this server. Upload a shorter recording.'],
    'another format' => ['call.wav', 'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16).pack('vvVVvv', 1, 1, 16000, 32000, 2, 16).'data'.pack('V', 0), 'This server can\'t turn .wav files into text. Upload an MP3 file instead.'],
]);
