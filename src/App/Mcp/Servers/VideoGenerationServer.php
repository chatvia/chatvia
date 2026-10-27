<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Media\GenerateVideoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Video generation capability connector: make a short video for the
 * visitor in the hosted chat (generate-video), with the video service an
 * admin picked in Settings → AI models.
 */
#[Name('Video generation')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Make a short video for the visitor when they ask for one ("make a video", "animate",
"create a clip of", "show me a video of"). One tool:
- generate-video: describe the video in English: the subject, what happens, the camera
  (close-up, wide shot, slow pan), the style, light and setting, and any sound or speech
  the visitor asked for. Pick landscape (wide) or portrait (tall, for phones).

The video is made in the background and takes a few minutes; the chat shows it by itself
as soon as it is ready. After calling the tool, say in one short sentence that it is on
its way and may take a few minutes. Never say where on the screen it will appear, never
say it is done, never describe a video you have not seen, and never paste a link for it.
If the tool says no video can be made, pass that on plainly.
Make one video per request unless the visitor asks for more.
TXT)]
class VideoGenerationServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    #[\Override]
    protected array $tools = [
        GenerateVideoTool::class,
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
