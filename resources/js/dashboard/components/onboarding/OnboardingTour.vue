<script setup lang="ts">
/**
 * One part of the dashboard tour on top of v-onboarding: dims the page around
 * each stop and shows our own step card (title, text, "2 of 6", Back / Next,
 * close). Stops whose `[data-tour]` element is missing or hidden (a narrow
 * screen, a permission) are left out when it starts. Arrow keys move between
 * steps and Esc ends it. Emits `finish` after the last step and `skip` when
 * the person closes it; `stop()` ends it quietly, e.g. when they click the
 * highlighted button themselves.
 */
import { Button } from '@/shared/ui/button';
import { VOnboardingStep, VOnboardingWrapper, type StepEntity, type VOnboardingWrapperOptions } from 'v-onboarding';
import 'v-onboarding/dist/style.css';
import { X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';

export interface TourStop {
    // The `data-tour` name of the element the stop points at.
    target: string;
    title: string;
    description: string;
    // Where the card sits; `end` is the reading direction's far side.
    placement?: 'top' | 'bottom' | 'end';
    // The label of the Next button on this stop (the last one says Finish).
    nextLabel?: string;
    // Leave the highlighted element clickable (it is not, by default).
    clickable?: boolean;
}

const props = defineProps<{ stops: TourStop[] }>();

const emit = defineEmits<{ finish: []; skip: [] }>();

const { t } = useI18n();

// What VOnboardingWrapper exposes.
const wrapper = ref<{ start(): void; finish(): void; goToStep(step: number): void } | null>(null);
const shown = shallowRef<TourStop[]>([]);
const index = ref(-1);

const active = computed(() => index.value >= 0);
const isRtl = (): boolean => document.documentElement.dir === 'rtl';

function selector(stop: TourStop): string {
    return `[data-tour="${stop.target}"]`;
}

function isVisible(stop: TourStop): boolean {
    const element = document.querySelector<HTMLElement>(selector(stop));

    return element !== null && element.getClientRects().length > 0;
}

const options: VOnboardingWrapperOptions = {
    overlay: { padding: 6, borderRadius: 8 },
    autoFinishByExit: false,
};

const steps = computed<StepEntity[]>(() =>
    shown.value.map((stop) => ({
        attachTo: { element: selector(stop) },
        content: { title: stop.title, description: stop.description },
        options: {
            popper: {
                placement: stop.placement === 'end' ? (isRtl() ? 'left' : 'right') : (stop.placement ?? 'bottom'),
                modifiers: [{ name: 'offset', options: { offset: [0, 14] } }],
            },
        },
        on: {
            // v-onboarding keeps the highlighted element clickable; a click on
            // a link would leave the page mid-tour, so only chosen stops allow it.
            beforeStep: () => {
                if (!stop.clickable) {
                    document.querySelector<HTMLElement>(selector(stop))?.style.setProperty('pointer-events', 'none');
                }
            },
        },
    })),
);

function goTo(next: number): void {
    index.value = next;
    wrapper.value?.goToStep(next);
}

function end(): void {
    index.value = -1;
    wrapper.value?.finish();
    window.removeEventListener('keydown', onKeydown);
}

function next(): void {
    if (index.value >= shown.value.length - 1) {
        end();
        emit('finish');

        return;
    }

    goTo(index.value + 1);
}

function previous(): void {
    if (index.value > 0) {
        goTo(index.value - 1);
    }
}

function skip(): void {
    end();
    emit('skip');
}

function onKeydown(event: KeyboardEvent): void {
    const forward = isRtl() ? 'ArrowLeft' : 'ArrowRight';
    const back = isRtl() ? 'ArrowRight' : 'ArrowLeft';

    if (event.key === 'Escape') skip();
    else if (event.key === forward) next();
    else if (event.key === back) previous();
    else return;

    event.preventDefault();
}

/** Starts at the first stop on screen; with none it finishes at once. */
function start(): void {
    shown.value = props.stops.filter(isVisible);

    if (shown.value.length === 0) {
        emit('finish');

        return;
    }

    index.value = 0;
    window.addEventListener('keydown', onKeydown);
    // The wrapper renders once its steps are in.
    requestAnimationFrame(() => wrapper.value?.start());
}

function stop(): void {
    if (active.value) end();
}

// Leaving the page mid-tour must not leave the page unclickable.
onBeforeUnmount(stop);

defineExpose({ start, stop, active });
</script>

<template>
    <Teleport to="body">
        <div class="onboarding-tour">
            <VOnboardingWrapper ref="wrapper" :steps="steps" :options="options">
                <template #default="{ step }">
                    <VOnboardingStep>
                        <div
                            v-if="step"
                            role="dialog"
                            aria-labelledby="onboarding-tour-title"
                            aria-describedby="onboarding-tour-text"
                            class="bg-popover text-popover-foreground w-80 max-w-[calc(100vw-2rem)] rounded-lg border p-4 shadow-lg"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <p id="onboarding-tour-title" class="font-semibold">{{ step.content.title }}</p>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground -me-1 -mt-1 rounded-sm p-1"
                                    :aria-label="t('onboarding.skip')"
                                    @click="skip"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <p id="onboarding-tour-text" class="text-muted-foreground mt-1 text-sm">{{ step.content.description }}</p>
                            <div class="mt-4 flex items-center justify-between gap-2">
                                <span class="text-muted-foreground text-xs">
                                    {{ t('onboarding.step_of', { current: index + 1, total: shown.length }) }}
                                </span>
                                <div class="flex gap-2">
                                    <Button v-if="index > 0" variant="ghost" size="sm" @click="previous">{{ t('onboarding.back') }}</Button>
                                    <Button size="sm" @click="next">
                                        {{ shown[index]?.nextLabel ?? (index === shown.length - 1 ? t('onboarding.finish') : t('onboarding.next')) }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </VOnboardingStep>
                </template>
            </VOnboardingWrapper>
        </div>
    </Teleport>
</template>

<style scoped>
/* Above the sticky header, the sidebar and open menus. */
.onboarding-tour {
    --v-onboarding-overlay-z: 60;
    --v-onboarding-step-z: 70;
    --v-onboarding-overlay-opacity: 0.45;
}
</style>
