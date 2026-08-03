<script setup>
import { computed } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import { todayIso } from '@/utils/format';

/**
 * The selected day, shown as a rail beside the calendar.
 *
 * Beside, not beneath: as a panel under the grid it was off-screen on a normal
 * laptop, so every day you clicked meant a scroll down to read it and a scroll
 * back up to pick another. Alongside, the month and the day are legible at once
 * and clicking through days costs nothing.
 *
 * Always rendered, even with nothing selected - a rail that appears and
 * disappears would reflow the calendar next to it on every click.
 */
const props = defineProps({
    // ISO date, or null when nothing is selected.
    date: { type: String, default: null },
    count: { type: Number, default: 0 },
    addLabel: { type: String, required: true },
    emptyText: { type: String, default: 'Nothing scheduled for this day.' },
    // Shown when no day is selected at all.
    idleText: { type: String, default: 'Pick a day in the calendar to see what it holds.' },
});

defineEmits(['add']);

const weekday = computed(() =>
    props.date ? new Date(`${props.date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'long' }) : ''
);

const dayAndMonth = computed(() =>
    props.date
        ? new Date(`${props.date}T00:00:00`).toLocaleDateString(undefined, {
            day: 'numeric', month: 'long', year: 'numeric',
        })
        : ''
);

const isToday = computed(() => props.date === todayIso());
const isPast = computed(() => props.date && props.date < todayIso());
</script>

<template>
    <div class="elevated flex max-h-[calc(100vh-7rem)] flex-col overflow-hidden rounded-xl border bg-surface">
        <div class="shrink-0 border-b px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">Selected day</p>

            <template v-if="date">
                <div class="mt-1.5 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <h3 class="text-base font-semibold tracking-tight text-foreground">{{ weekday }}</h3>
                    <TBadge v-if="isToday" variant="primary" size="sm">Today</TBadge>
                    <!-- Backfilling a past day is legitimate, so this informs
                         rather than blocks - but it should never be a surprise. -->
                    <TBadge v-else-if="isPast" variant="neutral" size="sm">Past</TBadge>
                </div>
                <p class="text-sm text-foreground-secondary">{{ dayAndMonth }}</p>
                <p class="mt-0.5 text-xs text-foreground-muted">
                    {{ count === 0 ? 'Nothing scheduled' : `${count} scheduled` }}
                </p>

                <!-- Full width and unmissable, which is what lets the tiny
                     per-cell "+" go away entirely. -->
                <TButton size="sm" class="mt-3 w-full" @click="$emit('add')">
                    <TIcon name="plus" :size="15" />
                    {{ addLabel }}
                </TButton>
            </template>

            <p v-else class="mt-1.5 text-sm text-foreground-muted">{{ idleText }}</p>
        </div>

        <!-- Scrolls inside the rail, so a day with twenty sailings never pushes
             the calendar's own height around. -->
        <div v-if="date" class="min-h-0 flex-1 overflow-y-auto">
            <div v-if="count === 0" class="px-4 py-8 text-center">
                <span class="mx-auto grid h-9 w-9 place-items-center rounded-full bg-surface-hover text-foreground-muted">
                    <TIcon name="calendar" :size="17" />
                </span>
                <p class="mt-2 text-sm text-foreground-muted">{{ emptyText }}</p>
            </div>
            <div v-else class="divide-y divide-[rgb(var(--color-border))]">
                <slot />
            </div>
        </div>
    </div>
</template>
