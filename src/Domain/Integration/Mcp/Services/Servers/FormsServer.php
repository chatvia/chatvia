<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\FindFormsTool;
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
Render real, fillable forms inline in the chat instead of asking for fields one at a
time. Forms are discovered dynamically — you are NOT told up front which forms exist.
Two tools:
- find-forms: search the agent's forms by the visitor's intent. Returns the matching
  forms, each with a `slug`, name and purpose. Call this the moment a visitor wants to
  do something a form would capture (book a demo, request a quote/callback, sign up, be
  contacted, ...). It returns nothing for general or informational questions.
- render-form: show the visitor a specific form by `slug` (from find-forms). The form
  collects and stores the answers itself.

Flow: when a visitor expresses such an intent, call find-forms first. If it returns a
form that fits, call render-form with that slug. If it returns nothing, or none fit,
keep helping conversationally — do NOT invent a form. The form persists the data itself
on submit. After rendering, briefly tell the visitor the form is shown below. Forms
render only on the web chat surfaces; if render-form reports the surface can't display a
form, just keep helping the visitor conversationally.
TXT)]
class FormsServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        FindFormsTool::class,
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
