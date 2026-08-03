<script setup>
// The staff-side counterpart to the visitor pages' search bar: a titled strip
// that says what the screen is working on and how much of it there is, with the
// controls that change that underneath. Management screens were each inventing
// their own arrangement of "count on the left, button on the right", which is
// why no two of them lined up.
defineProps({
    title: { type: String, required: true },
    // Live summary of what's on screen ("12 users", "4 of 9 sailings").
    summary: { type: String, default: null },
    // Sticks under the staff header while a long table scrolls. Off for
    // screens whose controls aren't worth the vertical space.
    sticky: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="elevated rounded-xl border bg-surface"
        :class="sticky ? 'sticky top-16 z-20' : ''"
    >
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b px-4 py-2">
            <div class="flex min-w-0 items-baseline gap-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">{{ title }}</h2>
                <p v-if="summary" class="truncate text-xs text-foreground-secondary">{{ summary }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Omitted entirely when a screen has no controls, so the strip
             collapses to a header rather than leaving an empty band. -->
        <div v-if="$slots.default" class="flex flex-wrap items-end gap-3 px-4 py-3">
            <slot />
        </div>
    </div>
</template>
