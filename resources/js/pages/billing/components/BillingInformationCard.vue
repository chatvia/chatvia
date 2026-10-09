<script lang="ts" setup>
// Read-only view of the billing contact and address, with a button that opens
// the edit dialog.
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useI18n } from 'vue-i18n';
import type { BillingUser } from '../types';

defineProps<{
    user: BillingUser;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('billing.payment_methods.info_title') }}</CardTitle>
            <CardDescription>{{ t('billing.payment_methods.info_description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.name') }}</label>
                        <p class="text-muted-foreground mt-1 text-sm">{{ user.name || t('billing.payment_methods.not_set') }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.email') }}</label>
                        <p class="text-muted-foreground mt-1 text-sm">{{ user.email || t('billing.payment_methods.not_set') }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.address') }}</label>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ user.billing_address?.line1 || t('billing.payment_methods.not_set') }}
                            <span v-if="user.billing_address?.line2"> <br />{{ user.billing_address.line2 }} </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ t('billing.payment_methods.fields.city_state_country') }}</label>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{
                                [user.billing_address?.city, user.billing_address?.state, user.billing_address?.country].filter(Boolean).join(', ') ||
                                t('billing.payment_methods.not_set')
                            }}
                        </p>
                    </div>
                </div>
                <Separator />
                <Button @click="emit('edit')" variant="outline">{{ t('billing.payment_methods.update_info') }}</Button>
            </div>
        </CardContent>
    </Card>
</template>
