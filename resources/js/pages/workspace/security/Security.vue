<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { useFeature } from '@/composables/useFeature';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Globe, History, LoaderCircle, Lock, Network, Plus, Save, Search, ShieldAlert, Sparkles, Trash2, Webhook, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Policy {
    ip_allowlist_mode: string;
    ip_allowlist: string[];
    geo_mode: string;
    geo_countries: string[];
    retention_conversations_days: number | null;
    retention_audit_days: number | null;
    webhook_url: string | null;
    webhook_enabled: boolean;
}

interface Limits {
    conversations_max: number;
    conversations_floor: number;
    audit_default: number;
    audit_floor: number;
}

interface Props {
    entitled: boolean;
    siem: boolean;
    policy: Policy;
    countries: { code: string; name: string }[];
    limits: Limits | null;
    // The viewer's own country (lowercase ISO code) for the live lockout warning;
    // null when it can't be resolved (private IP / provider miss).
    current_country: string | null;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasFeature } = useFeature();
const { hasPermission } = useTeamPermissions();

const canManage = computed(() => hasPermission('account.security'));
// Server-resolved entitlement for THIS workspace; hasFeature mirrors it for the
// admin-bypass edge case but props.entitled is authoritative.
const entitled = computed(() => props.entitled || hasFeature('security'));

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.security.breadcrumb'), href: '/my/workspace/security' }];

// ---- Form ------------------------------------------------------------------

const form = useForm({
    ip_allowlist_mode: props.policy.ip_allowlist_mode === 'off' ? 'off' : 'dashboard',
    ip_allowlist: [...(props.policy.ip_allowlist ?? [])],
    geo_mode: props.policy.geo_mode ?? 'off',
    geo_countries: [...(props.policy.geo_countries ?? [])],
    // Kept as strings for the number inputs; '' means "inherit the default".
    retention_conversations_days: props.policy.retention_conversations_days?.toString() ?? '',
    retention_audit_days: props.policy.retention_audit_days?.toString() ?? '',
    webhook_url: props.policy.webhook_url ?? '',
    webhook_secret: '', // write-only; blank leaves the stored secret untouched
    webhook_enabled: props.policy.webhook_enabled ?? false,
});

form.transform((d) => ({
    ip_allowlist_mode: d.ip_allowlist_mode,
    ip_allowlist: d.ip_allowlist.map((v) => v.trim()).filter(Boolean),
    geo_mode: d.geo_mode,
    geo_countries: d.geo_countries.map((v) => v.trim().toLowerCase()).filter(Boolean),
    retention_conversations_days: d.retention_conversations_days === '' ? null : Number(d.retention_conversations_days),
    retention_audit_days: d.retention_audit_days === '' ? null : Number(d.retention_audit_days),
    webhook_url: d.webhook_url,
    webhook_secret: d.webhook_secret,
    webhook_enabled: d.webhook_enabled,
}));

const networkEnabled = computed(() => form.ip_allowlist_mode !== 'off');
const geoEnabled = computed(() => form.geo_mode !== 'off');

// ---- Live self-lockout warning for the geo rule ----------------------------
// Mirrors the server gate: with network control + geo on, the viewer's own
// resolved country must satisfy the rule, or saving fences them out. An
// unresolved country (null) shows nothing — the gate fails open on it too.
const currentCountry = computed(() => props.current_country?.toLowerCase() ?? null);

const wouldGeoLockOut = (countries: string[]): boolean => {
    if (!networkEnabled.value || !geoEnabled.value || !currentCountry.value) return false;
    const listed = countries.some((c) => c.toLowerCase() === currentCountry.value);
    return form.geo_mode === 'allow' ? !listed : listed;
};

const geoLockout = computed(() => wouldGeoLockOut(form.geo_countries));

const toggleNetwork = () => {
    if (canManage.value) form.ip_allowlist_mode = networkEnabled.value ? 'off' : 'dashboard';
};
const toggleGeo = () => {
    if (canManage.value) form.geo_mode = geoEnabled.value ? 'off' : 'allow';
};
const toggleWebhook = () => {
    if (canManage.value) form.webhook_enabled = !form.webhook_enabled;
};

const save = () => {
    form.put(route('security.update'), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('workspace.security.saved')),
        onError: () => toast.error(t('workspace.security.save_error')),
    });
};

// ---- IP allowlist editor (badges + Manage dialog, draft commits on Done) ----

const ipOpen = ref(false);
const ipDraft = ref<string[]>([]);

const openIps = () => {
    ipDraft.value = form.ip_allowlist.length ? [...form.ip_allowlist] : [''];
    ipOpen.value = true;
};
const addIp = () => ipDraft.value.push('');
const removeIp = (index: number) => ipDraft.value.splice(index, 1);
const applyIps = () => {
    form.ip_allowlist = [...new Set(ipDraft.value.map((v) => v.trim()).filter(Boolean))];
    ipOpen.value = false;
};

// ---- Country editor (searchable list sourced from the countries table) ------

const countryMap = computed(() => new Map(props.countries.map((c) => [c.code, c.name])));
const countryName = (code: string): string => countryMap.value.get(code) ?? code.toUpperCase();
const flagUrl = (code: string): string => `/images/flags/${code.toLowerCase()}.svg`;

// Localized name of the viewer's own country + the matching lockout sentence,
// shared by the section warning and the in-dialog warning.
const currentCountryName = computed(() => (currentCountry.value ? countryName(currentCountry.value) : ''));
const geoLockoutMessage = computed(() =>
    form.geo_mode === 'allow'
        ? t('workspace.security.geo.lockout_allow', { country: currentCountryName.value })
        : t('workspace.security.geo.lockout_deny', { country: currentCountryName.value }),
);

const countriesOpen = ref(false);
const countriesDraft = ref<string[]>([]);
const countrySearch = ref('');

// Same check against the dialog's in-progress draft, so the warning reacts as
// the viewer toggles countries — before they commit with Done.
const geoLockoutDraft = computed(() => wouldGeoLockOut(countriesDraft.value));

// Type-to-filter the full country list by localized name or ISO code.
const filteredCountries = computed(() => {
    const q = countrySearch.value.trim().toLowerCase();
    if (!q) return props.countries;
    return props.countries.filter((c) => c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q));
});

const isCountrySelected = (code: string): boolean => countriesDraft.value.includes(code);

const openCountries = () => {
    countriesDraft.value = [...form.geo_countries];
    countrySearch.value = '';
    countriesOpen.value = true;
};
const toggleCountry = (code: string) => {
    const index = countriesDraft.value.indexOf(code);
    if (index === -1) countriesDraft.value.push(code);
    else countriesDraft.value.splice(index, 1);
};
const applyCountries = () => {
    form.geo_countries = [...new Set(countriesDraft.value.filter(Boolean))];
    countriesOpen.value = false;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.security.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full max-w-3xl space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Lock class="size-6" />
                        {{ t('workspace.security.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('workspace.security.description') }}</p>
                </div>

                <!-- Upsell teaser — non-Enterprise workspaces -->
                <Card v-if="!entitled">
                    <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
                        <div class="bg-primary/10 flex size-12 items-center justify-center rounded-full">
                            <Sparkles class="text-primary size-6" />
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold">{{ t('workspace.security.teaser.title') }}</h2>
                            <p class="text-muted-foreground mx-auto max-w-md text-sm">{{ t('workspace.security.teaser.description') }}</p>
                        </div>
                        <ul class="text-muted-foreground mx-auto grid max-w-md gap-2 text-start text-sm">
                            <li class="flex items-center gap-2"><Network class="size-4 shrink-0" /> {{ t('workspace.security.teaser.ip') }}</li>
                            <li class="flex items-center gap-2"><Globe class="size-4 shrink-0" /> {{ t('workspace.security.teaser.geo') }}</li>
                            <li class="flex items-center gap-2"><Lock class="size-4 shrink-0" /> {{ t('workspace.security.teaser.more') }}</li>
                        </ul>
                        <Button as-child>
                            <Link :href="route('billing.packages')">{{ t('workspace.security.teaser.cta') }}</Link>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Network access control -->
                <Card v-if="entitled" class="overflow-hidden py-0">
                    <CardContent class="p-0">
                        <!-- Capability header / master switch -->
                        <div class="flex items-start justify-between gap-4 p-5" :class="{ 'cursor-pointer': canManage }" @click="toggleNetwork">
                            <div class="flex items-start gap-3">
                                <div class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                                    <Network class="size-5" />
                                </div>
                                <div class="space-y-0.5">
                                    <p class="text-sm font-semibold">{{ t('workspace.security.network.title') }}</p>
                                    <p class="text-muted-foreground text-sm">{{ t('workspace.security.network.help') }}</p>
                                </div>
                            </div>
                            <Switch
                                :model-value="networkEnabled"
                                :disabled="!canManage"
                                :aria-label="t('workspace.security.network.title')"
                                class="pointer-events-none mt-0.5"
                            />
                        </div>

                        <!-- Nested controls -->
                        <div v-if="networkEnabled" class="space-y-4 border-t p-5">
                            <!-- Self-lockout guard (server-side validation error) -->
                            <Alert v-if="form.errors.ip_allowlist" variant="destructive" class="border-destructive/30 bg-destructive/5">
                                <ShieldAlert />
                                <AlertDescription>{{ form.errors.ip_allowlist }}</AlertDescription>
                            </Alert>

                            <!-- IP allowlist -->
                            <div class="bg-muted/30 space-y-3 rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="bg-background text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                        >
                                            <Network class="size-4" />
                                        </div>
                                        <div class="space-y-0.5">
                                            <p class="text-sm font-medium">{{ t('workspace.security.ip.title') }}</p>
                                            <p class="text-muted-foreground text-xs">{{ t('workspace.security.ip.help') }}</p>
                                        </div>
                                    </div>
                                    <Button v-if="canManage" type="button" variant="outline" size="sm" class="shrink-0" @click="openIps">
                                        {{ t('workspace.security.ip.manage') }}
                                    </Button>
                                </div>

                                <div class="ps-12">
                                    <div v-if="form.ip_allowlist.length" class="flex flex-wrap gap-2">
                                        <Badge v-for="ip in form.ip_allowlist" :key="ip" variant="secondary" class="font-mono font-normal">{{
                                            ip
                                        }}</Badge>
                                    </div>
                                    <div v-else class="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-sm">
                                        {{ t('workspace.security.ip.empty') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Country rules -->
                            <div class="bg-muted/30 rounded-lg border p-4">
                                <div class="flex items-start justify-between gap-3" :class="{ 'cursor-pointer': canManage }" @click="toggleGeo">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="bg-background text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg border"
                                        >
                                            <Globe class="size-4" />
                                        </div>
                                        <div class="space-y-0.5">
                                            <p class="text-sm font-medium">{{ t('workspace.security.geo.title') }}</p>
                                            <p class="text-muted-foreground text-xs">{{ t('workspace.security.geo.help') }}</p>
                                        </div>
                                    </div>
                                    <Switch
                                        :model-value="geoEnabled"
                                        :disabled="!canManage"
                                        :aria-label="t('workspace.security.geo.title')"
                                        class="pointer-events-none mt-0.5"
                                    />
                                </div>

                                <div v-if="geoEnabled" class="mt-3 space-y-3 ps-12">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <!-- Allow / Block segmented control -->
                                        <div class="border-input bg-background inline-flex rounded-lg border p-0.5">
                                            <button
                                                type="button"
                                                :disabled="!canManage"
                                                class="rounded-md px-3 py-1 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                                :class="
                                                    form.geo_mode === 'allow'
                                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                "
                                                @click="form.geo_mode = 'allow'"
                                            >
                                                {{ t('workspace.security.geo.allow') }}
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="!canManage"
                                                class="rounded-md px-3 py-1 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                                :class="
                                                    form.geo_mode === 'deny'
                                                        ? 'bg-primary text-primary-foreground shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                "
                                                @click="form.geo_mode = 'deny'"
                                            >
                                                {{ t('workspace.security.geo.deny') }}
                                            </button>
                                        </div>
                                        <Button v-if="canManage" type="button" variant="outline" size="sm" class="shrink-0" @click="openCountries">
                                            {{ t('workspace.security.geo.manage') }}
                                        </Button>
                                    </div>

                                    <div v-if="form.geo_countries.length" class="flex flex-wrap gap-2">
                                        <Badge v-for="c in form.geo_countries" :key="c" variant="secondary" class="gap-1.5 font-normal">
                                            <img
                                                :src="flagUrl(c)"
                                                :alt="countryName(c)"
                                                class="h-3 w-4 shrink-0 rounded-[2px] object-cover"
                                                onerror="this.style.display = 'none'"
                                            />
                                            {{ countryName(c) }}
                                        </Badge>
                                    </div>
                                    <div v-else class="text-muted-foreground rounded-md border border-dashed px-3 py-2 text-sm">
                                        {{ t('workspace.security.geo.empty') }}
                                    </div>

                                    <!-- Live self-lockout warning: this rule would block the viewer's own country -->
                                    <Alert v-if="geoLockout" variant="destructive" class="border-destructive/30 bg-destructive/5">
                                        <ShieldAlert />
                                        <AlertDescription>{{ geoLockoutMessage }}</AlertDescription>
                                    </Alert>

                                    <p v-if="form.errors.geo_countries" class="text-destructive text-xs">{{ form.errors.geo_countries }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Data retention -->
                <Card v-if="entitled" class="overflow-hidden py-0">
                    <CardContent class="p-0">
                        <div class="flex items-start gap-3 p-5">
                            <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                <History class="size-5" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-sm font-semibold">{{ t('workspace.security.retention.title') }}</p>
                                <p class="text-muted-foreground text-sm">{{ t('workspace.security.retention.help') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 border-t p-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="ret-conv">{{ t('workspace.security.retention.conversations') }}</Label>
                                <Input
                                    id="ret-conv"
                                    v-model="form.retention_conversations_days"
                                    type="number"
                                    :min="props.limits?.conversations_floor"
                                    :max="props.limits?.conversations_max"
                                    :disabled="!canManage"
                                    :placeholder="t('workspace.security.retention.plan_default', { days: props.limits?.conversations_max ?? 0 })"
                                />
                                <p class="text-muted-foreground text-xs">
                                    {{ t('workspace.security.retention.conversations_help', { max: props.limits?.conversations_max ?? 0 }) }}
                                </p>
                                <p v-if="form.errors.retention_conversations_days" class="text-destructive text-xs">
                                    {{ form.errors.retention_conversations_days }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="ret-audit">{{ t('workspace.security.retention.audit') }}</Label>
                                <Input
                                    id="ret-audit"
                                    v-model="form.retention_audit_days"
                                    type="number"
                                    :min="props.limits?.audit_floor"
                                    :max="props.limits?.audit_default"
                                    :disabled="!canManage"
                                    :placeholder="t('workspace.security.retention.plan_default', { days: props.limits?.audit_default ?? 0 })"
                                />
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        t('workspace.security.retention.audit_help', {
                                            default: props.limits?.audit_default ?? 0,
                                            floor: props.limits?.audit_floor ?? 0,
                                        })
                                    }}
                                </p>
                                <p v-if="form.errors.retention_audit_days" class="text-destructive text-xs">
                                    {{ form.errors.retention_audit_days }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- SIEM webhook — its own capability (Pro / Enterprise) -->
                <Card v-if="siem" class="overflow-hidden py-0">
                    <CardContent class="p-0">
                        <div class="flex items-start justify-between gap-4 p-5" :class="{ 'cursor-pointer': canManage }" @click="toggleWebhook">
                            <div class="flex items-start gap-3">
                                <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                                    <Webhook class="size-5" />
                                </div>
                                <div class="space-y-0.5">
                                    <p class="text-sm font-semibold">{{ t('workspace.security.siem.title') }}</p>
                                    <p class="text-muted-foreground text-sm">{{ t('workspace.security.siem.help') }}</p>
                                </div>
                            </div>
                            <Switch
                                :model-value="form.webhook_enabled"
                                :disabled="!canManage"
                                :aria-label="t('workspace.security.siem.title')"
                                class="pointer-events-none mt-0.5"
                            />
                        </div>

                        <div v-if="form.webhook_enabled" class="space-y-4 border-t p-5">
                            <div class="space-y-1.5">
                                <Label for="siem-url">{{ t('workspace.security.siem.url') }}</Label>
                                <div class="relative">
                                    <Webhook class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        id="siem-url"
                                        v-model="form.webhook_url"
                                        type="url"
                                        :disabled="!canManage"
                                        placeholder="https://siem.example.com/ingest"
                                        class="ps-9 font-mono"
                                    />
                                </div>
                                <p v-if="form.errors.webhook_url" class="text-destructive text-xs">{{ form.errors.webhook_url }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="siem-secret">{{ t('workspace.security.siem.secret') }}</Label>
                                <Input
                                    id="siem-secret"
                                    v-model="form.webhook_secret"
                                    type="password"
                                    autocomplete="new-password"
                                    :disabled="!canManage"
                                    :placeholder="t('workspace.security.siem.secret_placeholder')"
                                />
                                <p class="text-muted-foreground text-xs">{{ t('workspace.security.siem.secret_help') }}</p>
                                <p v-if="form.errors.webhook_secret" class="text-destructive text-xs">{{ form.errors.webhook_secret }}</p>
                            </div>

                            <p class="text-muted-foreground text-xs">{{ t('workspace.security.siem.signature_help') }}</p>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="entitled && canManage" class="flex justify-end">
                    <Button :disabled="form.processing" @click="save">
                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Save v-else class="me-2 h-4 w-4" />
                        {{ t('workspace.security.save') }}
                    </Button>
                </div>
            </div>
        </WorkspaceLayout>

        <!-- Manage allowed IPs -->
        <Dialog v-model:open="ipOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('workspace.security.ip.dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ t('workspace.security.ip.dialog_description') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <div v-for="(ip, index) in ipDraft" :key="index" class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Network class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                v-model="ipDraft[index]"
                                :placeholder="t('workspace.security.ip.placeholder')"
                                class="ps-9 font-mono"
                                @keydown.enter.prevent="addIp"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive shrink-0"
                            :aria-label="t('workspace.security.ip.remove')"
                            @click="removeIp(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                    <p v-if="!ipDraft.length" class="text-muted-foreground text-sm">{{ t('workspace.security.ip.empty_draft') }}</p>
                </div>

                <Button type="button" variant="outline" size="sm" class="w-fit" @click="addIp">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('workspace.security.ip.add') }}
                </Button>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="ipOpen = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('workspace.security.cancel') }}
                    </Button>
                    <Button type="button" @click="applyIps">
                        <Check class="me-2 h-4 w-4" />
                        {{ t('workspace.security.done') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Manage countries -->
        <Dialog v-model:open="countriesOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('workspace.security.geo.dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ t('workspace.security.geo.dialog_description') }}</DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="countrySearch" :placeholder="t('workspace.security.geo.select_placeholder')" class="ps-9" />
                </div>

                <!-- Live self-lockout warning as the draft changes -->
                <Alert v-if="geoLockoutDraft" variant="destructive" class="border-destructive/30 bg-destructive/5">
                    <ShieldAlert />
                    <AlertDescription>{{ geoLockoutMessage }}</AlertDescription>
                </Alert>

                <!-- Selected countries — removable chips -->
                <div v-if="countriesDraft.length" class="flex flex-wrap gap-1.5">
                    <Badge v-for="c in countriesDraft" :key="c" variant="secondary" class="gap-1 py-1 font-normal">
                        <img
                            :src="flagUrl(c)"
                            :alt="countryName(c)"
                            class="h-3 w-4 shrink-0 rounded-[2px] object-cover"
                            onerror="this.style.display = 'none'"
                        />
                        {{ countryName(c) }}
                        <button
                            type="button"
                            class="hover:text-destructive ms-0.5 -me-0.5 rounded-sm"
                            :aria-label="t('workspace.security.geo.remove')"
                            @click="toggleCountry(c)"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </Badge>
                </div>

                <!-- Filtered, toggleable list -->
                <ScrollArea class="h-64 rounded-md border">
                    <div class="p-1">
                        <button
                            v-for="c in filteredCountries"
                            :key="c.code"
                            type="button"
                            class="hover:bg-accent flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-start text-sm transition-colors"
                            :aria-pressed="isCountrySelected(c.code)"
                            @click="toggleCountry(c.code)"
                        >
                            <span class="flex items-center gap-2">
                                <img
                                    :src="flagUrl(c.code)"
                                    :alt="c.name"
                                    class="h-4 w-5 shrink-0 rounded-[2px] object-cover"
                                    onerror="this.style.display = 'none'"
                                />
                                {{ c.name }}
                            </span>
                            <Check v-if="isCountrySelected(c.code)" class="text-primary h-4 w-4 shrink-0" />
                        </button>
                        <p v-if="!filteredCountries.length" class="text-muted-foreground px-2 py-6 text-center text-sm">
                            {{ t('workspace.security.geo.no_matches') }}
                        </p>
                    </div>
                </ScrollArea>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="countriesOpen = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('workspace.security.cancel') }}
                    </Button>
                    <Button type="button" @click="applyCountries">
                        <Check class="me-2 h-4 w-4" />
                        {{ t('workspace.security.done') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
