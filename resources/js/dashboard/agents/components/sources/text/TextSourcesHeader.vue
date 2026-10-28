<script setup lang="ts">
/**
 * Header card of the Text sources page. Its "Add Text" menu either opens the
 * form for a new text (`create`) or the dialog that attaches a text the
 * workspace already trained (`attach`).
 */
import { Button } from '@/shared/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { ChevronDown, Link2, Plus, Type } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const emit = defineEmits<{
    create: [];
    attach: [];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Type class="h-5 w-5" />
                        {{ t('agents.sources.text.title') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('agents.sources.text.description') }}
                    </CardDescription>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            {{ t('agents.sources.text.add_text') }}
                            <ChevronDown class="ms-1 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64">
                        <DropdownMenuGroup>
                            <DropdownMenuItem class="cursor-pointer" @click="emit('create')">
                                <Plus class="me-2 h-4 w-4" />
                                {{ t('agents.sources.text.create_new') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="cursor-pointer" @click="emit('attach')">
                                <Link2 class="me-2 h-4 w-4" />
                                {{ t('agents.sources.text.already_trained') }}
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </CardHeader>
    </Card>
</template>
