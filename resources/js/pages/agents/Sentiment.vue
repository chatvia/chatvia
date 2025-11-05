<script lang="ts" setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Plus,
    Trash2,
    Save,
    Zap,
    ArrowRight
} from 'lucide-vue-next';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
    actionTypes: {
        type: Object,
        required: true,
    }
});

interface ActionRule {
    id: string;
    input: string;
    actionType: string;
    config: Record<string, any>;
}

const actions = ref<ActionRule[]>([]);
const newActionInput = ref('');
const newActionType = ref('');

// Add new action rule
const addAction = () => {
    if (!newActionInput.value.trim() || !newActionType.value) {
        return;
    }

    const newAction: ActionRule = {
        id: Date.now().toString(),
        input: newActionInput.value.trim(),
        actionType: newActionType.value,
        config: getDefaultConfig(newActionType.value)
    };

    actions.value.push(newAction);

    // Reset form
    newActionInput.value = '';
    newActionType.value = '';
};

// Remove action rule
const removeAction = (actionId: string) => {
    actions.value = actions.value.filter(action => action.id !== actionId);
};

// Get default configuration for action type
const getDefaultConfig = (actionType: string): Record<string, any> => {
    const defaults: Record<string, Record<string, any>> = {
        'send_slack_message': {
            channel: '#general',
            message: 'User input: {input}'
        },
        'send_email': {
            to: '',
            subject: 'New user input',
            body: 'User said: {input}'
        },
        'webhook': {
            url: '',
            method: 'POST',
            payload: '{"input": "{input}"}'
        },
        'create_ticket': {
            title: 'User Request',
            description: 'User input: {input}',
            priority: 'medium'
        },
        'log_event': {
            event_name: 'user_input',
            data: '{"input": "{input}"}'
        },
        'save_to_database': {
            table: 'user_inputs',
            fields: '{"input": "{input}", "timestamp": "{timestamp}"}'
        }
    };

    return defaults[actionType] || {};
};

// Get action type label
const getActionTypeLabel = (actionType: string) => {
    return props.actionTypes[actionType] || actionType;
};

// Save actions
const saveActions = async () => {
    try {
        await router.post(route('agents.actions.save', { agent: props.agent.id }), {
            actions: actions.value
        });
        console.log('Actions saved successfully');
    } catch (error) {
        console.error('Failed to save actions:', error);
    }
};

// Update action config
const updateActionConfig = (actionId: string, field: string, value: string) => {
    const action = actions.value.find(a => a.id === actionId);
    if (action) {
        action.config[field] = value;
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Sentiment">
        <Head title="Sentiment" />

        <div class="space-y-6">

            <div class="flex justify-center">
                <!-- Add New Sentiment Rule -->
                <Card class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus class="h-5 w-5" />
                        Add New Sentiment Rule
                    </CardTitle>
                    <CardDescription>
                        Create a rule that triggers a response based on sentiment analysis of user input
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="md:col-span-3 space-y-2">
                            <Label for="input">When user input contains</Label>
                            <Input
                                id="input"
                                v-model="newActionInput"
                                placeholder="e.g., 'contact sales', 'urgent help', 'need assistance'"
                                class="w-full text-lg py-3"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="action-type">Do this</Label>
                            <Select v-model="newActionType">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select an action" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="(label, value) in actionTypes"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-end">
                            <Button
                                @click="addAction"
                                :disabled="!newActionInput.trim() || !newActionType"
                                class="w-full"
                            >
                                <Plus class="h-4 w-4 mr-2" />
                                Add Action
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>

            <!-- Existing Sentiment Rules -->
            <div class="flex justify-center">
                <div class="w-full max-w-6xl space-y-4">
                    <h2 class="text-lg font-semibold">Active Sentiment Rules</h2>

                    <div v-if="actions.length === 0" class="text-center py-12">
                        <Zap class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <p class="text-muted-foreground">No sentiment rules configured yet</p>
                        <p class="text-sm text-muted-foreground">Add your first sentiment rule above to get started</p>
                    </div>

                    <Card v-for="action in actions" :key="action.id" class="relative">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-medium">
                                        "{{ action.input }}"
                                    </span>
                                    <ArrowRight class="h-4 w-4 text-muted-foreground" />
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-md font-medium">
                                        {{ getActionTypeLabel(action.actionType) }}
                                    </span>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="removeAction(action.id)"
                                class="text-destructive hover:text-destructive"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-0">
                        <div class="space-y-3">
                            <!-- Slack ChatMessage Config -->
                            <div v-if="action.actionType === 'send_slack_message'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">Channel</Label>
                                        <Input
                                            :value="action.config.channel"
                                            @input="updateActionConfig(action.id, 'channel', $event.target.value)"
                                            placeholder="#general"
                                            class="text-sm"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label class="text-xs">Message Template</Label>
                                    <Textarea
                                        :value="action.config.message"
                                        @input="updateActionConfig(action.id, 'message', $event.target.value)"
                                        placeholder="User input: {input}"
                                        class="text-sm min-h-[60px]"
                                    />
                                </div>
                            </div>

                            <!-- Email Config -->
                            <div v-else-if="action.actionType === 'send_email'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">To Email</Label>
                                        <Input
                                            :value="action.config.to"
                                            @input="updateActionConfig(action.id, 'to', $event.target.value)"
                                            placeholder="admin@example.com"
                                            class="text-sm"
                                        />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Subject</Label>
                                        <Input
                                            :value="action.config.subject"
                                            @input="updateActionConfig(action.id, 'subject', $event.target.value)"
                                            placeholder="New user input"
                                            class="text-sm"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label class="text-xs">Email Body</Label>
                                    <Textarea
                                        :value="action.config.body"
                                        @input="updateActionConfig(action.id, 'body', $event.target.value)"
                                        placeholder="User said: {input}"
                                        class="text-sm min-h-[60px]"
                                    />
                                </div>
                            </div>

                            <!-- Webhook Config -->
                            <div v-else-if="action.actionType === 'webhook'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">URL</Label>
                                        <Input
                                            :value="action.config.url"
                                            @input="updateActionConfig(action.id, 'url', $event.target.value)"
                                            placeholder="https://api.example.com/webhook"
                                            class="text-sm"
                                        />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Method</Label>
                                        <Select
                                            :value="action.config.method"
                                            @update:modelValue="updateActionConfig(action.id, 'method', $event)"
                                        >
                                            <SelectTrigger class="text-sm">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="POST">POST</SelectItem>
                                                <SelectItem value="PUT">PUT</SelectItem>
                                                <SelectItem value="PATCH">PATCH</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div>
                                    <Label class="text-xs">Payload (JSON)</Label>
                                    <Textarea
                                        :value="action.config.payload"
                                        @input="updateActionConfig(action.id, 'payload', $event.target.value)"
                                        placeholder='{"input": "{input}"}'
                                        class="text-sm min-h-[60px] font-mono"
                                    />
                                </div>
                            </div>

                            <!-- Generic config for other types -->
                            <div v-else class="text-sm text-muted-foreground">
                                <p>{{ getActionTypeLabel(action.actionType) }} configuration coming soon...</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
            </div>
        </div>
    </AgentLayout>
</template>
