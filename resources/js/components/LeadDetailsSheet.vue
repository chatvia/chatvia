<script lang="ts" setup>
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetClose } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Mail, Phone, Building2, Copy, Check, MapPin, Briefcase, ExternalLink, MessageSquare } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

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
    custom_fields?: Record<string, any>;
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
const copiedEmail = ref(false);

const copyEmail = async () => {
    if (!props.lead?.email) return;

    try {
        await navigator.clipboard.writeText(props.lead.email);
        copiedEmail.value = true;
        toast.success('Email copied to clipboard');

        // Reset the copied state after 2 seconds
        setTimeout(() => {
            copiedEmail.value = false;
        }, 2000);
    } catch (error) {
        toast.error('Failed to copy email');
    }
};

// Lead presentation helpers (for avatar & status)
const leadInitials = computed(() => {
    if (!props.lead) return '';
    if (props.lead.name) return props.lead.name.split(' ').map((p: string) => p[0]).join('').slice(0, 2).toUpperCase();
    if (props.lead.email) return props.lead.email[0].toUpperCase();
    return '?';
});

const statusStyles: Record<string, string> = {
    new: 'bg-blue-500/10 text-blue-600 border-blue-200',
    contacted: 'bg-yellow-500/10 text-yellow-600 border-yellow-200',
    qualified: 'bg-green-500/10 text-green-600 border-green-200',
    converted: 'bg-purple-500/10 text-purple-600 border-purple-200'
};

const leadStatusClass = computed(() => {
    if (!props.lead || !props.lead.status) return statusStyles.new;
    return statusStyles[props.lead.status] || statusStyles.new;
});

// --- User Agent Parsing (lightweight) ---
interface ParsedUA { browser: string; os: string; device: string; raw: string; }
function parseUserAgent(ua?: string | null): ParsedUA | null {
    if (!ua) return null;
    const raw = ua;
    let browser = 'Unknown';
    let os = 'Unknown';
    let device = 'Desktop';

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
        if (m) { browser = `${b.name} ${m[1]}`; break; }
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
    if (/Mobile|iPhone|Android.*Mobile/.test(ua)) device = 'Mobile';
    else if (/Tablet|iPad|Android(?!.*Mobile)/.test(ua)) device = 'Tablet';

    return { browser, os, device, raw };
}

const parsedLeadUA = computed(() => parseUserAgent(props.lead?.user_agent));

function formatDate(dt: string | null | undefined) {
    if (!dt) return '—';
    try {
        return new Intl.DateTimeFormat('en-US', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(dt));
    } catch {
        return dt;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="w-full sm:max-w-xl overflow-y-auto p-0 flex flex-col">
            <div class="px-6 pt-6 pb-4 border-b bg-gradient-to-b from-background/60 to-background backdrop-blur supports-[backdrop-filter]:bg-background/40">
                <SheetHeader class="items-start text-left">
                    <SheetTitle class="flex items-center gap-2 text-base font-semibold">
                        <span>Lead Details</span>

                    </SheetTitle>
                    <SheetDescription v-if="lead" class="text-xs mt-1">
                        Created {{ formatDate(lead.created_at) }}
                    </SheetDescription>
                    <SheetDescription v-else class="text-xs">No lead data.</SheetDescription>
                </SheetHeader>
            </div>

            <div v-if="lead" class="flex-1 overflow-y-auto px-6 py-6 space-y-8">
                <div class="flex items-start gap-4">
                    <div class="relative h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center text-sm font-semibold text-primary select-none">
                        {{ leadInitials }}
                    </div>
                    <div class="flex-1 space-y-1">
                        <h2 class="text-base font-semibold leading-tight">{{ lead.name || 'Unnamed Lead' }}</h2>
                        <div class="space-y-1 text-xs text-muted-foreground">
                            <div v-if="lead.email" class="flex items-center gap-1">
                                <Mail class="h-3.5 w-3.5" />
                                <span>{{ lead.email }}</span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="copyEmail"
                                    class="h-6 w-6 p-0 ml-1 hover:bg-muted"
                                    :title="copiedEmail ? 'Copied!' : 'Copy email'"
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
                                <MessageSquare class="h-3.5 w-3.5 mt-0.5 flex-shrink-0" />
                                <span class="leading-relaxed">{{ lead.message }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="lead.custom_fields && Object.keys(lead.custom_fields).length" class="space-y-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Custom Fields</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div v-for="(val, key) in lead.custom_fields" :key="key" class="group rounded-md border bg-card/50 hover:bg-card transition-colors p-2">
                            <div class="text-[10px] font-medium tracking-wide uppercase text-muted-foreground mb-1">{{ key }}</div>
                            <div class="text-xs font-medium truncate" :title="val">{{ val }}</div>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Technical</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]">
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">IP Address</span>
                            <span class="font-mono text-xs">{{ lead.ip_address || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">Locale</span>
                            <span class="font-mono text-xs">{{ lead.locale || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">Referrer URL</span>
                            <span class="font-mono text-xs truncate">{{ lead.referrer_url || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">Page URL</span>
                            <div v-if="lead.page_url" class="inline-flex items-center gap-1">
                                <a 
                                    :href="lead.page_url" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="font-mono text-xs text-primary hover:underline truncate inline-flex items-center gap-1"
                                    :title="lead.page_url"
                                >
                                    {{ lead.page_url }}
                                    <ExternalLink class="h-3 w-3 flex-shrink-0" />
                                </a>
                            </div>
                            <span v-else class="font-mono text-xs">—</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">Browser</span>
                            <span class="font-mono text-xs">{{ parsedLeadUA?.browser || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">OS</span>
                            <span class="font-mono text-xs">{{ parsedLeadUA?.os || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium text-muted-foreground">Device</span>
                            <span class="font-mono text-xs">{{ parsedLeadUA?.device || '—' }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5 sm:col-span-2">
                            <span class="font-medium text-muted-foreground">User Agent (Raw)</span>
                            <span class="font-mono text-[10px] break-all leading-relaxed max-h-20 overflow-y-auto pr-1">{{ parsedLeadUA?.raw || '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex-1 flex items-center justify-center p-10 text-sm text-muted-foreground">No lead data.</div>
            <div class="px-6 py-4 border-t bg-background/80 backdrop-blur flex items-center justify-end gap-2">
                <SheetClose as-child>
                    <Button variant="outline" size="sm">Close</Button>
                </SheetClose>
            </div>
        </SheetContent>
    </Sheet>
</template>
