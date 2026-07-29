<script setup>
defineProps({
    to: { type: [String, Object], required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <router-link
        :to="to"
        class="inline-flex items-center gap-2 border-b-2 px-1 pt-1 text-sm font-medium transition-colors"
        :class="
            active
                ? 'border-primary text-primary'
                : 'border-transparent text-foreground-secondary hover:border-foreground-muted/30 hover:text-foreground'
        "
    >
        <slot />
    </router-link>
</template>
