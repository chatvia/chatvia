<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { MessageSquare, PlusCircle, Trash2, AlertCircle, LoaderCircle } from 'lucide-vue-next';
import { usePage} from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();

interface Props {
    agent: any
}

const props = defineProps<Props>();

const showAddStarterDialog = ref(false);
const isSubmitting = ref(false);

const starterMessageForm = ref({
    text: ''
});

const starters = ref(page.props.starters);

const addStarterMessage = () => {
    axios.post(route('agents.starters.store', props.agent.id), { text: starterMessageForm.value.text }).then((response) => {
        showAddStarterDialog.value = false;

        starters.value.push({
            id: response.data.id,
            text: response.data.text
        });
    });
};

const removeStarter = (id) => {
    axios.delete(route('agents.starters.delete', { agent: props.agent.id, id })).then(() => {
        starters.value = starters.value.filter(text => text.id !== id);
    });
};
</script>

<template>
    <AgentLayout :agent="agent" title="Starters">
        <div class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Starters
                    </CardTitle>
                    <CardDescription>
                        Suggest conversation starters for your users
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium">Current Starters</h3>
                            <Button variant="outline" @click="showAddStarterDialog = true">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Add Starter
                            </Button>
                        </div>

                        <div class="rounded-md border border-neutral-200 dark:border-neutral-800">
                            <div v-if="starters.length === 0"
                                 class="p-4 text-center text-muted-foreground">
                                No starter messages yet
                            </div>
                            <div
                                v-for="message in starters"
                                :key="message.id"
                                class="flex items-center justify-between border-b border-neutral-200 p-3 last:border-0 dark:border-neutral-800"
                            >
                                <div class="flex items-center gap-3">
                                    <MessageSquare class="h-5 w-5 text-neutral-400" />
                                    <div class="font-medium">{{ message.text }}</div>
                                </div>
                                <Button
                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80"
                                    size="sm"
                                    variant="ghost"
                                    @click="removeStarter(message.id)"
                                >
                                    <span class="sr-only">Remove starter message</span>
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <Alert>
                            <AlertCircle class="h-4 w-4" />
                            <AlertTitle>Tip</AlertTitle>
                            <AlertDescription>
                                Good starter messages are specific questions that showcase your agent's capabilities.
                            </AlertDescription>
                        </Alert>
                    </div>
                </CardContent>
            </Card>
            </div>

            <!-- Add Starter ChatMessage Dialog -->
            <Dialog v-model:open="showAddStarterDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Starter Message</DialogTitle>
                        <DialogDescription>
                            Create a new conversation starter for your users.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="py-4">
                        <Label class="mb-2" for="starter-message">Message Text</Label>
                        <Textarea
                            id="starter-message"
                            v-model="starterMessageForm.text"
                            placeholder="E.g., How can I track my order?"
                            rows="3"
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="showAddStarterDialog = false">
                            Cancel
                        </Button>
                        <Button
                            :disabled="!starterMessageForm.text.trim() || isSubmitting"
                            @click="addStarterMessage"
                        >
                            <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            <PlusCircle v-else class="mr-2 h-4 w-4" />
                            Add Message
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AgentLayout>
</template>
