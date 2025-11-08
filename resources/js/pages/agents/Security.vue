<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogFooter, 
    DialogHeader, 
    DialogTitle 
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Lock, AlertTriangle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// Check if agent is widget type
const isWidgetAgent = computed(() => {
    return props.agent.type === 'widget';
});

const form = useForm({
    is_public: props.agent.is_public,
});

const isSubmitting = ref(false);
const showConfirmDialog = ref(false);

const updateSecuritySettings = () => {
    isSubmitting.value = true;
    form.patch(route('agents.security.update', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            toast.success('Security settings updated');
        },
        onError: () => {
            isSubmitting.value = false;
            toast.error('Failed to update security settings');
            // Revert the form value on error
            form.is_public = !form.is_public;
        }
    });
};

const confirmMakePublic = () => {
    showConfirmDialog.value = false;
    updateSecuritySettings();
};

const cancelMakePublic = () => {
    showConfirmDialog.value = false;
    // Revert the toggle
    form.is_public = false;
};

// Auto-save when toggle changes
watch(() => form.is_public, (newValue, oldValue) => {
    // Only process if the value actually changed and it's not the initial load
    if (oldValue !== undefined && newValue !== oldValue) {
        // If trying to make public, show confirmation
        if (newValue === true) {
            showConfirmDialog.value = true;
        } else {
            // If making private, just save directly
            updateSecuritySettings();
        }
    }
});
</script>

<template>
    <AgentLayout 
        :agent="agent" 
        title="Security"
        description="Configure access control and security settings for your agent"
    >
        <template #header-icon>
            <Lock class="h-5 w-5" />
        </template>
        
        <div class="flex justify-center">
            <Card v-if="!isWidgetAgent" class="w-full max-w-6xl">
                <CardContent class="p-6">
                    <!-- Public Access Toggle -->
                    <div class="flex items-center justify-between space-x-2">
                        <div class="flex-1">
                            <Label class="text-base" for="is_public">Make this agent public</Label>
                            <p class="text-sm text-muted-foreground mt-1">
                                Public agents can be accessed by anyone with the link
                            </p>
                        </div>
                        <Switch 
                            id="is_public" 
                            v-model="form.is_public"
                            :disabled="isSubmitting"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Confirmation Dialog -->
        <Dialog v-model:open="showConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                        Make Agent Public?
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to make this agent public? Anyone with the link will be able to access and interact with this agent.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelMakePublic">
                        Cancel
                    </Button>
                    <Button @click="confirmMakePublic">
                        Make Public
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
