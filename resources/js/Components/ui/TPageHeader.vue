<script setup>
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: null },
    // Set inside StaffLayout's header bar, which supplies its own spacing.
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-4"
        :class="compact ? '' : 'mb-6'"
    >
        <div class="flex min-w-0 items-center gap-3">
            <span
                v-if="icon"
                class="grid h-9 w-9 shrink-0 place-items-center rounded bg-primary-soft text-primary"
            >
                <TIcon :name="icon" :size="19" />
            </span>
            <div class="min-w-0">
                <h1
                    class="truncate font-semibold tracking-tight text-foreground"
                    :class="compact ? 'text-base' : 'text-xl'"
                >
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="truncate text-sm text-foreground-secondary">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
