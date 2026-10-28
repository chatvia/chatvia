<script setup lang="ts">
/** "Chats by country" card of the analytics overview: a world map plus a dialog listing every country. */
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/shared/ui/dialog';
import WorldMap from '@/dashboard/agents/components/WorldMap.vue';
import { Globe2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    countries: Domain.Agent.Data.Resources.Stats.CountryUsageData[];
}>();

const { t } = useI18n();

const isCountryDialogOpen = ref(false);

const getFlagPath = (countryCode: string): string => {
    return `/images/flags/${countryCode.toLowerCase()}.svg`;
};
</script>

<template>
    <Card class="mb-6 overflow-hidden">
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <Globe2 class="h-5 w-5" />
                    {{ t('agents.analytics.chats_by_country') }}
                </CardTitle>
                <Dialog v-model:open="isCountryDialogOpen" v-if="countries?.length">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm">{{ t('agents.analytics.view_all') }}</Button>
                    </DialogTrigger>
                    <DialogContent class="flex max-h-[80vh] max-w-2xl flex-col overflow-hidden">
                        <DialogHeader>
                            <DialogTitle>{{ t('agents.analytics.all_countries') }}</DialogTitle>
                        </DialogHeader>
                        <div class="flex-1 overflow-y-auto pe-2">
                            <div class="space-y-3">
                                <div class="bg-background sticky top-0 z-10 flex items-center justify-between border-b pb-3">
                                    <div class="text-muted-foreground flex-1 text-sm font-medium">{{ t('agents.analytics.country') }}</div>
                                    <div class="text-muted-foreground w-32 text-end text-sm font-medium">
                                        {{ t('agents.analytics.progress') }}
                                    </div>
                                    <div class="text-muted-foreground w-16 text-end text-sm font-medium">
                                        {{ t('agents.analytics.chats') }}
                                    </div>
                                    <div class="text-muted-foreground w-16 text-end text-sm font-medium">
                                        {{ t('agents.analytics.percent') }}
                                    </div>
                                </div>

                                <div v-for="country in countries" :key="country.country_code" class="flex items-center justify-between gap-4 py-2">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <img
                                            :src="getFlagPath(country.country_code)"
                                            :alt="country.country_name"
                                            class="h-4 w-6 flex-shrink-0 rounded object-cover"
                                            onerror="this.style.display = 'none'"
                                        />
                                        <span class="truncate text-sm font-medium">{{ country.country_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="bg-muted h-2 w-32 overflow-hidden rounded-full">
                                            <div class="h-full bg-blue-600 transition-all" :style="{ width: `${country.percentage}%` }"></div>
                                        </div>
                                        <span class="w-16 text-end text-sm font-bold">{{ country.count }}</span>
                                        <span class="text-muted-foreground w-16 text-end text-sm">{{ Number(country.percentage).toFixed(1) }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </CardHeader>
        <CardContent class="overflow-hidden">
            <div v-if="!countries?.length" class="text-muted-foreground py-8 text-center">
                {{ t('agents.analytics.no_geo_data') }}
            </div>
            <div v-else class="overflow-hidden">
                <WorldMap :country-data="countries ?? []" />
            </div>
        </CardContent>
    </Card>
</template>
