/**
 * WhatsApp connect via Meta's Embedded Signup (the Facebook JS SDK popup).
 * Unlike Messenger/Instagram there is no redirect: the customer picks their
 * WhatsApp Business Account + phone number inside Meta's popup, which hands back
 * the WABA id + phone number id (a postMessage event) and an auth code (the
 * FB.login callback). We POST all three; the backend exchanges the code,
 * subscribes the WABA, registers the number, and stores the token.
 */
import { router } from '@inertiajs/vue3';
import { ref, watch, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { loadFacebookSdk, type FacebookLoginResponse } from './facebookSdk';
import type { WhatsappChannelProps } from './types';

interface SessionInfo {
    waba_id?: string;
    phone_number_id?: string;
}

/** The slice of Meta's `WA_EMBEDDED_SIGNUP` postMessage payload we read. */
interface EmbeddedSignupMessage {
    type?: unknown;
    event?: unknown;
    data?: SessionInfo;
}

/**
 * The WABA + phone number ids from a finished Embedded Signup message, or null
 * for any other message. Throws on non-JSON data (the caller ignores those).
 */
function readFinishedSession(raw: unknown): SessionInfo | null {
    const message: unknown = JSON.parse(String(raw));
    if (typeof message !== 'object' || message === null) return null;

    const { type, event, data } = message as EmbeddedSignupMessage;
    if (type !== 'WA_EMBEDDED_SIGNUP' || (event !== 'FINISH' && event !== 'FINISH_ONLY_WABA')) return null;

    return { waba_id: data?.waba_id, phone_number_id: data?.phone_number_id };
}

export function useWhatsappEmbeddedSignup(options: {
    agentId: () => string;
    whatsapp: () => WhatsappChannelProps;
    /** The channel slide-over's open state — closing it cancels a stuck connect. */
    sheetOpen: Ref<boolean>;
}) {
    const { t } = useI18n();

    const connecting = ref(false);
    let sessionInfo: SessionInfo = {};

    const loadSdk = () => loadFacebookSdk(options.whatsapp().app_id, options.whatsapp().graph_version);

    /**
     * Preload the SDK as soon as the WhatsApp sheet opens, so the Embedded
     * Signup popup later fires inside the click gesture (loading it on click
     * would make the popup look unrequested and get blocked).
     */
    const preload = () => {
        const whatsapp = options.whatsapp();
        if (!whatsapp.connected && whatsapp.config_id) {
            void loadSdk();
        }
    };

    const onSessionMessage = (event: MessageEvent) => {
        // Only trust messages from a facebook.com subdomain (the `.` guards against a
        // look-alike like notfacebook.com). The ids here are hints anyway — the real
        // auth is the server-side code exchange — but keep the listener tight.
        if (typeof event.origin !== 'string' || !event.origin.endsWith('.facebook.com')) return;

        try {
            const finished = readFinishedSession(event.data);
            if (finished) {
                sessionInfo = finished;
            }
        } catch {
            // The SDK posts many non-JSON messages — ignore whatever we can't parse.
        }
    };

    // Single teardown for every exit path: drop the global 'message' listener and
    // clear the in-flight flag so the Connect button never gets stuck spinning.
    const reset = () => {
        window.removeEventListener('message', onSessionMessage);
        connecting.value = false;
    };

    const onLoginResponse = (response: FacebookLoginResponse) => {
        reset();

        const code = response?.authResponse?.code;
        const wabaId = sessionInfo.waba_id;
        const phoneNumberId = sessionInfo.phone_number_id;

        if (!code || !wabaId || !phoneNumberId) {
            // No code = the user closed/cancelled the popup (staying silent is
            // fine). A code but missing ids = the popup finished without a
            // usable number — surface it rather than failing silently.
            if (code && wabaId && !phoneNumberId) {
                toast.error(t('agents.channels.setup.whatsapp.finish_number'));
            } else if (code) {
                toast.error(t('agents.channels.setup.whatsapp.connect_failed'));
            }
            return;
        }

        router.post(
            route('agents.channels.whatsapp.connect', options.agentId()),
            { code, waba_id: wabaId, phone_number_id: phoneNumberId },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(t('agents.channels.whatsapp.connected_toast')),
            },
        );
    };

    const launchLogin = () => {
        window.FB?.login(onLoginResponse, {
            config_id: options.whatsapp().config_id,
            response_type: 'code',
            override_default_response_type: true,
            extras: { feature: 'whatsapp_embedded_signup', sessionInfoVersion: '3' },
        });
    };

    const connect = () => {
        const whatsapp = options.whatsapp();
        if (!whatsapp.app_id || !whatsapp.config_id) {
            toast.error(t('agents.channels.setup.whatsapp.not_configured'));
            return;
        }

        sessionInfo = {};
        connecting.value = true;
        window.addEventListener('message', onSessionMessage);

        if (window.FB) {
            launchLogin();
        } else {
            // Preload (on sheet-open) usually has the SDK ready; if not, load then
            // launch. Best-effort: a launch after the async load may be popup-blocked,
            // so the sheet-close recovery net below clears any stuck state.
            void loadSdk().then(() => {
                if (window.FB) {
                    launchLogin();
                } else {
                    reset();
                    toast.error(t('agents.channels.setup.whatsapp.not_configured'));
                }
            });
        }
    };

    // Recovery net: if the sheet closes while a connect is in flight (or the popup
    // is abandoned in a way that never fires FB.login's callback), clear the stuck
    // spinner + leaked 'message' listener so reopening the sheet works.
    watch(options.sheetOpen, (open) => {
        if (!open && connecting.value) {
            reset();
        }
    });

    return { connecting, connect, preload };
}
