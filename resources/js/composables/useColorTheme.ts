/**
 * Applies an agent's colour theme (palette + shade) to the page by pointing the
 * `--primary` and chat-bubble CSS variables at that palette (the text on
 * `--primary` is dark or light by its lightness). Used by the hosted
 * chat and the chatter login page.
 */
import { nearestShade } from '@/lib/colorShades';

export const useColorTheme = () => {
    const applyColorTheme = (appearance?: { color_theme?: string; color_shade?: number }) => {
        if (appearance?.color_theme) {
            const theme = appearance.color_theme;
            const shade = nearestShade(appearance.color_shade || 500);
            document.documentElement.style.setProperty('--primary', `var(--${theme}-${shade})`);
            // Dark mode flips each palette, so a shade that is dark in light
            // mode is light there: the text on it follows its lightness.
            document.documentElement.style.setProperty(
                '--primary-foreground',
                'oklch(from var(--primary) clamp(0.145, (0.65 - l) * 1000, 0.985) 0 0)',
            );
            document.documentElement.style.setProperty('--chat-bubble-color', `var(--${theme}-chat-bubble)`);
            document.documentElement.style.setProperty('--chat-text-color', `var(--${theme}-chat-text)`);
        }
    };

    return {
        applyColorTheme,
    };
};
