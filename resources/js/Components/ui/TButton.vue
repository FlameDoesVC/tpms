<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    loading: { type: Boolean, default: false },
    as: { type: [String, Object], default: 'button' },
});

// Solid variants carry a contact shadow plus a top rim highlight, and settle
// by 1px on press — that physicality is most of what "shading" buys.
const variantClasses = {
    primary: 'bg-primary text-white shadow-sm hover:bg-primary-hover hover:shadow-md active:translate-y-px active:shadow-xs focus-visible:ring-primary/30 [box-shadow:var(--shadow-sm),inset_0_1px_0_rgb(255_255_255/0.16)]',
    secondary: 'bg-surface border border-strong text-foreground shadow-xs hover:bg-surface-hover hover:shadow-sm active:translate-y-px focus-visible:ring-primary/30',
    ghost: 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground focus-visible:ring-primary/30',
    danger: 'bg-danger text-white shadow-sm hover:bg-danger-hover hover:shadow-md active:translate-y-px active:shadow-xs focus-visible:ring-danger/30 [box-shadow:var(--shadow-sm),inset_0_1px_0_rgb(255_255_255/0.16)]',
    accent: 'bg-accent text-accent-fg shadow-sm hover:bg-accent-hover hover:shadow-md active:translate-y-px active:shadow-xs focus-visible:ring-accent/30 [box-shadow:var(--shadow-sm),inset_0_1px_0_rgb(255_255_255/0.2)]',
};

const sizeClasses = {
    xs: 'px-2.5 py-1 text-xs gap-1',
    sm: 'px-3 py-1.5 text-sm gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
    lg: 'px-5 py-2.5 text-base gap-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center font-medium rounded-lg transition-colors duration-150',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-[rgb(var(--color-surface))]',
    'disabled:opacity-50 disabled:pointer-events-none',
    variantClasses[props.variant],
    sizeClasses[props.size],
]);

// `loading` must win over any explicitly-passed disabled, so it is bound after
// the attrs spread rather than folded into it.
const isDisabled = computed(() => props.loading || attrs.disabled === '' || !!attrs.disabled);
</script>

<template>
    <component
        :is="as"
        v-bind="$attrs"
        :class="classes"
        :disabled="as === 'button' ? isDisabled : undefined"
    >
        <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </component>
</template>
