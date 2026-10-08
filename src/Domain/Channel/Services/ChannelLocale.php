<?php

namespace Domain\Channel\Services;

use Domain\Agent\Models\Agent;

/**
 * The language for the fixed system messages an agent sends on a messaging
 * channel ("Thinking…", "unavailable right now", the Telegram built-in
 * commands). A channel message carries no reliable visitor language, so this
 * uses the language the owner pinned for the agent's Hosted Chat or Widget,
 * and the app default when both are on "auto". Texts use English-as-key
 * translations from `lang/{locale}.json`; a missing translation shows the
 * English text.
 */
final class ChannelLocale
{
    public static function for(Agent $agent): string
    {
        foreach (['embed', 'widget'] as $group) {
            $language = $agent->settings($group)->get('language');

            if (is_string($language) && $language !== '' && $language !== 'auto') {
                return $language;
            }
        }

        return (string) config('app.locale', 'en');
    }

    /**
     * Translate a system message into the agent's channel language.
     *
     * @param  array<string, string>  $replace
     */
    public static function text(Agent $agent, string $key, array $replace = []): string
    {
        return (string) __($key, $replace, self::for($agent));
    }
}
