<script setup>
/**
 * A link in the visitor top nav.
 *
 * This was a `border-b-2` underline - the stock starter-kit tab - while the staff
 * sidebar and the mobile nav both mark the current page with a primary-soft
 * fill. Three navs, two languages, and the flattest of them on the surface
 * visitors see most. It now carries the sidebar's treatment: soft fill and
 * primary ink when active, a surface tint on hover. Same tokens, same radius,
 * just laid out along the other axis.
 */
defineProps({
    to: { type: [String, Object], required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <router-link
        :to="to"
        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
        :class="
            active
                ? 'bg-primary-soft text-primary'
                : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'
        "
    >
        <slot />
    </router-link>
</template>
