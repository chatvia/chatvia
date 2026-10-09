/**
 * Lazy loader for Meta's Facebook JS SDK, which runs WhatsApp Embedded Signup.
 * The script is injected only when the WhatsApp slide-over asks for it, never
 * globally: it is a third-party script we keep off every other page.
 */

/** What the Embedded Signup popup hands back to the `FB.login` callback. */
export interface FacebookLoginResponse {
    authResponse?: { code?: string } | null;
}

// The SDK attaches itself to window — declare the slice we call.
declare global {
    interface Window {
        FB?: {
            init: (options: Record<string, unknown>) => void;
            login: (callback: (response: FacebookLoginResponse) => void, options: Record<string, unknown>) => void;
        };
        fbAsyncInit?: () => void;
    }
}

/**
 * Resolves once `window.FB` is initialised. Safe to call more than once: the
 * script tag is only added the first time, and an already-loaded SDK resolves
 * straight away.
 */
export function loadFacebookSdk(appId: string | null, version: string): Promise<void> {
    return new Promise((resolve) => {
        if (window.FB) {
            resolve();
            return;
        }

        window.fbAsyncInit = () => {
            window.FB?.init({
                appId,
                autoLogAppEvents: true,
                xfbml: false,
                version,
            });
            resolve();
        };

        if (!document.getElementById('facebook-jssdk')) {
            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            document.body.appendChild(script);
        }
    });
}
