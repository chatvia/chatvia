<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { LOCALES, normalizeLocale, persistLocale, setI18nLocale } from '@/i18n';
import { Check, Languages } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

// Optional allow-list of language codes (the agent's Setup → Languages
// selection). Empty/undefined ⇒ every platform locale (no restriction).
const props = defineProps<{ codes?: string[] }>();

const activeLocale = computed(() => normalizeLocale(locale.value));

const items = computed(() => {
    const allow = props.codes ?? [];
    return allow.length ? LOCALES.filter((l) => allow.includes(l.code)) : LOCALES;
});

// Standalone switcher for surfaces without a user menu (auth pages, etc.).
// Persists the choice client-side so it survives navigation + carries into the
// session at login (the signed-in user menu additionally saves it server-side).
const change = (code: string): void => {
    if (code === activeLocale.value) return;
    persistLocale(code);
    void setI18nLocale(code);
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9">
                <Languages class="h-4 w-4" />
                <span class="sr-only">{{ t('nav.language') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="max-h-80 overflow-y-auto">
            <DropdownMenuItem v-for="item in items" :key="item.code" class="cursor-pointer" @select="change(item.code)">
                <img :src="`/images/flags/${item.flag}.svg`" :alt="item.nativeName" class="me-2 h-4 w-5 shrink-0 rounded-[2px] object-cover" />
                <span class="flex-1">{{ item.nativeName }}</span>
                <Check v-if="item.code === activeLocale" class="ms-2 h-4 w-4" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
