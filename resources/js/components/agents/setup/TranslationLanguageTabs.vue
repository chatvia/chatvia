<script setup lang="ts">
/**
 * The flag tabs above the Setup text editors (Info, Starters, Bubbles). The
 * first tab ("default") edits the base text; each flag tab edits that
 * language's manual translation and shows a dot when it has one.
 */
import { LOCALES } from '@/i18n';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        // Allowed language codes (Setup → Languages); all languages when empty.
        languages?: Record<string, string>;
        hasOverride: (code: string) => boolean;
    }>(),
    { languages: () => ({}) },
);

/** 'default' for the base text, otherwise a locale code. */
const selected = defineModel<string>({ required: true });

const { t } = useI18n();

const tabs = computed(() => {
    const allowed = Object.keys(props.languages).filter((code) => code !== 'auto');
    return allowed.length ? LOCALES.filter((l) => allowed.includes(l.code)) : LOCALES;
});
</script>

<template>
    <div class="border-b">
        <div class="-mb-px flex items-center gap-1 overflow-x-auto pb-px">
            <button
                type="button"
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="selected === 'default' ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'"
                :title="t('agents.setup.sections.appearance_translations_default_tip')"
                @click="selected = 'default'"
            >
                <Globe class="h-4 w-4" />
                {{ t('agents.setup.sections.appearance_translations_default') }}
            </button>
            <button
                v-for="tab in tabs"
                :key="tab.code"
                type="button"
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors"
                :class="selected === tab.code ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent'"
                :title="tab.nativeName"
                @click="selected = tab.code"
            >
                <img :src="`/images/flags/${tab.flag}.svg`" :alt="tab.nativeName" class="h-4 w-5 rounded-[2px] object-cover" />
                <span class="uppercase">{{ tab.code }}</span>
                <span v-if="hasOverride(tab.code)" class="bg-primary h-1.5 w-1.5 rounded-full"></span>
            </button>
        </div>
    </div>
</template>
