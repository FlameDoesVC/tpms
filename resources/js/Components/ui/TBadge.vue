<script setup>
defineProps({
    variant: { type: String, default: 'neutral' },
    size: { type: String, default: 'sm' },
    dot: { type: Boolean, default: false },
});

const variantClasses = {
    neutral: 'bg-surface-hover text-foreground-secondary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    primary: 'bg-primary-soft text-primary',
};

const dotClasses = {
    neutral: 'bg-foreground-muted',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    primary: 'bg-primary',
};

const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded font-medium"
        :class="[variantClasses[variant] ?? variantClasses.neutral, sizeClasses[size]]"
    >
        <span v-if="dot" class="h-1.5 w-1.5 rounded-full" :class="dotClasses[variant] ?? dotClasses.neutral" />
        <slot />
    </span>
</template>
