<script setup lang="ts">
// Network access control card: a master switch that limits dashboard access
// to an IP allowlist, plus an optional allow/block rule by country. It warns
// live when the country rule would block the viewer's own country.
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Globe, Network, ShieldAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCountryNames, type Country } from './countries';
import CountryRulesDialog from './CountryRulesDialog.vue';
import IpAllowlistDialog from './IpAllowlistDialog.vue';

const props = defineProps<{
    canManage: boolean;
    countries: Country[];
    // The viewer's own country (ISO code); null when it can't be resolved.
    currentCountry: string | null;
    errors: { ip_allowlist?: string; geo_countries?: string };
}>();

const ipAllowlistMode = defineModel<string>('ipAllowlistMode', { required: true });
const ipAllowlist = defineModel<string[]>('ipAllowlist', { required: true });
const geoMode = defineModel<string>('geoMode', { required: true });
const geoCountries = defineModel<string[]>('geoCountries', { required: true });

const { t } = useI18n();
const { countryName, flagUrl } = useCountryNames(() => props.countries);

const networkEnabled = computed(() => ipAllowlistMode.value !== 'off');
const geoEnabled = computed(() => geoMode.value !== 'off');

const toggleNetwork = () => {
    if (props.canManage) ipAllowlistMode.value = networkEnabled.value ? 'off' : 'dashboard';
};
const toggleGeo = () => {
    if (props.canManage) geoMode.value = geoEnabled.value ? 'off' : 'allow';
};

// ---- Live self-lockout warning for the geo rule ----------------------------
// Mirrors the server gate: with network control + geo on, the viewer's own
// resolved country must satisfy the rule, or saving fences them out. An
// unresolved country (null) shows nothing — the gate fails open on it too.
const ownCountry = computed(() => props.currentCountry?.toLowerCase() ?? null);

const wouldGeoLockOut = (countries: string[]): boolean => {
    if (!networkEnabled.value || !geoEnabled.value || !ownCountry.value) return false;
    const listed = countries.some((c) => c.toLowerCase() === ownCountry.value);
    return geoMode.value === 'allow' ? !listed : listed;
};

const geoLockout = computed(() => wouldGeoLockOut(geoCountries.value));

// Localized name of the viewer's own country + the matching lockout sentence,
// shared by the section warning and the in-dialog warning.
const ownCountryName = computed(() => (ownCountry.value ? countryName(ownCountry.value) : ''));
const geoLockoutMessage = computed(() =>
    geoMode.value === 'allow'
        ? t('workspace.security.geo.lockout_allow', { country: ownCountryName.value })
        : t('workspace.security.geo.lockout_deny', { country: ownCountryName.value }),
);

const ipOpen = ref(false);
const countriesOpen = ref(false);
</script>

<template>
    <Card class="overflow-hidden py-0">
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
                <Alert v-if="errors.ip_allowlist" variant="destructive" class="border-destructive/30 bg-destructive/5">
                    <ShieldAlert />
                    <AlertDescription>{{ errors.ip_allowlist }}</AlertDescription>
                </Alert>

                <!-- IP allowlist -->
                <div class="bg-muted/30 space-y-3 rounded-lg border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="bg-background text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg border">
                                <Network class="size-4" />
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium">{{ t('workspace.security.ip.title') }}</p>
                                <p class="text-muted-foreground text-xs">{{ t('workspace.security.ip.help') }}</p>
                            </div>
                        </div>
                        <Button v-if="canManage" type="button" variant="outline" size="sm" class="shrink-0" @click="ipOpen = true">
                            {{ t('workspace.security.ip.manage') }}
                        </Button>
                    </div>

                    <div class="ps-12">
                        <div v-if="ipAllowlist.length" class="flex flex-wrap gap-2">
                            <Badge v-for="ip in ipAllowlist" :key="ip" variant="secondary" class="font-mono font-normal">{{ ip }}</Badge>
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
                            <div class="bg-background text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg border">
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
                                        geoMode === 'allow'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="geoMode = 'allow'"
                                >
                                    {{ t('workspace.security.geo.allow') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="!canManage"
                                    class="rounded-md px-3 py-1 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="
                                        geoMode === 'deny'
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="geoMode = 'deny'"
                                >
                                    {{ t('workspace.security.geo.deny') }}
                                </button>
                            </div>
                            <Button v-if="canManage" type="button" variant="outline" size="sm" class="shrink-0" @click="countriesOpen = true">
                                {{ t('workspace.security.geo.manage') }}
                            </Button>
                        </div>

                        <div v-if="geoCountries.length" class="flex flex-wrap gap-2">
                            <Badge v-for="c in geoCountries" :key="c" variant="secondary" class="gap-1.5 font-normal">
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

                        <p v-if="errors.geo_countries" class="text-destructive text-xs">{{ errors.geo_countries }}</p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Manage allowed IPs -->
    <IpAllowlistDialog v-model:open="ipOpen" v-model="ipAllowlist" />

    <!-- Manage countries -->
    <CountryRulesDialog
        v-model:open="countriesOpen"
        v-model="geoCountries"
        :countries="countries"
        :would-lock-out="wouldGeoLockOut"
        :lockout-message="geoLockoutMessage"
    />
</template>
