<script setup lang="ts">
import { Button } from '@/shared/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { useAppearance } from '@/shared/composables/useAppearance';
import { Laptop, Moon, Sun } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const { appearance, updateAppearance } = useAppearance();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="h-9 w-9">
                <Sun v-if="appearance === 'light'" class="h-4 w-4" />
                <Moon v-else-if="appearance === 'dark'" class="h-4 w-4" />
                <Laptop v-else class="h-4 w-4" />
                <span class="sr-only">{{ t('theme.toggle') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="updateAppearance('light')" :disabled="appearance === 'light'">
                <Sun class="me-2 h-4 w-4" />
                <span>{{ t('theme.light') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="updateAppearance('dark')" :disabled="appearance === 'dark'">
                <Moon class="me-2 h-4 w-4" />
                <span>{{ t('theme.dark') }}</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="updateAppearance('system')" :disabled="appearance === 'system'">
                <Laptop class="me-2 h-4 w-4" />
                <span>{{ t('theme.system') }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
