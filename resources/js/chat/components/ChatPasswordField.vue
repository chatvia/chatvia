<script setup lang="ts">
/**
 * One labelled password row in the chatter profile dialog, with a lock icon,
 * a show/hide toggle and the field's validation error.
 */
import InputError from '@/shared/components/InputError.vue';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Eye, EyeOff, Lock } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    id: string;
    label: string;
    placeholder: string;
    error?: string;
}>();

const value = defineModel<string>({ required: true });

const { t } = useI18n();
const isVisible = ref(false);
</script>

<template>
    <div class="grid grid-cols-4 items-center gap-4">
        <Label :for="id" class="text-end">{{ label }}</Label>
        <div class="col-span-3 space-y-1">
            <div class="relative">
                <Lock class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
                <Input :id="id" v-model="value" :type="isVisible ? 'text' : 'password'" class="ps-10 pe-10" :placeholder="placeholder" />
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground absolute end-3 top-1/2 -translate-y-1/2 transform"
                    :aria-label="isVisible ? t('sidebar.hide_password') : t('sidebar.show_password')"
                    @click="isVisible = !isVisible"
                >
                    <Eye v-if="!isVisible" class="h-4 w-4" />
                    <EyeOff v-else class="h-4 w-4" />
                </button>
            </div>
            <InputError :message="error" class="text-xs" />
        </div>
    </div>
</template>
