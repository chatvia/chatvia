<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { useBrand } from '@/composables/useBrand';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { MessagesSquare, Play, Plus, Wand2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * First-run welcome popup. Greets a brand-new workspace owner, explains what an
 * agent is, and sends them straight into creating their first one. Creating —
 * or dismissing via the close button — stamps `users.onboarded_at`, so the
 * popup never returns.
 */
const emit = defineEmits<{ 'start-create': [] }>();

const { t } = useI18n();
const page = usePage<SharedData>();
const { brand } = useBrand();

// Dismissed locally for this session — covers the brief window before the
// shared `auth.user` prop refreshes with the persisted timestamp.
const dismissed = ref(false);
const persisted = ref(false);

// Intro video (embed URL from `brand.onboarding_video_url`) — loaded only once
// the owner hits play, and left out entirely when no URL is set. Bound via :src
// so the `&` in the query string isn't parsed as HTML entities.
const playing = ref(false);
const videoUrl = computed(() => brand.value.onboarding_video_url);

const open = computed<boolean>({
    get: () => {
        if (dismissed.value) return false;

        const user = page.props.auth.user;

        // Only the account owner (who can actually create agents) is onboarded;
        // teammates and chatters never see the first-run popup.
        return user.type === 'workspace' && !user.onboarded_at;
    },
    set: (value) => {
        // Closing via the X, Esc, or overlay still marks the user onboarded.
        if (!value) dismiss();
    },
});

function persistOnboarded(): void {
    if (persisted.value) return;
    persisted.value = true;

    router.post(route('onboarding.complete'), {}, { preserveScroll: true, preserveState: true });
}

function dismiss(): void {
    dismissed.value = true;
    persistOnboarded();
}

function createAgent(): void {
    dismiss();
    emit('start-create');
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => (open = value)">
        <DialogContent class="max-h-[90dvh] gap-0 overflow-y-auto p-0">
            <!-- Header -->
            <div class="relative flex flex-col items-center gap-3 px-6 pt-9 pb-1 text-center">
                <div class="from-primary/10 pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b to-transparent"></div>
                <div class="ring-border/70 bg-background relative flex h-16 w-16 items-center justify-center rounded-2xl shadow-sm ring-1">
                    <img src="/images/logo.svg" :alt="brand.name" class="h-10 w-10" />
                </div>
                <div class="relative space-y-1.5">
                    <DialogTitle class="text-2xl font-semibold tracking-tight">{{ t('onboarding.welcome.title') }}</DialogTitle>
                    <DialogDescription class="text-muted-foreground mx-auto max-w-sm text-base">
                        {{ t('onboarding.welcome.intro') }}
                    </DialogDescription>
                </div>
            </div>

            <!-- Body -->
            <div class="space-y-5 px-6 pt-4 pb-6">
                <!-- Intro video — poster + play button until the owner hits play, then the embed loads inline -->
                <div v-if="videoUrl" class="bg-muted ring-border/60 relative aspect-video w-full overflow-hidden rounded-xl ring-1">
                    <button
                        v-if="!playing"
                        type="button"
                        class="group absolute inset-0 flex cursor-pointer items-center justify-center"
                        :aria-label="t('onboarding.welcome.video_caption')"
                        @click="playing = true"
                    >
                        <img src="/images/main.png" alt="" class="absolute inset-0 h-full w-full object-cover object-top" />
                        <div class="absolute inset-0 bg-slate-900/30 transition-colors group-hover:bg-slate-900/40"></div>
                        <div class="relative z-10 flex flex-col items-center gap-2.5">
                            <div
                                class="bg-background/95 ring-border/60 flex h-14 w-14 items-center justify-center rounded-full shadow-lg ring-1 transition-transform duration-200 group-hover:scale-105"
                            >
                                <Play class="text-primary ms-0.5 h-6 w-6 fill-current" />
                            </div>
                            <span class="text-xs font-medium text-white drop-shadow">{{ t('onboarding.welcome.video_caption') }}</span>
                        </div>
                    </button>

                    <iframe
                        v-else
                        :src="videoUrl"
                        loading="lazy"
                        class="absolute inset-0 h-full w-full border-0"
                        allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowfullscreen
                    ></iframe>
                </div>

                <!-- What / How -->
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="bg-primary/10 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                            <MessagesSquare class="text-primary h-4 w-4" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold">{{ t('onboarding.welcome.what_title') }}</p>
                            <p class="text-muted-foreground text-sm leading-relaxed">{{ t('onboarding.welcome.what_body') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="bg-primary/10 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                            <Wand2 class="text-primary h-4 w-4" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold">{{ t('onboarding.welcome.how_title') }}</p>
                            <p class="text-muted-foreground text-sm leading-relaxed">{{ t('onboarding.welcome.how_body') }}</p>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <Button size="lg" class="w-full gap-2" @click="createAgent">
                    <Plus class="h-4 w-4" />
                    {{ t('onboarding.welcome.start') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
