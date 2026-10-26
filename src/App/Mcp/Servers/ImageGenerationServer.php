<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Media\GenerateImageTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Image generation capability connector: make a picture for the visitor
 * in the hosted chat (generate-image), with the image service an admin picked
 * in Settings → AI models.
 */
#[Name('Image generation')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Make pictures for the visitor when they ask for one ("draw", "make an image",
"design a logo", "show me a picture of"). One tool:
- generate-image: describe the picture in English, with the subject, style,
  colours and setting the visitor asked for, and pick a shape.

The picture is made in the background and appears under your reply a moment later.
After calling the tool, say in one short sentence that it is on its way. Never say it
is done, never describe a picture you have not seen, and never paste a link or a
markdown image for it. If the tool says no picture can be made, pass that on plainly.
Make one picture per request unless the visitor asks for more.
TXT)]
class ImageGenerationServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    #[\Override]
    protected array $tools = [
        GenerateImageTool::class,
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
