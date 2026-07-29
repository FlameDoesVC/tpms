<script setup>
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    change: { type: String, default: null },
    trend: { type: String, default: null },
    icon: { type: String, default: null },
    // Fraction 0..1 — draws a capacity meter under the value.
    progress: { type: Number, default: null },
    tone: { type: String, default: 'primary' },
});

const toneRing = {
    primary: 'bg-primary-soft text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
};

const toneBar = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
};
</script>

<template>
    <div class="elevated rounded-xl border bg-surface p-5">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-foreground-secondary">{{ label }}</p>
            <span
                v-if="icon"
                class="grid h-8 w-8 shrink-0 place-items-center rounded"
                :class="toneRing[tone] ?? toneRing.primary"
            >
                <TIcon :name="icon" :size="17" />
            </span>
        </div>

        <!-- Tabular figures stop the number jittering as it updates. -->
        <p class="mt-2 text-3xl font-semibold tracking-tight text-foreground [font-variant-numeric:tabular-nums]">
            {{ value }}
        </p>

        <div v-if="progress !== null" class="mt-3 h-1.5 w-full overflow-hidden rounded-sm bg-surface-sunken">
            <div
                class="h-full rounded-sm transition-[width] duration-500"
                :class="toneBar[tone] ?? toneBar.primary"
                :style="{ width: `${Math.min(100, Math.max(0, progress * 100))}%` }"
            />
        </div>

        <p
            v-if="change"
            class="mt-2 flex items-center gap-1 text-sm"
            :class="trend === 'up' ? 'text-success' : trend === 'down' ? 'text-danger' : 'text-foreground-muted'"
        >
            <TIcon v-if="trend === 'up'" name="arrowRight" :size="15" class="-rotate-45" />
            <TIcon v-else-if="trend === 'down'" name="arrowRight" :size="15" class="rotate-45" />
            {{ change }}
        </p>
    </div>
</template>
