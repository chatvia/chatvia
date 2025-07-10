<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';

import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Palette, Upload, Trash2, LoaderCircle, Save, User } from 'lucide-vue-next';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// Form for appearance settings
const appearanceForm = useForm({
    primary_color: props.agent.theme?.primary_color || '#7C3AED',
    chat_bubble_color: props.agent.theme?.chat_bubble_color || '#F3F4F6',
    chat_bubble_text_color: props.agent.theme?.chat_bubble_text_color || '#111827',
    welcome_message: props.agent.theme?.welcome_message || '',
    chat_window_title: props.agent.theme?.chat_window_title || ''
});

// States
const isSubmitting = ref(false);
const avatarPreview = ref(null);
const avatarInputRef = ref(null);

const updateAppearance = () => {
    isSubmitting.value = true;
    appearanceForm.put(route('agents.update.appearance', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const triggerAvatarInput = () => {
    avatarInputRef.value.click();
};

const handleAvatarUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        avatarPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
};

const removeAvatar = () => {
    avatarPreview.value = null;
    if (avatarInputRef.value) {
        avatarInputRef.value.value = '';
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Appearance Settings">
        <form @submit.prevent="updateAppearance">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Palette class="h-5 w-5" />
                        Appearance
                    </CardTitle>
                    <CardDescription>
                        Customize how your agent looks
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="primary_color">Primary Color</Label>
                                <div class="flex items-center gap-2">
                                    <div
                                        :style="{ backgroundColor: appearanceForm.primary_color }"
                                        class="h-8 w-8 rounded-full border"
                                    ></div>
                                    <Input
                                        id="primary_color"
                                        v-model="appearanceForm.primary_color"
                                        :error="appearanceForm.errors.primary_color"
                                        placeholder="#7C3AED"
                                        type="text"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Used for buttons and accents
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="chat_bubble_color">Chat Bubble Color</Label>
                                <div class="flex items-center gap-2">
                                    <div
                                        :style="{ backgroundColor: appearanceForm.chat_bubble_color }"
                                        class="h-8 w-8 rounded-full border"
                                    ></div>
                                    <Input
                                        id="chat_bubble_color"
                                        v-model="appearanceForm.chat_bubble_color"
                                        :error="appearanceForm.errors.chat_bubble_color"
                                        placeholder="#F3F4F6"
                                        type="text"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Background color of chat bubbles
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="chat_bubble_text_color">Chat Text Color</Label>
                                <div class="flex items-center gap-2">
                                    <div
                                        :style="{ backgroundColor: appearanceForm.chat_bubble_text_color }"
                                        class="h-8 w-8 rounded-full border"
                                    ></div>
                                    <Input
                                        id="chat_bubble_text_color"
                                        v-model="appearanceForm.chat_bubble_text_color"
                                        :error="appearanceForm.errors.chat_bubble_text_color"
                                        placeholder="#111827"
                                        type="text"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Text color in chat bubbles
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="avatar">Agent Avatar</Label>
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 overflow-hidden rounded-full border bg-muted">
                                    <img
                                        v-if="avatarPreview"
                                        :src="avatarPreview"
                                        alt="Agent avatar"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
                                        <User class="h-8 w-8" />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input
                                        ref="avatarInputRef"
                                        accept="image/*"
                                        class="hidden"
                                        type="file"
                                        @change="handleAvatarUpload"
                                    />
                                    <Button type="button" variant="outline" @click="triggerAvatarInput">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Upload Avatar
                                    </Button>
                                    <Button
                                        v-if="avatarPreview"
                                        class="text-destructive hover:text-destructive/80"
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        @click="removeAvatar"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Recommended size: 256x256 pixels
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="welcome_message">Welcome Message</Label>
                            <Textarea
                                id="welcome_message"
                                v-model="appearanceForm.welcome_message"
                                :error="appearanceForm.errors.welcome_message"
                                placeholder="Hi there! How can I help you today?"
                                rows="3"
                            />
                            <p v-if="appearanceForm.errors.welcome_message" class="text-sm text-destructive">
                                {{ appearanceForm.errors.welcome_message }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                This message will be shown when users first visit your agent
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="chat_window_title">Chat Window Title</Label>
                            <Input
                                id="chat_window_title"
                                v-model="appearanceForm.chat_window_title"
                                :error="appearanceForm.errors.chat_window_title"
                                placeholder="Chat with our AI Assistant"
                            />
                            <p v-if="appearanceForm.errors.chat_window_title" class="text-sm text-destructive">
                                {{ appearanceForm.errors.chat_window_title }}
                            </p>
                        </div>
                    </div>
                </CardContent>
                <CardFooter class="flex justify-end border-t p-6">
                    <Button :disabled="isSubmitting" size="lg" type="submit">
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        Save Appearance
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </AgentLayout>
</template>
