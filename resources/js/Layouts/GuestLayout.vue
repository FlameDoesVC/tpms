<script setup>
import BrandMark from '@/Components/BrandMark.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggle: toggleTheme } = useTheme();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-page pt-6 sm:justify-center sm:pt-0">
        <div class="flex items-center gap-4">
            <router-link to="/">
                <BrandMark :size="40" />
            </router-link>
            <button
                type="button"
                @click="toggleTheme"
                class="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
                <TIcon :name="isDark ? 'sun' : 'moon'" :size="19" />
            </button>
        </div>

        <div class="mt-8 w-full overflow-hidden rounded-xl border bg-surface px-6 py-6 shadow-sm sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
