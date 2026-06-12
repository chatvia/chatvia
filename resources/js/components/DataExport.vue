<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { AlertTriangle, CheckCircle2, Download, Loader2, RefreshCw, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface ActiveExport {
    id: number;
    status: 'pending' | 'processing' | 'ready' | 'failed' | 'expired';
    file_size: number | null;
    expires_at: string | null;
    error_message: string | null;
    created_at: string | null;
    download_url: string | null;
}

interface Props {
    export: ActiveExport | null;
}

const props = defineProps<Props>();

const form = useForm({});

const submit = () => {
    form.post(route('account.data-export.store'), {
        preserveScroll: true,
    });
};

const page = usePage();
const userId = computed<number | null>(() => (page.props as any)?.auth?.user?.id ?? null);

const now = ref<number>(Math.floor(performance.timeOrigin / 1000) + Math.floor(performance.now() / 1000));
let tick: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    tick = setInterval(() => {
        now.value += 1;
    }, 1000);
});

onUnmounted(() => {
    if (tick) clearInterval(tick);
});

const remainingHours = computed<string | null>(() => {
    if (!props.export?.expires_at) return null;
    const expiresMs = Date.parse(props.export.expires_at);
    const diffSec = Math.max(0, Math.floor((expiresMs - now.value * 1000) / 1000));
    const h = Math.floor(diffSec / 3600);
    const m = Math.floor((diffSec % 3600) / 60);
    return `${h}h ${m}m`;
});

const formattedSize = computed<string | null>(() => {
    if (!props.export?.file_size) return null;
    const mb = props.export.file_size / (1024 * 1024);
    if (mb < 1) {
        return `${(props.export.file_size / 1024).toFixed(0)} KB`;
    }
    return `${mb.toFixed(1)} MB`;
});

// When the backend tells us the export is ready (via broadcast notification),
// reload the Profile page props so the UI flips from "processing" → "ready"
// without the user having to refresh.
if (userId.value) {
    useEcho(`App.Identity.Workspace.Models.User.${userId.value}`, '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', () => {
        router.reload({ only: ['activeExport'] });
    });
}

const refresh = () => {
    router.reload({ only: ['activeExport'] });
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Download class="size-6" />
                {{ t('account.data_export.heading') }}
            </h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ t('account.data_export.description') }}
            </p>
        </div>

        <Separator />

        <div class="space-y-4 rounded-lg border border-emerald-100 bg-emerald-50 p-4 dark:border-emerald-200/10 dark:bg-emerald-700/10">
            <!-- Idle: no active export -->
            <template v-if="!props.export">
                <div class="flex items-start gap-3 text-emerald-700 dark:text-emerald-100">
                    <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                    <div class="space-y-1">
                        <p class="font-medium">{{ t('account.data_export.idle_title') }}</p>
                        <p class="text-sm">{{ t('account.data_export.idle_description') }}</p>
                    </div>
                </div>
                <form @submit.prevent="submit">
                    <Button type="submit" :disabled="form.processing">
                        <Download class="me-2 size-4" />
                        {{ t('account.data_export.request_button') }}
                    </Button>
                    <InputError class="mt-2" :message="(form.errors as Record<string, string>).data_export" />
                </form>
            </template>

            <!-- Pending / processing -->
            <template v-else-if="props.export.status === 'pending' || props.export.status === 'processing'">
                <div class="flex items-start gap-3 text-emerald-700 dark:text-emerald-100">
                    <Loader2 class="mt-0.5 size-5 shrink-0 animate-spin" />
                    <div class="space-y-1">
                        <p class="font-medium">{{ t('account.data_export.processing_title') }}</p>
                        <p class="text-sm">{{ t('account.data_export.processing_description') }}</p>
                    </div>
                </div>
                <Button type="button" variant="secondary" size="sm" @click="refresh">
                    <RefreshCw class="me-2 size-4" />
                    {{ t('account.data_export.check_status') }}
                </Button>
            </template>

            <!-- Ready -->
            <template v-else-if="props.export.status === 'ready' && props.export.download_url">
                <div class="flex items-start gap-3 text-emerald-700 dark:text-emerald-100">
                    <CheckCircle2 class="mt-0.5 size-5 shrink-0" />
                    <div class="space-y-1">
                        <p class="font-medium">{{ t('account.data_export.ready_title') }}</p>
                        <p class="text-sm">
                            <span v-if="formattedSize">{{ formattedSize }} · </span>
                            <span v-if="remainingHours">{{ t('account.data_export.available_for', { time: remainingHours }) }}</span>
                        </p>
                    </div>
                </div>
                <a :href="props.export.download_url">
                    <Button type="button">
                        <Download class="me-2 size-4" />
                        {{ t('account.data_export.download_zip') }}
                    </Button>
                </a>
            </template>

            <!-- Failed -->
            <template v-else-if="props.export.status === 'failed'">
                <div class="flex items-start gap-3 text-red-700 dark:text-red-100">
                    <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <div class="space-y-1">
                        <p class="font-medium">{{ t('account.data_export.failed_title') }}</p>
                        <p v-if="props.export.error_message" class="text-sm">
                            {{ props.export.error_message }}
                        </p>
                    </div>
                </div>
                <form @submit.prevent="submit">
                    <Button type="submit" variant="secondary" :disabled="form.processing">
                        <RefreshCw class="me-2 size-4" />
                        {{ t('account.data_export.try_again') }}
                    </Button>
                </form>
            </template>
        </div>
    </div>
</template>
