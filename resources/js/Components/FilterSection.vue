<script setup>
defineProps({
    title: { type: String, required: true },
    // Echoes the current value beside the heading ("2 nights", "Any price"), so
    // a section stays readable without scanning the controls inside it.
    hint: { type: String, default: null },
});
</script>

<template>
    <section class="px-4 py-4">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">{{ title }}</h3>
            <span v-if="hint" class="truncate text-xs text-foreground-secondary">{{ hint }}</span>
        </div>
        <slot />
    </section>
</template>
