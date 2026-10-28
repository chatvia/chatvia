<script lang="ts" setup>
// Side sheet with everything captured for one lead: contact details, custom
// form fields and technical context (IP, locale, URLs, browser / OS / device
// parsed from the user agent).
import { Button } from '@/shared/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/shared/ui/sheet';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import { useClipboard } from '@vueuse/core';
import { Briefcase, Building2, Check, Copy, ExternalLink, Mail, MapPin, MessageSquare, Phone } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

type CustomFieldValue = string | number | boolean | null;

interface Lead {
    id: number;
    name: string | null;
    email: string | null;
    phone?: string | null;
    job_title?: string | null;
    company?: string | null;
    location?: string | null;
    message?: string | null;
    status?: string;
    status_label?: string;
    session_id?: string;
    custom_fields?: Record<string, CustomFieldValue>;
    created_at: string;
    contacted_at?: string | null;
    qualified_at?: string | null;
    converted_at?: string | null;
    ip_address?: string | null;
    referrer_url?: string | null;
    page_url?: string | null;
    locale?: string | null;
    user_agent?: string | null;
}

interface Props {
    open: boolean;
    lead: Lead | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Copy functionality
const { copy } = useClipboard({ legacy: true });
const copiedEmail = ref(false);

const copyEmail = async () => {
    if (!props.lead?.email) return;

    try {
        await copy(props.lead.email);
        copiedEmail.value = true;
        toast.success(t('agents.leads.details_sheet.email_copied'));

        // Reset the copied state after 2 seconds
        setTimeout(() => {
            copiedEmail.value = false;
        }, 2000);
    } catch {
        toast.error(t('agents.leads.details_sheet.copy_email_error'));
    }
};

// Lead presentation helpers (for avatar & status)
const leadInitials = computed(() => {
    if (!props.lead) return '';
    if (props.lead.name)
        return props.lead.name
            .split(' ')
            .map((p: string) => p[0])
            .join('')
            .slice(0, 2)
            .toUpperCase();
    if (props.lead.email) return props.lead.email[0].toUpperCase();
    return '?';
});

// --- User Agent Parsing (lightweight) ---
// Browser and OS are null when not recognised; the template shows "Unknown".
type DeviceType = 'desktop' | 'mobile' | 'tablet';

interface ParsedUA {
    browser: string | null;
    os: string | null;
    device: DeviceType;
    raw: string;
}
function parseUserAgent(ua?: string | null): ParsedUA | null {
    if (!ua) return null;
    const raw = ua;
    let browser: string | null = null;
    let os: string | null = null;
    let device: DeviceType = 'desktop';

    // Browser detection order matters
    const browserRegexes: { name: string; regex: RegExp }[] = [
        { name: 'Edge', regex: /Edg\/(\d+[\.\d+]*)/ },
        { name: 'Opera', regex: /OPR\/(\d+[\.\d+]*)/ },
        { name: 'Chrome', regex: /Chrome\/(\d+[\.\d+]*)/ },
        { name: 'Firefox', regex: /Firefox\/(\d+[\.\d+]*)/ },
        { name: 'Safari', regex: /Version\/(\d+[\.\d+]*) Safari/ },
    ];
    for (const b of browserRegexes) {
        const m = ua.match(b.regex);
        if (m) {
            browser = `${b.name} ${m[1]}`;
            break;
        }
    }

    // OS detection
    if (/Windows NT 10\.0/.test(ua)) os = 'Windows 10/11';
    else if (/Windows NT 6\.3/.test(ua)) os = 'Windows 8.1';
    else if (/Windows NT 6\.2/.test(ua)) os = 'Windows 8';
    else if (/Windows NT 6\.1/.test(ua)) os = 'Windows 7';
    else if (/Mac OS X (\d+[._]\d+)/.test(ua)) {
        os = 'macOS ' + ua.match(/Mac OS X (\d+[._]\d+)/)![1].replace(/_/g, '.');
    } else if (/iPhone OS (\d+[._]\d+)/.test(ua)) {
        os = 'iOS ' + ua.match(/iPhone OS (\d+[._]\d+)/)![1].replace(/_/g, '.');
    } else if (/iPad; CPU OS (\d+[._]\d+)/.test(ua)) {
        os = 'iPadOS ' + ua.match(/iPad; CPU OS (\d+[._]\d+)/)![1].replace(/_/g, '.');
    } else if (/Android (\d+[\.\d]*)/.test(ua)) {
        os = 'Android ' + ua.match(/Android (\d+[\.\d]*)/)![1];
    } else if (/Linux/.test(ua)) os = 'Linux';

    // Device type
    if (/Mobile|iPhone|Android.*Mobile/.test(ua)) device = 'mobile';
    else if (/Tablet|iPad|Android(?!.*Mobile)/.test(ua)) device = 'tablet';

    return { browser, os, device, raw };
}

const parsedLeadUA = computed(() => parseUserAgent(props.lead?.user_agent));

const deviceLabels = computed<Record<DeviceType, string>>(() => ({
    desktop: t('agents.playground.devices.desktop'),
    mobile: t('agents.playground.devices.mobile'),
    tablet: t('agents.playground.devices.tablet'),
}));

// Display texts for the technical section; null when there is no user agent.
const leadClient = computed(() => {
    const parsed = parsedLeadUA.value;
    if (!parsed) return null;
    return {
        browser: parsed.browser ?? t('shared.unknown'),
        os: parsed.os ?? t('shared.unknown'),
        device: deviceLabels.value[parsed.device],
        raw: parsed.raw,
    };
});

// Custom form values are mostly strings; numbers and booleans show as text.
const fieldText = (value: CustomFieldValue): string | undefined => (value === null ? undefined : String(value));
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex w-full flex-col overflow-y-auto p-0 sm:max-w-xl">
            <div
                class="from-background/60 to-background supports-[backdrop-filter]:bg-background/40 border-b bg-gradient-to-b px-6 pt-6 pb-4 backdrop-blur"
            >
                <SheetHeader class="items-start text-start">
                    <SheetTitle class="flex items-center gap-2 text-base font-semibold">
                        <span>{{ t('agents.leads.details_sheet.title') }}</span>
                    </SheetTitle>
                    <SheetDescription v-if="lead" class="mt-1 text-xs">
                        {{ t('agents.leads.details_sheet.created', { date: formatDateTime(lead.created_at) || '—' }) }}
                    </SheetDescription>
                    <SheetDescription v-else class="text-xs">{{ t('agents.leads.details_sheet.no_data') }}</SheetDescription>
                </SheetHeader>
            </div>

            <div v-if="lead" class="flex-1 space-y-8 overflow-y-auto px-6 py-6">
                <div class="flex items-start gap-4">
                    <div
                        class="bg-primary/10 text-primary relative flex h-14 w-14 items-center justify-center rounded-full text-sm font-semibold select-none"
                    >
                        {{ leadInitials }}
                    </div>
                    <div class="flex-1 space-y-1">
                        <h2 class="text-base leading-tight font-semibold">{{ lead.name || t('agents.leads.details_sheet.unnamed') }}</h2>
                        <div class="text-muted-foreground space-y-1 text-xs">
                            <div v-if="lead.email" class="flex items-center gap-1">
                                <Mail class="h-3.5 w-3.5" />
                                <span>{{ lead.email }}</span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="copyEmail"
                                    class="hover:bg-muted ms-1 h-6 w-6 p-0"
                                    :title="copiedEmail ? t('agents.leads.details_sheet.copied') : t('agents.leads.details_sheet.copy_email')"
                                    :aria-label="t('agents.leads.details_sheet.copy_email')"
                                >
                                    <Check v-if="copiedEmail" class="h-3 w-3 text-green-600" />
                                    <Copy v-else class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="lead.phone" class="flex items-center gap-1">
                                <Phone class="h-3.5 w-3.5" />
                                <span>{{ lead.phone }}</span>
                            </div>
                            <div v-if="lead.job_title" class="flex items-center gap-1">
                                <Briefcase class="h-3.5 w-3.5" />
                                <span>{{ lead.job_title }}</span>
                            </div>
                            <div v-if="lead.company" class="flex items-center gap-1">
                                <Building2 class="h-3.5 w-3.5" />
                                <span>{{ lead.company }}</span>
                            </div>
                            <div v-if="lead.location" class="flex items-center gap-1">
                                <MapPin class="h-3.5 w-3.5" />
                                <span>{{ lead.location }}</span>
                            </div>
                            <div v-if="lead.message" class="flex items-start gap-1">
                                <MessageSquare class="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                                <span class="leading-relaxed">{{ lead.message }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="lead.custom_fields && Object.keys(lead.custom_fields).length" class="space-y-3">
                    <h3 class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        {{ t('agents.leads.details_sheet.custom_fields') }}
                    </h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div
                            v-for="(val, key) in lead.custom_fields"
                            :key="key"
                            class="group bg-card/50 hover:bg-card rounded-md border p-2 transition-colors"
                        >
                            <div class="text-muted-foreground mb-1 text-[10px] font-medium tracking-wide uppercase">{{ key }}</div>
                            <div class="truncate text-xs font-medium" :title="fieldText(val)">{{ val }}</div>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h3 class="text-muted-foreground text-xs font-medium tracking-wide uppercase">{{ t('agents.leads.details_sheet.technical') }}</h3>
                    <div class="grid grid-cols-1 gap-3 text-[11px] sm:grid-cols-2">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.ip_address') }}</span>
                            <span class="font-mono text-xs">{{ lead.ip_address || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.locale') }}</span>
                            <span class="font-mono text-xs">{{ lead.locale || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.referrer_url') }}</span>
                            <span class="truncate font-mono text-xs">{{ lead.referrer_url || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.page_url') }}</span>
                            <div v-if="lead.page_url" class="inline-flex items-center gap-1">
                                <a
                                    :href="lead.page_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary inline-flex items-center gap-1 truncate font-mono text-xs hover:underline"
                                    :title="lead.page_url"
                                >
                                    {{ lead.page_url }}
                                    <ExternalLink class="h-3 w-3 flex-shrink-0" />
                                </a>
                            </div>
                            <span v-else class="font-mono text-xs">—</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.browser') }}</span>
                            <span class="font-mono text-xs">{{ leadClient?.browser || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.os') }}</span>
                            <span class="font-mono text-xs">{{ leadClient?.os || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.device') }}</span>
                            <span class="font-mono text-xs">{{ leadClient?.device || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5 sm:col-span-2">
                            <span class="text-muted-foreground font-medium">{{ t('agents.leads.details_sheet.user_agent') }}</span>
                            <span class="max-h-20 overflow-y-auto pe-1 font-mono text-[10px] leading-relaxed break-all">{{
                                leadClient?.raw || '—'
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-muted-foreground flex flex-1 items-center justify-center p-10 text-sm">
                {{ t('agents.leads.details_sheet.no_data') }}
            </div>
            <div class="bg-background/80 flex items-center justify-end gap-2 border-t px-6 py-4 backdrop-blur">
                <SheetClose as-child>
                    <Button variant="outline" size="sm">{{ t('agents.leads.details_sheet.close') }}</Button>
                </SheetClose>
            </div>
        </SheetContent>
    </Sheet>
</template>
