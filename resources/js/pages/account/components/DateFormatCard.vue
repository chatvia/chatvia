<script lang="ts">
import { type DateFormatString } from '@/composables/useDateFormat';

/** One choice in the date-format picker, as the server lists them. */
export interface DateFormatOption {
    value: DateFormatString;
    label: string;
    example: string;
}
</script>

<script setup lang="ts">
// "Date & time" card on the profile page: pick how calendar dates are shown
// across the dashboard, with a live preview of the current moment.
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DEFAULT_DATE_FORMAT, formatDateWith } from '@/composables/useDateFormat';
import { update as updatePreferences } from '@/routes/account/preferences';
import { type User } from '@/types';

const props = defineProps<{
    user: User;
    dateFormats: DateFormatOption[];
}>();

const { t } = useI18n();

const dateForm = useForm({
    date_format: props.user.date_format || DEFAULT_DATE_FORMAT,
});

const selectedFormat = computed(() => props.dateFormats.find((option) => option.value === dateForm.date_format));

// Live preview of the current moment in the selected format.
const datePreview = computed(() => formatDateWith(dateForm.date_format, new Date()));

const submitDateFormat = () => {
    dateForm.patch(updatePreferences.url(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <!-- Date & time preferences -->
    <Card>
        <CardHeader>
            <CardTitle>{{ t('account.profile.datetime_title') }}</CardTitle>
            <CardDescription>{{ t('account.profile.datetime_description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submitDateFormat" class="space-y-6">
                <div class="grid gap-2">
                    <Label for="date_format">{{ t('account.profile.date_format') }}</Label>
                    <Select v-model="dateForm.date_format">
                        <SelectTrigger id="date_format" class="w-full sm:w-80">
                            <!-- Only the example once chosen: the copied item text would run the label into it. -->
                            <SelectValue :placeholder="t('account.profile.date_format_placeholder')">
                                {{ selectedFormat?.example ?? t('account.profile.date_format_placeholder') }}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in props.dateFormats" :key="option.value" :value="option.value">
                                <span class="font-medium">{{ option.example }}</span>
                                <span class="text-muted-foreground ms-2">{{ option.label }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="dateForm.errors.date_format" />
                </div>

                <div class="bg-muted/50 rounded-md px-4 py-3 text-sm">
                    <span class="text-muted-foreground">{{ t('common.preview') }}:</span>
                    <span class="ms-2 font-medium">{{ datePreview }}</span>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <Button type="submit" :disabled="dateForm.processing">
                        <Save class="me-2 size-4" />
                        {{ t('common.save') }}
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out duration-300"
                        enter-from-class="opacity-0 transform translate-x-4"
                        enter-to-class="opacity-100 transform translate-x-0"
                        leave-active-class="transition ease-in-out duration-300"
                        leave-from-class="opacity-100 transform translate-x-0"
                        leave-to-class="opacity-0 transform translate-x-4"
                    >
                        <div v-show="dateForm.recentlySuccessful" class="flex items-center text-sm text-green-600">
                            <CheckCircle class="me-1 size-4" />
                            <span>{{ t('common.saved') }}</span>
                        </div>
                    </Transition>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
