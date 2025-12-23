export const useColorTheme = () => {
    const applyColorTheme = (appearance?: { color_theme?: string; color_shade?: number }) => {
        if (appearance?.color_theme) {
            const theme = appearance.color_theme;
            const shade = appearance.color_shade || 500;
            document.documentElement.style.setProperty('--primary', `var(--${theme}-${shade})`);
            document.documentElement.style.setProperty('--chat-bubble-color', `var(--${theme}-chat-bubble)`);
            document.documentElement.style.setProperty('--chat-text-color', `var(--${theme}-chat-text)`);
        }
    };

    return {
        applyColorTheme,
    };
};
