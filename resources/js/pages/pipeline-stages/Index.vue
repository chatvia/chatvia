<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { 
    Plus,
    Edit,
    Trash2,
    GripVertical,
    Eye,
    EyeOff,
    Settings
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface PipelineStage {
    id: number;
    name: string;
    slug: string;
    color: string;
    order: number;
    is_active: boolean;
    is_default: boolean;
    description?: string;
    leads_count?: number;
}

interface Props {
    stages: PipelineStage[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    {
        title: 'Pipeline Stages',
        href: route('pipeline-stages.index'),
    },
];

// Form state
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingStage = ref<PipelineStage | null>(null);

const form = ref({
    name: '',
    slug: '',
    color: '#3B82F6',
    description: '',
});

// Methods
const openCreateDialog = () => {
    form.value = {
        name: '',
        slug: '',
        color: '#3B82F6',
        description: '',
    };
    showCreateDialog.value = true;
};

const openEditDialog = (stage: PipelineStage) => {
    editingStage.value = stage;
    form.value = {
        name: stage.name,
        slug: stage.slug,
        color: stage.color,
        description: stage.description || '',
    };
    showEditDialog.value = true;
};

const generateSlug = () => {
    if (form.value.name) {
        form.value.slug = form.value.name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .trim('-');
    }
};

const createStage = () => {
    router.post(route('pipeline-stages.store'), form.value, {
        onSuccess: () => {
            showCreateDialog.value = false;
            toast.success('Pipeline stage created successfully');
        },
        onError: (errors) => {
            toast.error('Failed to create pipeline stage');
        }
    });
};

const updateStage = () => {
    if (!editingStage.value) return;
    
    router.patch(route('pipeline-stages.update', editingStage.value.id), form.value, {
        onSuccess: () => {
            showEditDialog.value = false;
            editingStage.value = null;
            toast.success('Pipeline stage updated successfully');
        },
        onError: (errors) => {
            toast.error('Failed to update pipeline stage');
        }
    });
};

const toggleStageActive = (stage: PipelineStage) => {
    router.patch(route('pipeline-stages.toggle', stage.id), {}, {
        onSuccess: () => {
            const status = stage.is_active ? 'deactivated' : 'activated';
            toast.success(`Pipeline stage ${status} successfully`);
        },
        onError: () => {
            toast.error('Failed to toggle stage status');
        }
    });
};

const deleteStage = (stage: PipelineStage) => {
    if (stage.leads_count && stage.leads_count > 0) {
        toast.error('Cannot delete stage with existing leads');
        return;
    }
    
    if (confirm(`Are you sure you want to delete the "${stage.name}" stage?`)) {
        router.delete(route('pipeline-stages.destroy', stage.id), {
            onSuccess: () => {
                toast.success('Pipeline stage deleted successfully');
            },
            onError: () => {
                toast.error('Failed to delete pipeline stage');
            }
        });
    }
};

const colors = [
    '#3B82F6', // blue
    '#EAB308', // yellow
    '#10B981', // green
    '#F59E0B', // amber
    '#8B5CF6', // violet
    '#059669', // emerald
    '#DC2626', // red
    '#6B7280', // gray
    '#EC4899', // pink
    '#F97316', // orange
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pipeline Stages" />
        
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">Pipeline Stages</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your sales pipeline stages and customize the lead flow
                    </p>
                </div>
                <Button @click="openCreateDialog" class="gap-2">
                    <Plus class="h-4 w-4" />
                    Add Stage
                </Button>
            </div>

            <!-- Stages Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card 
                    v-for="stage in props.stages" 
                    :key="stage.id"
                    class="relative"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-4 h-4 rounded-full"
                                    :style="{ backgroundColor: stage.color }"
                                ></div>
                                <CardTitle class="text-lg">{{ stage.name }}</CardTitle>
                            </div>
                            <div class="flex items-center gap-1">
                                <Badge v-if="stage.is_default" variant="secondary" class="text-xs">
                                    Default
                                </Badge>
                                <Badge 
                                    :variant="stage.is_active ? 'default' : 'secondary'"
                                    class="text-xs"
                                >
                                    {{ stage.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent class="space-y-4">
                        <!-- Description -->
                        <p v-if="stage.description" class="text-sm text-muted-foreground">
                            {{ stage.description }}
                        </p>
                        
                        <!-- Stats -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Order: {{ stage.order }}</span>
                            <span class="text-muted-foreground">
                                {{ stage.leads_count || 0 }} leads
                            </span>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-2">
                            <Button 
                                @click="openEditDialog(stage)" 
                                variant="outline" 
                                size="sm"
                                class="gap-1"
                            >
                                <Edit class="h-3 w-3" />
                                Edit
                            </Button>
                            
                            <Button 
                                @click="toggleStageActive(stage)" 
                                variant="outline" 
                                size="sm"
                                class="gap-1"
                            >
                                <component :is="stage.is_active ? EyeOff : Eye" class="h-3 w-3" />
                                {{ stage.is_active ? 'Deactivate' : 'Activate' }}
                            </Button>
                            
                            <Button 
                                @click="deleteStage(stage)" 
                                variant="outline" 
                                size="sm"
                                class="gap-1 text-red-600 hover:text-red-700"
                                :disabled="(stage.leads_count || 0) > 0"
                            >
                                <Trash2 class="h-3 w-3" />
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Create Dialog -->
            <Dialog v-model:open="showCreateDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Pipeline Stage</DialogTitle>
                    </DialogHeader>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium">Stage Name</label>
                            <Input 
                                v-model="form.name" 
                                placeholder="e.g., Proposal Sent"
                                @input="generateSlug"
                            />
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Slug</label>
                            <Input 
                                v-model="form.slug" 
                                placeholder="e.g., proposal-sent"
                            />
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Color</label>
                            <div class="flex gap-2 mt-2">
                                <button
                                    v-for="color in colors"
                                    :key="color"
                                    @click="form.color = color"
                                    class="w-8 h-8 rounded-full border-2 transition-all"
                                    :class="[
                                        form.color === color 
                                            ? 'border-primary scale-110' 
                                            : 'border-muted hover:scale-105'
                                    ]"
                                    :style="{ backgroundColor: color }"
                                ></button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Description (Optional)</label>
                            <textarea 
                                v-model="form.description" 
                                placeholder="Describe when leads should be in this stage..."
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                        
                        <div class="flex gap-2 pt-4">
                            <Button @click="createStage" class="flex-1">
                                Create Stage
                            </Button>
                            <Button @click="showCreateDialog = false" variant="outline" class="flex-1">
                                Cancel
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Edit Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Pipeline Stage</DialogTitle>
                    </DialogHeader>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium">Stage Name</label>
                            <Input 
                                v-model="form.name" 
                                placeholder="e.g., Proposal Sent"
                            />
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Slug</label>
                            <Input 
                                v-model="form.slug" 
                                placeholder="e.g., proposal-sent"
                            />
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Color</label>
                            <div class="flex gap-2 mt-2">
                                <button
                                    v-for="color in colors"
                                    :key="color"
                                    @click="form.color = color"
                                    class="w-8 h-8 rounded-full border-2 transition-all"
                                    :class="[
                                        form.color === color 
                                            ? 'border-primary scale-110' 
                                            : 'border-muted hover:scale-105'
                                    ]"
                                    :style="{ backgroundColor: color }"
                                ></button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="text-sm font-medium">Description (Optional)</label>
                            <textarea 
                                v-model="form.description" 
                                placeholder="Describe when leads should be in this stage..."
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                        </div>
                        
                        <div class="flex gap-2 pt-4">
                            <Button @click="updateStage" class="flex-1">
                                Update Stage
                            </Button>
                            <Button @click="showEditDialog = false" variant="outline" class="flex-1">
                                Cancel
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
