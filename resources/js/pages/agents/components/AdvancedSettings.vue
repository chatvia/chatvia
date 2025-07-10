<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';

const props = defineProps<{
    form: {
        maxResponseLength: string;
        temperature: number;
        contextWindow: string;
        fallbackBehavior: string;
        errorHandling: string;
        dataRetention: string;
        privacyLevel: string;
        userDataHandling: string;
        contentFiltering: boolean;
    };
}>();

const maxResponseLengths = [
    { value: 'short', label: 'Short (1-2 sentences)' },
    { value: 'medium', label: 'Medium (2-3 paragraphs)' },
    { value: 'long', label: 'Long (Detailed explanations)' },
    { value: 'custom', label: 'Custom Length' },
];

const contextWindows = [
    { value: 'small', label: 'Small (1-2 messages)' },
    { value: 'medium', label: 'Medium (5-10 messages)' },
    { value: 'large', label: 'Large (20+ messages)' },
    { value: 'unlimited', label: 'Unlimited' },
];

const fallbackBehaviors = [
    { value: 'ask_user', label: 'Ask User for Clarification' },
    { value: 'use_default', label: 'Use Default Response' },
    { value: 'escalate', label: 'Escalate to Human' },
    { value: 'skip', label: 'Skip and Continue' },
];

const errorHandling = [
    { value: 'graceful', label: 'Graceful Degradation' },
    { value: 'strict', label: 'Strict Validation' },
    { value: 'adaptive', label: 'Adaptive Recovery' },
    { value: 'informative', label: 'Informative Errors' },
];

const dataRetention = [
    { value: '7days', label: '7 Days' },
    { value: '30days', label: '30 Days' },
    { value: '90days', label: '90 Days' },
    { value: '1year', label: '1 Year' },
    { value: 'indefinite', label: 'Indefinite' },
];

const privacyLevels = [
    { value: 'minimal', label: 'Minimal (Basic Data)' },
    { value: 'standard', label: 'Standard (Normal Usage)' },
    { value: 'enhanced', label: 'Enhanced (Extra Security)' },
    { value: 'maximum', label: 'Maximum (Full Protection)' },
];

const userDataHandling = [
    { value: 'minimal', label: 'Minimal (Essential Only)' },
    { value: 'standard', label: 'Standard (Normal Usage)' },
    { value: 'comprehensive', label: 'Comprehensive (Full Profile)' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Advanced Settings</CardTitle>
            <CardDescription>
                Configure advanced behavior and technical settings
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="maxResponseLength">Maximum Response Length</Label>
                    <Select v-model="form.maxResponseLength" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select response length" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="length in maxResponseLengths"
                                :key="length.value"
                                :value="length.value"
                            >
                                {{ length.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="contextWindow">Context Window</Label>
                    <Select v-model="form.contextWindow" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select context window" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="window in contextWindows"
                                :key="window.value"
                                :value="window.value"
                            >
                                {{ window.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="fallbackBehavior">Fallback Behavior</Label>
                    <Select v-model="form.fallbackBehavior" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select fallback behavior" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="behavior in fallbackBehaviors"
                                :key="behavior.value"
                                :value="behavior.value"
                            >
                                {{ behavior.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="errorHandling">Error Handling</Label>
                    <Select v-model="form.errorHandling" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select error handling" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="handling in errorHandling"
                                :key="handling.value"
                                :value="handling.value"
                            >
                                {{ handling.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <Separator />

            <div class="space-y-4">
                <h3 class="text-lg font-medium">Security & Privacy</h3>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="dataRetention">Data Retention</Label>
                        <Select v-model="form.dataRetention" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Select data retention period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="retention in dataRetention"
                                    :key="retention.value"
                                    :value="retention.value"
                                >
                                    {{ retention.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="privacyLevel">Privacy Level</Label>
                        <Select v-model="form.privacyLevel" required>
                            <SelectTrigger>
                                <SelectValue placeholder="Select privacy level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="level in privacyLevels"
                                    :key="level.value"
                                    :value="level.value"
                                >
                                    {{ level.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="userDataHandling">User Data Handling</Label>
                    <Select v-model="form.userDataHandling" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select user data handling" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="handling in userDataHandling"
                                :key="handling.value"
                                :value="handling.value"
                            >
                                {{ handling.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex items-center justify-between space-x-2">
                    <div class="space-y-0.5">
                        <Label>Content Filtering</Label>
                        <p class="text-sm text-muted-foreground">
                            Enable automatic content filtering
                        </p>
                    </div>
                    <Switch v-model="form.contentFiltering" />
                </div>
            </div>
        </CardContent>
    </Card>
</template> 