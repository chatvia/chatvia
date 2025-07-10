<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Moon, Sun, Laptop } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const currentIcon = computed(() => {
  switch (appearance.value) {
    case 'dark':
      return Moon;
    case 'light':
      return Sun;
    default:
      return Laptop;
  }
});
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="icon" class="h-9 w-9">
        <Sun v-if="appearance === 'light'" class="h-4 w-4" />
        <Moon v-else-if="appearance === 'dark'" class="h-4 w-4" />
        <Laptop v-else class="h-4 w-4" />
        <span class="sr-only">Toggle theme</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem @click="updateAppearance('light')" :disabled="appearance === 'light'">
        <Sun class="mr-2 h-4 w-4" />
        <span>Light</span>
      </DropdownMenuItem>
      <DropdownMenuItem @click="updateAppearance('dark')" :disabled="appearance === 'dark'">
        <Moon class="mr-2 h-4 w-4" />
        <span>Dark</span>
      </DropdownMenuItem>
      <DropdownMenuItem @click="updateAppearance('system')" :disabled="appearance === 'system'">
        <Laptop class="mr-2 h-4 w-4" />
        <span>System</span>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
