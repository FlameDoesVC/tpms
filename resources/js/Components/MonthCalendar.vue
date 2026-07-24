<script setup>
import { computed } from 'vue';

const props = defineProps({
    // ISO date (any day within the month to display), e.g. '2026-08-01'.
    modelValue: { type: String, required: true },
    // Items must each expose a `date` field as an ISO 'YYYY-MM-DD' string.
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const toIsoDate = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const currentMonthDate = computed(() => new Date(`${props.modelValue}T00:00:00`));

const monthLabel = computed(() => currentMonthDate.value.toLocaleDateString(undefined, {
    month: 'long',
    year: 'numeric',
}));

const itemsByDate = computed(() => {
    const map = {};
    for (const item of props.items) {
        (map[item.date] ??= []).push(item);
    }
    return map;
});

// Sunday-start 6x7 grid covering the full month plus leading/trailing days
// from adjacent months, so every row is a complete week.
const weeks = computed(() => {
    const year = currentMonthDate.value.getFullYear();
    const month = currentMonthDate.value.getMonth();

    const firstOfMonth = new Date(year, month, 1);
    const gridStart = new Date(year, month, 1 - firstOfMonth.getDay());

    const days = [];
    for (let i = 0; i < 42; i++) {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + i);
        days.push({
            iso: toIsoDate(date),
            day: date.getDate(),
            inCurrentMonth: date.getMonth() === month,
            items: itemsByDate.value[toIsoDate(date)] ?? [],
        });
    }

    const rows = [];
    for (let i = 0; i < days.length; i += 7) {
        rows.push(days.slice(i, i + 7));
    }
    return rows;
});

const goToMonth = (offset) => {
    const next = new Date(currentMonthDate.value);
    next.setMonth(next.getMonth() + offset);
    emit('update:modelValue', toIsoDate(next));
};

const goToToday = () => {
    emit('update:modelValue', toIsoDate(new Date()));
};
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 p-4">
            <h3 class="text-sm font-semibold text-gray-700">{{ monthLabel }}</h3>
            <div class="flex items-center gap-2 text-sm">
                <button type="button" class="rounded px-2 py-1 hover:bg-gray-100" @click="goToMonth(-1)">&lsaquo; Prev</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-gray-100" @click="goToToday">Today</button>
                <button type="button" class="rounded px-2 py-1 hover:bg-gray-100" @click="goToMonth(1)">Next &rsaquo;</button>
            </div>
        </div>

        <div v-if="$slots.legend" class="flex justify-end border-b border-gray-100 p-2">
            <slot name="legend" />
        </div>

        <div class="grid grid-cols-7 border-b border-gray-100 text-xs font-medium text-gray-500">
            <div v-for="label in WEEKDAY_LABELS" :key="label" class="p-2 text-center">{{ label }}</div>
        </div>

        <div class="divide-y divide-gray-100">
            <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7 divide-x divide-gray-100">
                <div
                    v-for="cell in week"
                    :key="cell.iso"
                    class="min-h-[6rem] p-1.5 align-top"
                    :class="cell.inCurrentMonth ? 'bg-white' : 'bg-gray-50 text-gray-400'"
                >
                    <div class="mb-1 text-xs">{{ cell.day }}</div>
                    <slot name="day" :date="cell.iso" :items="cell.items" />
                </div>
            </div>
        </div>
    </div>
</template>
