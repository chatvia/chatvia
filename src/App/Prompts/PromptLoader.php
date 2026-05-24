<?php

namespace App\Prompts;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PromptLoader
{
    public static function load(string $name, array $variables = []): string
    {
        //        $name = str_replace('/', '.', $name);
        //
        //        return view('prompts.'.$name, ...$variables)->render();

        $path = resource_path("prompts/{$name}.md");

        if (! File::exists($path)) {
            throw new RuntimeException("Prompt not found: {$name}");
        }

        $content = File::get($path);

        return template($content, $variables);
    }
}
