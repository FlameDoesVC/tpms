<script setup>
/**
 * The wordmark lockup: logo plus "TPMS." with the accent full stop.
 *
 * It existed only in the staff sidebar, so the visitor nav showed a bare logo
 * and the auth screens showed a bigger bare logo - three surfaces, three
 * different brands. One component instead, with the wordmark optional because
 * the sidebar hides it when collapsed.
 *
 * The landing hero keeps its own hand-written wordmark on purpose: that one sits
 * on open water and has to be white in both themes, where this reads off the
 * foreground token.
 */
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    size: { type: [String, Number], default: 32 },
    wordmark: { type: Boolean, default: true },
});
</script>

<template>
    <span class="inline-flex items-center gap-2.5 overflow-hidden">
        <ApplicationLogo :size="size" class="shrink-0" />
        <span v-if="wordmark" class="truncate text-base font-bold tracking-tight text-foreground">
            TPMS<span class="text-accent">.</span>
        </span>
    </span>
</template>
