<script lang="ts" setup>
import HeaderActions from '@/components/HeaderActions.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Building2, Clock, Globe, Mail, MapPin, Phone, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Profile {
    email: string | null;
    phone: string | null;
    website: string | null;
    address: string | null;
    opening_hours: string | null;
}

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    profile: Profile;
}

const props = defineProps<Props>();

const form = useForm({
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    website: props.profile.website ?? '',
    address: props.profile.address ?? '',
    opening_hours: props.profile.opening_hours ?? '',
});

const submit = () => {
    form.patch(route('agents.business-profile.update', { agent: props.agent.external_id }), {
        preserveScroll: true,
    });
};

const hasUnsavedChanges = computed(
    () =>
        form.email !== (props.profile.email ?? '') ||
        form.phone !== (props.profile.phone ?? '') ||
        form.website !== (props.profile.website ?? '') ||
        form.address !== (props.profile.address ?? '') ||
        form.opening_hours !== (props.profile.opening_hours ?? ''),
);

const hasFormErrors = computed(() => Object.keys(form.errors).length > 0);

// Whether anything is actually persisted server-side — controls whether the
// delete button shows. We deliberately look at props, not the form state, so
// editing a field doesn't make the button appear or disappear mid-edit.
const hasSavedProfile = computed(
    () =>
        !!props.profile.email ||
        !!props.profile.phone ||
        !!props.profile.website ||
        !!props.profile.address ||
        !!props.profile.opening_hours,
);

const deleteOpen = ref(false);
const deleteForm = useForm({});

const confirmDelete = () => {
    deleteForm.delete(route('agents.business-profile.destroy', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            form.reset();
            toast.success('Business profile deleted');
        },
        onError: () => toast.error('Could not delete the business profile'),
    });
};
</script>

<template>
    <AgentLayout :agent="agent" title="Business Profile">
        <template #header-actions>
            <HeaderActions
                :is-submitting="form.processing"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="form.recentlySuccessful"
                success-message="Business profile saved"
                :show-error-message="hasFormErrors"
                error-message="Please fix errors below"
                save-label="Save Changes"
                :save-icon="Save"
                form-id="business-profile-form"
            />
        </template>

        <form id="business-profile-form" @submit.prevent="submit" class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-3xl">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Building2 class="h-5 w-5" />
                            Business Profile
                        </CardTitle>
                        <CardDescription>
                            Real contact details for the business this agent represents. They are added to the agent's system prompt so it can answer
                            questions like "what's your email?" or "when are you open?" using true values instead of making them up. Leave a field
                            empty if it doesn't apply — the agent will say it isn't available rather than invent one.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="email">Email</Label>
                                    <div class="relative">
                                        <Mail class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            placeholder="hello@yourcompany.com"
                                            class="pl-9"
                                            :error="form.errors.email"
                                        />
                                    </div>
                                    <p v-if="form.errors.email" class="text-destructive text-sm">{{ form.errors.email }}</p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="phone">Phone</Label>
                                    <div class="relative">
                                        <Phone class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                        <Input
                                            id="phone"
                                            v-model="form.phone"
                                            placeholder="+1 555 123 4567"
                                            class="pl-9"
                                            :error="form.errors.phone"
                                        />
                                    </div>
                                    <p v-if="form.errors.phone" class="text-destructive text-sm">{{ form.errors.phone }}</p>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="website">Website</Label>
                                <div class="relative">
                                    <Globe class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        id="website"
                                        v-model="form.website"
                                        type="url"
                                        placeholder="https://yourcompany.com"
                                        class="pl-9"
                                        :error="form.errors.website"
                                    />
                                </div>
                                <p v-if="form.errors.website" class="text-destructive text-sm">{{ form.errors.website }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="address">Address</Label>
                                <div class="relative">
                                    <MapPin class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                    <Textarea
                                        id="address"
                                        v-model="form.address"
                                        rows="2"
                                        placeholder="123 Main Street, Suite 400, City, Country"
                                        class="pl-9"
                                        :error="form.errors.address"
                                    />
                                </div>
                                <p v-if="form.errors.address" class="text-destructive text-sm">{{ form.errors.address }}</p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="opening_hours">Opening hours</Label>
                                <div class="relative">
                                    <Clock class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                    <Textarea
                                        id="opening_hours"
                                        v-model="form.opening_hours"
                                        rows="4"
                                        placeholder="Mon–Fri 9am–6pm EST
Sat 10am–4pm EST
Closed Sundays and public holidays"
                                        class="pl-9"
                                        :error="form.errors.opening_hours"
                                    />
                                </div>
                                <p v-if="form.errors.opening_hours" class="text-destructive text-sm">{{ form.errors.opening_hours }}</p>
                            </div>

                            <div v-if="hasSavedProfile" class="border-border flex items-center justify-between border-t pt-6">
                                <div>
                                    <p class="text-sm font-medium">Delete business profile</p>
                                    <p class="text-muted-foreground text-sm">
                                        Clear every field above. The agent will stop offering these details and say they aren't available.
                                    </p>
                                </div>
                                <Button type="button" variant="destructive" @click="deleteOpen = true">
                                    <Trash2 class="mr-2 size-4" />
                                    Delete profile
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>

        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete business profile?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This clears the email, phone, website, address, and opening hours for this agent. The agent will no longer use these
                        values when answering — it will say the information isn't available. This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        Delete profile
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
