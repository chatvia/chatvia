<?php

declare(strict_types=1);

namespace Support\Prompt;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * The safe part of Blade that an edited prompt may use (Settings → Prompts).
 * Blade compiles to PHP, so an edit could otherwise run any code on the
 * server. An edit may hold plain text and:
 *
 * - `{!! $name !!}` and `{{ $name }}`, with a variable, an array key of one
 *   (`$item['url']`), a `$loop` property, and an optional `?? 'default'`;
 * - `@if`, `@elseif`, `@unless` with one variable, maybe negated (`! $x`) or
 *   compared with a plain value (`$x !== ''`); `@else` and the matching ends;
 * - `@foreach ($items as $key => $item)` and `@endforeach`;
 * - `{{-- comments --}}`.
 *
 * Anything else that Blade or PHP would run is refused: other directives
 * (`@php`, `@include` …), PHP tags, components (`<x-…>`), function calls and
 * expressions. The variables must be ones the prompt is given.
 */
final class PromptTemplate
{
    /** A variable, an array key of one, or a property of the `$loop` of a `@foreach`. */
    private const string VARIABLE = '\$(?:loop->(?:index|iteration|remaining|count|first|last|even|odd|depth)|[A-Za-z_]\w*(?:\[(?:\'[^\'\\\\]*\'|"[^"\\\\$]*"|\d+)\])*)';

    /** A plain value: a string without interpolation, a number, true, false or null. */
    private const string LITERAL = '(?:\'[^\'\\\\]*\'|"[^"\\\\$]*"|-?\d+(?:\.\d+)?|true|false|null|\[\])';

    /** Directives an edit may use, with what their parentheses hold (null: none). */
    private const array DIRECTIVES = [
        'if' => 'condition',
        'elseif' => 'condition',
        'unless' => 'condition',
        'else' => null,
        'endif' => null,
        'endunless' => null,
        'foreach' => 'loop',
        'endforeach' => null,
    ];

    /** Directive pairs that must open and close in order. */
    private const array BLOCKS = ['if' => 'endif', 'unless' => 'endunless', 'foreach' => 'endforeach'];

    /**
     * What is wrong with an edited prompt, in plain words; empty when it is
     * safe to render.
     *
     * @param  list<string>|null  $variables  the variables the prompt may use, or null for any
     * @return list<string>
     */
    public static function problems(string $template, ?array $variables = null): array
    {
        $problems = [];
        $text = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $template);

        if (preg_match('/<\?|\?>/', $text) === 1) {
            $problems[] = 'PHP tags (<?php … ?>) are not allowed.';
        }

        if (preg_match('/<\/?(?:x[-:]|livewire:)/i', $text) === 1) {
            $problems[] = 'Blade components (<x-…>) are not allowed.';
        }

        // An escaped echo (@{{ … }}) prints as written.
        $echoes = (string) preg_replace('/@\{\{.*?\}\}/s', '', $text);
        preg_match_all('/\{!!(.*?)!!\}|\{\{(.*?)\}\}/s', $echoes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $expression = trim(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''));

            if (preg_match('/^'.self::VARIABLE.'(?:\s*\?\?\s*'.self::LITERAL.')?$/', $expression) !== 1) {
                $problems[] = "\"{$match[0]}\" may only print a variable, such as {!! \$question !!}.";
            }
        }

        $problems = [...$problems, ...self::directiveProblems($text)];

        if ($variables !== null) {
            // Variables the edit's own loops make are its own.
            $unknown = array_values(array_diff(self::inputs($template), $variables));

            if ($unknown !== []) {
                $problems[] = sprintf(
                    'Unknown %s: %s. This prompt can use: %s.',
                    count($unknown) === 1 ? 'variable' : 'variables',
                    implode(', ', array_map(fn (string $name): string => '$'.$name, $unknown)),
                    $variables === [] ? 'no variables' : implode(', ', array_map(fn (string $name): string => '$'.$name, $variables)),
                );
            }
        }

        return array_values(array_unique($problems));
    }

    public static function isSafe(string $template): bool
    {
        return self::problems($template) === [];
    }

    /**
     * The variables a template uses, `$loop` left out, in order of use.
     *
     * @return list<string>
     */
    public static function variables(string $template): array
    {
        $text = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $template);
        preg_match_all('/\{!!(.*?)!!\}|\{\{(.*?)\}\}|@(?:if|elseif|unless|foreach)\s*\((.*)\)/', $text, $matches);
        preg_match_all('/\$([A-Za-z_]\w*)/', implode(' ', [...$matches[1], ...$matches[2], ...$matches[3]]), $names);

        return array_values(array_diff(array_unique($names[1]), ['loop']));
    }

    /**
     * The variables a template is given, not those its loops make.
     *
     * @return list<string>
     */
    public static function inputs(string $template): array
    {
        preg_match_all('/@foreach\s*\(.*?\bas\s+(?:\$(\w+)\s*=>\s*)?\$(\w+)\s*\)/', $template, $loops);

        return array_values(array_diff(self::variables($template), array_filter([...$loops[1], ...$loops[2]])));
    }

    /**
     * @return list<string>
     */
    private static function directiveProblems(string $text): array
    {
        $problems = [];
        $open = [];

        preg_match_all('/\B@(@?\w+)/', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$name, $offset]) {
            // @@word prints "@word"; @word that is no directive is plain text.
            if (str_starts_with($name, '@') || ! self::isDirective($name)) {
                continue;
            }

            if (! array_key_exists($name, self::DIRECTIVES)) {
                $problems[] = "@{$name} is not allowed. You can use @if, @elseif, @else, @unless, @foreach and their ends.";

                continue;
            }

            $argument = self::argument($text, $offset + strlen($name));
            $kind = self::DIRECTIVES[$name];

            if ($kind === 'condition' && ($argument === null || preg_match('/^\s*(?:!\s*)?'.self::VARIABLE.'(?:\s*\?\?\s*'.self::LITERAL.')?(?:\s*(?:===|!==|==|!=|>=|<=|>|<)\s*'.self::LITERAL.')?\s*$/', $argument) !== 1)) {
                $problems[] = "@{$name} may only test one variable, such as @{$name} (\$email) or @{$name} (\$reference !== '').";
            }

            if ($kind === 'loop' && ($argument === null || preg_match('/^\s*'.self::VARIABLE.'\s+as\s+(?:\$\w+\s*=>\s*)?\$\w+\s*$/', $argument) !== 1)) {
                $problems[] = '@foreach may only loop over a variable, such as @foreach ($items as $index => $item).';
            }

            if (array_key_exists($name, self::BLOCKS)) {
                $open[] = $name;
            } elseif (in_array($name, self::BLOCKS, true)) {
                $opened = array_pop($open);

                if ($opened === null || self::BLOCKS[$opened] !== $name) {
                    $problems[] = "@{$name} does not close the block it is in.";
                }
            } elseif (in_array($name, ['else', 'elseif'], true) && end($open) !== 'if') {
                $problems[] = "@{$name} must be inside @if … @endif.";
            }
        }

        foreach ($open as $name) {
            $problems[] = "@{$name} has no @".self::BLOCKS[$name].'.';
        }

        return $problems;
    }

    /** Whether Blade compiles `@{name}` as a directive rather than leaving it as text. */
    private static function isDirective(string $name): bool
    {
        return method_exists(BladeCompiler::class, 'compile'.ucfirst($name))
            || array_key_exists($name, Blade::getCustomDirectives())
            || in_array($name, ['verbatim', 'endverbatim', 'php', 'endphp'], true);
    }

    /** The text inside the parentheses that follow a directive, or null when there are none. */
    private static function argument(string $text, int $offset): ?string
    {
        $rest = substr($text, $offset);

        if (preg_match('/^[ \t]*\(/', $rest, $start) !== 1) {
            return null;
        }

        $depth = 0;
        $length = strlen($rest);

        for ($i = strlen($start[0]) - 1; $i < $length; $i++) {
            $depth += match ($rest[$i]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return substr($rest, strlen($start[0]), $i - strlen($start[0]));
            }
        }

        return null;
    }
}
