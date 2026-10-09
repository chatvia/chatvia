<script setup lang="ts">
// "Manage countries" dialog: a searchable, toggleable country list with the
// current picks as removable chips. It edits a draft taken each time the
// dialog opens and warns live when the draft would lock the viewer out.
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Check, Search, ShieldAlert, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCountryNames, type Country } from './countries';

const props = defineProps<{
    countries: Country[];
    /** Whether a rule with these countries would block the viewer's own country. */
    wouldLockOut: (countries: string[]) => boolean;
    lockoutMessage: string;
}>();

const open = defineModel<boolean>('open', { required: true });
const selected = defineModel<string[]>({ required: true });

const { t } = useI18n();
const { countryName, flagUrl } = useCountryNames(() => props.countries);

const draft = ref<string[]>([]);
const search = ref('');

watch(open, (isOpen) => {
    if (!isOpen) return;
    draft.value = [...selected.value];
    search.value = '';
});

// Same lockout check against the in-progress draft, so the warning reacts as
// the viewer toggles countries — before they commit with Done.
const lockoutDraft = computed(() => props.wouldLockOut(draft.value));

// Type-to-filter the full country list by localized name or ISO code.
const filteredCountries = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.countries;
    return props.countries.filter((c) => c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q));
});

const isSelected = (code: string): boolean => draft.value.includes(code);

const toggle = (code: string) => {
    const index = draft.value.indexOf(code);
    if (index === -1) draft.value.push(code);
    else draft.value.splice(index, 1);
};

const apply = () => {
    selected.value = [...new Set(draft.value.filter(Boolean))];
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('workspace.security.geo.dialog_title') }}</DialogTitle>
                <DialogDescription>{{ t('workspace.security.geo.dialog_description') }}</DialogDescription>
            </DialogHeader>

            <div class="relative">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="search" :placeholder="t('workspace.security.geo.select_placeholder')" class="ps-9" />
            </div>

            <!-- Live self-lockout warning as the draft changes -->
            <Alert v-if="lockoutDraft" variant="destructive" class="border-destructive/30 bg-destructive/5">
                <ShieldAlert />
                <AlertDescription>{{ lockoutMessage }}</AlertDescription>
            </Alert>

            <!-- Selected countries — removable chips -->
            <div v-if="draft.length" class="flex flex-wrap gap-1.5">
                <Badge v-for="c in draft" :key="c" variant="secondary" class="gap-1 py-1 font-normal">
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
                        @click="toggle(c)"
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
                        :aria-pressed="isSelected(c.code)"
                        @click="toggle(c.code)"
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
                        <Check v-if="isSelected(c.code)" class="text-primary h-4 w-4 shrink-0" />
                    </button>
                    <p v-if="!filteredCountries.length" class="text-muted-foreground px-2 py-6 text-center text-sm">
                        {{ t('workspace.security.geo.no_matches') }}
                    </p>
                </div>
            </ScrollArea>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('workspace.security.cancel') }}
                </Button>
                <Button type="button" @click="apply">
                    <Check class="me-2 h-4 w-4" />
                    {{ t('workspace.security.done') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
