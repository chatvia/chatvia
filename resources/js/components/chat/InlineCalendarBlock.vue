<script setup lang="ts">
/**
 * Renders a booking calendar artifact (from RenderCalendarTool): a week of
 * free times in the visitor's own time zone, then name / email / notes and
 * Book. Transport is injected (`inlineCalendar`) by the chat surface, like
 * the inline form, so the block stays surface-agnostic. When the scheduler
 * refuses API booking, the block offers the meeting's own booking page.
 */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocaleDate } from '@/composables/useLocaleDate';
import { type BookingWindow, canGoBack, canGoForward, groupSlotsByDay, shiftWindow, visitorTimeZone } from '@/lib/bookingSlots';
import type { CalendarArtifactData } from '@/types/stream';
import { CalendarCheck2, ChevronLeft, ChevronRight, ExternalLink, LoaderCircle } from 'lucide-vue-next';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface CalendarTransport {
    slots: (bookingInstanceId: string, from: string, to: string) => Promise<{ slots: { start_time: string }[] }>;
    book: (
        bookingInstanceId: string,
        payload: { start_time: string; name: string; email: string; timezone: string; notes?: string; assistant_message_id?: string },
    ) => Promise<{ status: string; start_time: string; message: string }>;
}

interface FailureBody {
    status?: string;
    message?: string;
    fallback_url?: string | null;
    errors?: Record<string, string[]>;
}

const props = defineProps<{ calendar: CalendarArtifactData; messageId?: string }>();

const { t } = useI18n();
const { intlLocale } = useLocaleDate();
const transport = inject<CalendarTransport | null>('inlineCalendar', null);

const timeZone = visitorTimeZone();
const earliest = new Date(props.calendar.window_start);

const state = ref<'pick' | 'details' | 'booked' | 'expired' | 'fallback'>(
    props.calendar.status === 'submitted' ? 'booked' : props.calendar.status === 'expired' ? 'expired' : 'pick',
);
const range = ref<BookingWindow>({ from: earliest, to: new Date(props.calendar.window_end) });
const slots = ref<string[]>(props.calendar.slots.map((slot) => slot.start_time));
const loadingSlots = ref(false);
const slotsError = ref(false);

const days = computed(() => groupSlotsByDay(slots.value, timeZone));
const activeDayKey = ref<string | null>(days.value[0]?.key ?? null);
const activeDay = computed(() => days.value.find((day) => day.key === activeDayKey.value) ?? days.value[0] ?? null);

const selected = ref<string | null>(null);
const bookedStart = ref<string | null>(props.calendar.booked_start_time);
const message = ref<string | null>(null);
const fallbackUrl = ref<string | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const booking = ref(false);

const name = ref('');
const email = ref('');
const notes = ref('');

const dayLabel = (key: string): string =>
    new Date(`${key}T12:00:00`).toLocaleDateString(intlLocale.value, { weekday: 'short', month: 'short', day: 'numeric' });
const timeLabel = (iso: string): string => new Date(iso).toLocaleTimeString(intlLocale.value, { timeZone, hour: 'numeric', minute: '2-digit' });
const fullLabel = (iso: string): string => new Date(iso).toLocaleString(intlLocale.value, { timeZone, dateStyle: 'full', timeStyle: 'short' });

async function browse(direction: 1 | -1): Promise<void> {
    if (!transport || loadingSlots.value) return;

    const next = shiftWindow(range.value, direction, earliest, props.calendar.max_days_ahead);
    loadingSlots.value = true;
    slotsError.value = false;
    try {
        const result = await transport.slots(props.calendar.booking_instance_id, next.from.toISOString(), next.to.toISOString());
        range.value = next;
        slots.value = result.slots.map((slot) => slot.start_time);
        activeDayKey.value = days.value[0]?.key ?? null;
    } catch (error: unknown) {
        handleFailure(error, () => (slotsError.value = true));
    } finally {
        loadingSlots.value = false;
    }
}

async function reloadWeek(): Promise<void> {
    if (!transport) return;

    try {
        const result = await transport.slots(props.calendar.booking_instance_id, range.value.from.toISOString(), range.value.to.toISOString());
        slots.value = result.slots.map((slot) => slot.start_time);
        activeDayKey.value = days.value[0]?.key ?? null;
    } catch {
        slotsError.value = true;
    }
}

function choose(slot: string): void {
    selected.value = slot;
    message.value = null;
    fieldErrors.value = {};
    state.value = 'details';
}

async function book(): Promise<void> {
    if (!selected.value || booking.value) return;
    if (!transport) {
        message.value = t('chat.calendar.unavailable');
        return;
    }

    booking.value = true;
    message.value = null;
    fieldErrors.value = {};
    try {
        const result = await transport.book(props.calendar.booking_instance_id, {
            start_time: selected.value,
            name: name.value,
            email: email.value,
            timezone: timeZone,
            notes: notes.value || undefined,
            assistant_message_id: props.messageId,
        });
        bookedStart.value = result.start_time;
        message.value = result.message;
        state.value = 'booked';
    } catch (error: unknown) {
        handleFailure(error, () => (message.value = t('chat.calendar.book_failed')));
    } finally {
        booking.value = false;
    }
}

/** Reads the server's `{ status, message }` contract; anything else falls to `otherwise`. */
function handleFailure(error: unknown, otherwise: () => void): void {
    const response = (error as { response?: { status?: number; data?: FailureBody } })?.response;
    const body = response?.data ?? {};

    if (body.status === 'submitted') {
        state.value = 'booked';
    } else if (body.status === 'expired' || response?.status === 410) {
        state.value = 'expired';
    } else if (body.status === 'slot_taken') {
        message.value = body.message ?? t('chat.calendar.slot_taken');
        selected.value = null;
        state.value = 'pick';
        void reloadWeek();
    } else if (body.status === 'booking_not_allowed') {
        message.value = body.message ?? null;
        fallbackUrl.value = body.fallback_url ?? null;
        state.value = 'fallback';
    } else if (response?.status === 422 && body.errors) {
        fieldErrors.value = Object.fromEntries(Object.entries(body.errors).map(([field, errors]) => [field, errors[0] ?? '']));
    } else {
        otherwise();
    }
}
</script>

<template>
    <div class="bg-card not-prose my-2 rounded-lg border p-4">
        <div class="mb-3 flex items-start gap-3 border-b pb-3">
            <CalendarCheck2 class="text-primary mt-0.5 h-5 w-5 shrink-0" />
            <div class="min-w-0">
                <p class="text-base leading-tight font-semibold">{{ calendar.event_type_name }}</p>
                <p class="text-muted-foreground mt-1 text-xs">{{ t('chat.calendar.duration', { minutes: calendar.duration }) }}</p>
            </div>
        </div>

        <!-- Booked -->
        <div v-if="state === 'booked'" class="flex items-start gap-2 text-sm font-medium text-green-600">
            <CalendarCheck2 class="h-5 w-5 shrink-0" />
            <div>
                <p v-if="bookedStart">{{ t('chat.calendar.booked_for', { time: fullLabel(bookedStart) }) }}</p>
                <p v-else>{{ t('chat.calendar.booked') }}</p>
                <p v-if="message" class="text-muted-foreground mt-1 font-normal">{{ message }}</p>
            </div>
        </div>

        <!-- Expired -->
        <p v-else-if="state === 'expired'" class="text-muted-foreground text-sm">{{ t('chat.calendar.expired') }}</p>

        <!-- The scheduler refused booking from the chat: book on its own page. -->
        <div v-else-if="state === 'fallback'" class="space-y-3 text-sm">
            <p>{{ message ?? t('chat.calendar.book_on_page') }}</p>
            <a v-if="fallbackUrl" :href="fallbackUrl" target="_blank" rel="noopener noreferrer">
                <Button size="sm">
                    {{ t('chat.calendar.open_booking_page') }}
                    <ExternalLink class="ms-1 h-4 w-4" />
                </Button>
            </a>
        </div>

        <!-- Pick a time -->
        <div v-else-if="state === 'pick'" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="loadingSlots || !canGoBack(range, earliest)"
                    :aria-label="t('chat.calendar.previous_week')"
                    @click="browse(-1)"
                >
                    <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                </Button>
                <p class="text-muted-foreground text-xs">{{ t('chat.calendar.times_in', { timezone: timeZone }) }}</p>
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="loadingSlots || !canGoForward(range, earliest, calendar.max_days_ahead)"
                    :aria-label="t('chat.calendar.next_week')"
                    @click="browse(1)"
                >
                    <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                </Button>
            </div>

            <div v-if="loadingSlots" class="text-muted-foreground flex items-center gap-2 py-4 text-sm">
                <LoaderCircle class="h-4 w-4 animate-spin" />
                {{ t('chat.calendar.loading') }}
            </div>
            <p v-else-if="slotsError" class="text-destructive py-2 text-sm">{{ t('chat.calendar.slots_failed') }}</p>
            <p v-else-if="days.length === 0" class="text-muted-foreground py-2 text-sm">{{ t('chat.calendar.no_times') }}</p>
            <template v-else>
                <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" :aria-label="t('chat.calendar.days')">
                    <Button
                        v-for="day in days"
                        :key="day.key"
                        role="tab"
                        size="sm"
                        :variant="day.key === activeDay?.key ? 'default' : 'outline'"
                        :aria-selected="day.key === activeDay?.key"
                        class="shrink-0"
                        @click="activeDayKey = day.key"
                    >
                        {{ dayLabel(day.key) }}
                    </Button>
                </div>
                <div v-if="activeDay" class="grid grid-cols-3 gap-2 sm:grid-cols-4" role="tabpanel">
                    <Button v-for="slot in activeDay.slots" :key="slot" variant="outline" size="sm" @click="choose(slot)">
                        {{ timeLabel(slot) }}
                    </Button>
                </div>
            </template>
            <p v-if="message" class="text-destructive text-sm">{{ message }}</p>
        </div>

        <!-- Who is booking -->
        <form v-else-if="state === 'details' && selected" class="space-y-3" @submit.prevent="book">
            <p class="text-sm font-medium">{{ fullLabel(selected) }}</p>
            <div class="space-y-1">
                <Label :for="`booking-name-${calendar.booking_instance_id}`">{{ t('chat.calendar.name') }}</Label>
                <Input :id="`booking-name-${calendar.booking_instance_id}`" v-model="name" autocomplete="name" required maxlength="200" />
                <p v-if="fieldErrors.name" class="text-destructive text-xs">{{ fieldErrors.name }}</p>
            </div>
            <div class="space-y-1">
                <Label :for="`booking-email-${calendar.booking_instance_id}`">{{ t('chat.calendar.email') }}</Label>
                <Input
                    :id="`booking-email-${calendar.booking_instance_id}`"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    required
                    maxlength="254"
                />
                <p v-if="fieldErrors.email" class="text-destructive text-xs">{{ fieldErrors.email }}</p>
            </div>
            <div class="space-y-1">
                <Label :for="`booking-notes-${calendar.booking_instance_id}`">
                    {{ calendar.notes_required ? t('chat.calendar.notes') : t('chat.calendar.notes_optional') }}
                </Label>
                <Textarea
                    :id="`booking-notes-${calendar.booking_instance_id}`"
                    v-model="notes"
                    rows="2"
                    maxlength="1000"
                    :required="calendar.notes_required === true"
                />
                <p v-if="fieldErrors.notes" class="text-destructive text-xs">{{ fieldErrors.notes }}</p>
            </div>
            <p v-if="fieldErrors.start_time" class="text-destructive text-xs">{{ fieldErrors.start_time }}</p>
            <p v-if="message" class="text-destructive text-sm">{{ message }}</p>
            <div class="flex gap-2">
                <Button type="submit" :disabled="booking">
                    <LoaderCircle v-if="booking" class="me-1 h-4 w-4 animate-spin" />
                    {{ booking ? t('chat.calendar.booking') : t('chat.calendar.book') }}
                </Button>
                <Button type="button" variant="ghost" :disabled="booking" @click="state = 'pick'">{{ t('chat.calendar.back') }}</Button>
            </div>
        </form>
    </div>
</template>
