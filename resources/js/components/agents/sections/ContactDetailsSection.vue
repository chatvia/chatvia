<script lang="ts" setup>
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { Clock, Globe, Mail, MapPin, Phone, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Profile {
    email: string | null;
    phone: string | null;
    website: string | null;
    address: string | null;
    opening_hours: string | null;
}

const props = withDefaults(
    defineProps<{
        agentId: string;
        profile: Profile;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const form = useForm({
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    website: props.profile.website ?? '',
    address: props.profile.address ?? '',
    opening_hours: props.profile.opening_hours ?? '',
});

const submit = () => {
    form.patch(route('agents.business-profile.update', { agent: props.agentId }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Contact details saved'),
        onError: () => toast.error('Please fix the errors below'),
    });
};

// Whether anything is actually persisted server-side — controls whether the
// delete button shows. Looks at props, not form state, so editing a field
// doesn't make the button appear or disappear mid-edit.
const hasSavedProfile = computed(
    () => !!props.profile.email || !!props.profile.phone || !!props.profile.website || !!props.profile.address || !!props.profile.opening_hours,
);

const deleteOpen = ref(false);
const deleteForm = useForm({});

const confirmDelete = () => {
    deleteForm.delete(route('agents.business-profile.destroy', { agent: props.agentId }), {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            form.reset();
            toast.success('Contact details deleted');
        },
        onError: () => toast.error('Could not delete the contact details'),
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <h3 class="text-sm font-medium">Contact</h3>
            <p class="text-muted-foreground mt-1 text-xs">
                If you want your agent to know about your business, add the details here. It shares them when visitors ask. Leave a field empty to
                skip it.
            </p>
        </div>

        <div class="grid max-w-3xl gap-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="contact-email">Email</Label>
                    <div class="relative">
                        <Mail class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="contact-email"
                            v-model="form.email"
                            type="email"
                            placeholder="hello@yourcompany.com"
                            class="pl-9"
                            :disabled="disabled"
                            :error="form.errors.email"
                        />
                    </div>
                    <p v-if="form.errors.email" class="text-destructive text-sm">{{ form.errors.email }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="contact-phone">Phone</Label>
                    <div class="relative">
                        <Phone class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            id="contact-phone"
                            v-model="form.phone"
                            placeholder="+1 555 123 4567"
                            class="pl-9"
                            :disabled="disabled"
                            :error="form.errors.phone"
                        />
                    </div>
                    <p v-if="form.errors.phone" class="text-destructive text-sm">{{ form.errors.phone }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="contact-website">Website</Label>
                <div class="relative">
                    <Globe class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        id="contact-website"
                        v-model="form.website"
                        type="url"
                        placeholder="https://yourcompany.com"
                        class="pl-9"
                        :disabled="disabled"
                        :error="form.errors.website"
                    />
                </div>
                <p v-if="form.errors.website" class="text-destructive text-sm">{{ form.errors.website }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="contact-address">Address</Label>
                <div class="relative">
                    <MapPin class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                    <Textarea
                        id="contact-address"
                        v-model="form.address"
                        rows="2"
                        placeholder="123 Main Street, Suite 400, City, Country"
                        class="pl-9"
                        :disabled="disabled"
                        :error="form.errors.address"
                    />
                </div>
                <p v-if="form.errors.address" class="text-destructive text-sm">{{ form.errors.address }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="contact-hours">Opening hours</Label>
                <div class="relative">
                    <Clock class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                    <Textarea
                        id="contact-hours"
                        v-model="form.opening_hours"
                        rows="4"
                        placeholder="Mon–Fri 9am–6pm&#10;Sat 10am–4pm&#10;Closed Sundays and public holidays"
                        class="pl-9"
                        :disabled="disabled"
                        :error="form.errors.opening_hours"
                    />
                </div>
                <p v-if="form.errors.opening_hours" class="text-destructive text-sm">{{ form.errors.opening_hours }}</p>
            </div>

            <div v-if="hasSavedProfile && !disabled" class="border-border flex items-center justify-between border-t pt-6">
                <div>
                    <p class="text-sm font-medium">Delete contact details</p>
                    <p class="text-muted-foreground text-sm">Clear every field above. The agent will stop offering these details.</p>
                </div>
                <Button type="button" variant="destructive" @click="deleteOpen = true">
                    <Trash2 class="mr-2 size-4" />
                    Delete
                </Button>
            </div>
        </div>

        <div v-if="!disabled" class="border-t pt-4">
            <Button type="submit" size="sm" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                {{ form.processing ? 'Saving...' : 'Save' }}
            </Button>
        </div>
    </form>

    <AlertDialog v-model:open="deleteOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete contact details?</AlertDialogTitle>
                <AlertDialogDescription>
                    This clears the email, phone, website, address, and opening hours for this agent. The agent will say the information isn't
                    available when asked. This cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="deleteForm.processing"
                    @click="confirmDelete"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
