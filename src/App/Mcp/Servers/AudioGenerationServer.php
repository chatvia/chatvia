<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Media\GenerateAudioTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Audio generation capability connector: make an audio file of a text,
 * or of the PDF the visitor attached, read aloud in the hosted chat
 * (generate-audio), with the speech service of Settings → AI models → Audio
 * generation.
 */
#[Name('Audio generation')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Make an audio file of a text read aloud when the visitor asks for one ("read this
as audio", "make an MP3 of", "I want to listen to it"). One tool:
- generate-audio: pass the exact words to read, in the language they should be
  spoken in. Write the text first if the visitor asked you to (a summary, a
  welcome message), then pass it. To read the PDF file the visitor attached
  ("read this PDF aloud", "convert the file to audio"), set source to
  "attachment" and pass no text: never copy the file's text yourself.

The file is made in the background, and the chat shows it by itself as soon as it is
ready. After calling the tool, say in one short sentence that it is on its way. Never
say where on the screen it will appear, never say it is done, and never paste a link
for it. If the tool says no file can be made, pass
that on plainly.
TXT)]
class AudioGenerationServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    #[\Override]
    protected array $tools = [
        GenerateAudioTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    #[\Override]
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    #[\Override]
    protected array $prompts = [];
}
