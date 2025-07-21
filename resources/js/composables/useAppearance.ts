import { onMounted, ref } from 'vue';
import axios from 'axios';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');

    onMounted(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}

// Add interface for appearance data
export interface AppearanceData {
    avatar: string | null;
    color_theme: string | null;
    color_shade: number | null;
    welcome_message: string | null;
    chat_window_title: string | null;
    chat_footer_text: string | null;
}

/**
 * Apply theme colors based on current appearance data and dark mode state
 */
const applyThemeColors = (appearance: AppearanceData) => {
    if (!appearance.color_theme) return;
    
    const theme = appearance.color_theme;
    const shade = appearance.color_shade || 500;
    
    // Check if dark mode is active
    const isDarkMode = document.documentElement.classList.contains('dark');
    // In dark mode, use lighter borders for visibility; in light mode, use subtle borders
    const borderShade = isDarkMode ? '400' : '200';
    
    document.documentElement.style.setProperty('--primary', `var(--${theme}-${shade})`);
    document.documentElement.style.setProperty('--color-border', `var(--${theme}-border)`);
    document.documentElement.style.setProperty('--border', `var(--${theme}-${borderShade})`);
    document.documentElement.style.setProperty('--ring', `var(--${theme}-${shade})`);
};
