<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

const props = defineProps<{
    form: {
        personality: string;
        tone: string;
        formality: string;
        responseStyle: string;
        useEmojis: boolean;
        useMarkdown: boolean;
    };
}>();

const tones = [
    { value: 'professional', label: 'Professional', description: 'Formal and business-like communication' },
    { value: 'friendly', label: 'Friendly', description: 'Warm and approachable tone' },
    { value: 'formal', label: 'Formal', description: 'Highly structured and traditional' },
    { value: 'casual', label: 'Casual', description: 'Relaxed and conversational' },
    { value: 'technical', label: 'Technical', description: 'Precise and detailed' },
    { value: 'empathetic', label: 'Empathetic', description: 'Understanding and supportive' },
    { value: 'authoritative', label: 'Authoritative', description: 'Confident and decisive' },
];

const formalityLevels = [
    { value: 'very_formal', label: 'Very Formal' },
    { value: 'formal', label: 'Formal' },
    { value: 'balanced', label: 'Balanced' },
    { value: 'casual', label: 'Casual' },
    { value: 'very_casual', label: 'Very Casual' },
];

const responseStyles = [
    { value: 'concise', label: 'Concise', description: 'Brief and to the point' },
    { value: 'detailed', label: 'Detailed', description: 'Comprehensive and thorough' },
    { value: 'step_by_step', label: 'Step by Step', description: 'Clear sequential instructions' },
    { value: 'conversational', label: 'Conversational', description: 'Natural dialogue style' },
    { value: 'academic', label: 'Academic', description: 'Scholarly and well-referenced' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Personality & Communication</CardTitle>
            <CardDescription>
                Define how your agent should communicate and behave
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="space-y-2">
                <Label for="personality">Personality</Label>
                <Textarea
                    id="personality"
                    v-model="form.personality"
                    placeholder="Describe your agent's personality traits and characteristics"
                    rows="3"
                    required
                />
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="tone">Communication Tone</Label>
                    <Select v-model="form.tone" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a tone" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="tone in tones"
                                :key="tone.value"
                                :value="tone.value"
                            >
                                <div class="flex flex-col">
                                    <span>{{ tone.label }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ tone.description }}
                                    </span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="formality">Formality Level</Label>
                    <Select v-model="form.formality" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select formality level" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="level in formalityLevels"
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
                <Label for="responseStyle">Response Style</Label>
                <Select v-model="form.responseStyle" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select response style" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="style in responseStyles"
                            :key="style.value"
                            :value="style.value"
                        >
                            <div class="flex flex-col">
                                <span>{{ style.label }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ style.description }}
                                </span>
                            </div>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center justify-between space-x-2">
                <div class="space-y-0.5">
                    <Label>Use Emojis</Label>
                    <p class="text-sm text-muted-foreground">
                        Allow the agent to use emojis in responses
                    </p>
                </div>
                <Switch v-model="form.useEmojis" />
            </div>

            <div class="flex items-center justify-between space-x-2">
                <div class="space-y-0.5">
                    <Label>Use Markdown</Label>
                    <p class="text-sm text-muted-foreground">
                        Enable markdown formatting in responses
                    </p>
                </div>
                <Switch v-model="form.useMarkdown" />
            </div>
        </CardContent>
    </Card>
</template> 