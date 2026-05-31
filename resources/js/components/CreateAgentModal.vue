<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, Bot, LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'agent-created': [];
}>();

type UsageData = App.Auth.Data.Resources.My.Usage.UsageResourceData;

const form = useForm({
    name: '',
});

const isSubmitting = ref(false);
const isLoadingUsage = ref(false);
const usageData = ref<UsageData | null>(null);

const canCreateAnyAgent = computed(() => {
    if (!usageData.value) return false;
    return usageData.value.agents.remaining > 0;
});

const currentAgentQuota = computed(() => {
    if (!usageData.value) return null;
    return usageData.value.agents;
});

const fetchUsageData = async () => {
    try {
        isLoadingUsage.value = true;
        const response = await axios.get(route('usage'));
        usageData.value = response.data;
    } catch (error) {
        console.error('Failed to fetch usage data:', error);
        toast.error('Failed to load usage information');
    } finally {
        isLoadingUsage.value = false;
    }
};

const submit = () => {
    if (!canCreateAnyAgent.value) {
        toast.error('Agent limit reached', {
            description: "You've reached your agent limit.",
            duration: 5000,
        });
        return;
    }

    isSubmitting.value = true;

    form.post(route('agents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            emit('update:open', false);
            emit('agent-created');
            fetchUsageData();
            form.reset();
            form.clearErrors();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    emit('update:open', false);
    form.reset();
    form.clearErrors();
};

watch(
    () => props.open,
    (newValue) => {
        if (newValue) {
            fetchUsageData();
            form.clearErrors();
        }
    },
);

onMounted(() => {
    if (props.open) {
        fetchUsageData();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Bot class="h-5 w-5" />
                    Create Agent
                </DialogTitle>
                <DialogDescription> Every agent exposes a Hosted Chat, Widget, and Bar — configure each surface after creating. </DialogDescription>
            </DialogHeader>

            <div v-if="usageData && currentAgentQuota">
                <div v-if="!canCreateAnyAgent" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/20">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Agent Limit Reached</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">You've reached your agent limit.</p>
                            <div class="mt-3">
                                <Button
                                    size="sm"
                                    class="bg-red-600 text-white hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700"
                                    @click="router.visit(route('billing.index'))"
                                >
                                    Upgrade Plan
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">{{ form.progress.percentage }}%</progress>
                <div class="space-y-6" :class="{ 'pointer-events-none opacity-50': !canCreateAnyAgent }">
                    <div class="space-y-2">
                        <Label for="name" required>Agent Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :error="form.errors.name"
                            :disabled="!canCreateAnyAgent"
                            autofocus
                            placeholder="E.g., Customer Support"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>
                </div>
            </form>

            <div class="my-4 border-t border-gray-200 dark:border-gray-700"></div>

            <DialogFooter class="flex justify-between">
                <Button variant="outline" size="lg" @click="closeModal"> Cancel </Button>
                <Button :disabled="isSubmitting || !canCreateAnyAgent" size="lg" @click="submit">
                    <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    Create Agent
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
