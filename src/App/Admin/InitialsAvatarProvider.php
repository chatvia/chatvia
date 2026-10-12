<?php

declare(strict_types=1);

namespace App\Admin;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin panel's default avatar: the person's initials on an ink circle,
 * drawn as an inline SVG. Filament's own default loads the avatar from
 * ui-avatars.com, which would send every admin's name to a third party on
 * each page.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        return $this->forName(Filament::getNameForDefaultAvatar($record));
    }

    /** The avatar of a name, as a data URL. */
    public function forName(string $name): string
    {
        $initials = htmlspecialchars($this->initials($name), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#171717"/><text x="32" y="32" dy="0.35em" text-anchor="middle" fill="#fafafa" font-family="Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif" font-size="25" font-weight="600" letter-spacing="-0.5">{$initials}</text></svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** The first letter of the first and the last word, or "?" for a blank name. */
    private function initials(string $name): string
    {
        // Leading punctuation ("[SYSTEM] Admin") is not part of a word.
        $words = array_values(array_filter(array_map(
            fn (string $word): string => (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word),
            preg_split('/\s+/u', trim($name)) ?: [],
        )));

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
