<script setup>
defineProps({
    to: { type: [String, Object], required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <router-link
        :to="to"
        class="block border-l-4 py-2 pe-4 ps-3 text-base font-medium transition-colors"
        :class="
            active
                ? 'border-primary bg-primary-soft text-primary'
                : 'border-transparent text-foreground-secondary hover:border-foreground-muted/30 hover:bg-surface-hover hover:text-foreground'
        "
    >
        <slot />
    </router-link>
</template>
