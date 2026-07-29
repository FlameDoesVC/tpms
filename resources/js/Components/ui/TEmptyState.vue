<script setup>
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: null },
    icon: { type: String, default: 'inbox' },
});

// Older callers pass ship/search/etc; map them onto the shared icon set so
// empty states can't drift away from the rest of the system.
const alias = { ship: 'ferry', bed: 'hotel', event: 'sparkle' };
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-surface-sunken/60 px-6 py-14 text-center">
        <div class="relative">
            <!-- Soft bloom behind the glyph so the state feels considered
                 rather than like a missing asset. -->
            <span class="absolute inset-0 -z-10 scale-150 rounded-full bg-primary/10 blur-xl" aria-hidden="true" />
            <span class="grid h-14 w-14 place-items-center rounded-full border bg-surface text-primary shadow-sm">
                <TIcon :name="alias[icon] ?? icon" :size="24" />
            </span>
        </div>
        <h3 class="mt-4 text-sm font-semibold text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-foreground-muted">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
