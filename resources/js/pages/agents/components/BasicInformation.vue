<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = defineProps<{
    form: {
        name: string;
        subdomain: string;
        description: string;
        category: string;
        language: string;
        timezone: string;
    };
}>();

const categories = [
    { value: 'support', label: 'Customer Support' },
    { value: 'sales', label: 'Sales & Marketing' },
    { value: 'technical', label: 'Technical Support' },
    { value: 'education', label: 'Education & Training' },
    { value: 'research', label: 'Research & Analysis' },
    { value: 'creative', label: 'Creative Writing' },
    { value: 'legal', label: 'Legal Assistant' },
    { value: 'healthcare', label: 'Healthcare Support' },
];

const languages = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'it', label: 'Italian' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'ru', label: 'Russian' },
    { value: 'zh', label: 'Chinese' },
    { value: 'ja', label: 'Japanese' },
    { value: 'ar', label: 'Arabic' },
];

const timezones = [
    { value: 'UTC', label: 'UTC (Coordinated Universal Time)' },
    { value: 'EST', label: 'EST (Eastern Standard Time)' },
    { value: 'PST', label: 'PST (Pacific Standard Time)' },
    { value: 'GMT', label: 'GMT (Greenwich Mean Time)' },
    { value: 'CET', label: 'CET (Central European Time)' },
    { value: 'IST', label: 'IST (Indian Standard Time)' },
    { value: 'JST', label: 'JST (Japan Standard Time)' },
];
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Basic Information</CardTitle>
            <CardDescription>
                Provide the essential details about your agent
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="name">Agent Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="e.g., Customer Support Assistant"
                        required
                    />
                </div>
                <div class="space-y-2">
                    <Label for="subdomain">Subdomain</Label>
                    <Input
                        id="subdomain"
                        v-model="form.subdomain"
                        placeholder="e.g., support"
                        required
                    />
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="category">Category</Label>
                    <Select v-model="form.category" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label for="language">Primary Language</Label>
                    <Select v-model="form.language" required>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a language" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="language in languages"
                                :key="language.value"
                                :value="language.value"
                            >
                                {{ language.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    placeholder="Describe what your agent does and its purpose"
                    rows="3"
                    required
                />
            </div>

            <div class="space-y-2">
                <Label for="timezone">Timezone</Label>
                <Select v-model="form.timezone" required>
                    <SelectTrigger>
                        <SelectValue placeholder="Select a timezone" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="timezone in timezones"
                            :key="timezone.value"
                            :value="timezone.value"
                        >
                            {{ timezone.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>
</template> 