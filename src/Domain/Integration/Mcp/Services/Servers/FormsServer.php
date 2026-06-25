<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\RenderFormTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Forms')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Render a real, fillable form inline in the chat instead of asking for fields one
at a time. Exposes a single tool:
- render-form: show the visitor a form (lead capture, booking, qualification, or a
  custom form the agent admin built). The form collects and stores the answers
  itself.

Call render-form when the visitor agrees to share their details, asks for a quote,
demo, callback, or booking, or when collecting several fields at once would be
smoother than a back-and-forth. Do NOT also call capture-lead in the same turn —
the form persists the data on submit. After rendering, briefly tell the visitor the
form is shown below. If the tool reports the surface can't display a form, fall back
to collecting details conversationally and call capture-lead.
TXT)]
class FormsServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        RenderFormTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
