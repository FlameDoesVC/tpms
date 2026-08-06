<script setup>
/**
 * The day's start times for one attraction, grouped into the three parts of a
 * day people plan around.
 *
 * Single-select, so each tile is a real radio input - the same native-radio
 * treatment TRadio and every other selector in the app uses.
 */
import { computed } from 'vue';
import { formatTime } from '@/utils/format';

const props = defineProps({
    slots: { type: Array, default: () => [] },
    selectedId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['select']);

const BANDS = [
    { key: 'morning', label: 'Morning', hint: 'before noon' },
    { key: 'afternoon', label: 'Afternoon', hint: 'noon – 5pm' },
    { key: 'evening', label: 'Evening', hint: 'after 5pm' },
];

const bandOf = (slotTime) => {
    const hour = Number(String(slotTime ?? '').split(':')[0]);
    if (Number.isNaN(hour)) return 'afternoon';
    if (hour < 12) return 'morning';
    if (hour < 17) return 'afternoon';
    return 'evening';
};

const grouped = computed(() =>
    BANDS.map((band) => ({
        ...band,
        slots: props.slots.filter((slot) => bandOf(slot.slot_time) === band.key),
    })).filter((band) => band.slots.length > 0)
);

const select = (slot) => {
    if (slot.available_capacity < 1) return;
    emit('select', slot);
};
</script>

<template>
    <div class="space-y-4">
        <div v-for="band in grouped" :key="band.key">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                {{ band.label }}
                <span class="font-normal normal-case tracking-normal">· {{ band.hint }}</span>
            </p>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                <label
                    v-for="slot in band.slots"
                    :key="slot.id"
                    class="flex items-center gap-2.5 rounded-lg border bg-surface p-2.5 transition-colors"
                    :class="[
                        selectedId === slot.id ? 'border-primary bg-primary-soft' : '',
                        slot.available_capacity < 1
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer hover:border-strong hover:bg-surface-hover',
                    ]"
                >
                    <input
                        type="radio"
                        name="event-slot"
                        :checked="selectedId === slot.id"
                        :disabled="slot.available_capacity < 1"
                        class="h-4 w-4 shrink-0 border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                        @change="select(slot)"
                    />
                    <span class="min-w-0">
                        <span
                            class="block text-sm font-semibold"
                            :class="selectedId === slot.id ? 'text-primary' : 'text-foreground'"
                        >
                            {{ formatTime(slot.slot_time) }}
                        </span>
                        <span
                            class="block text-xs"
                            :class="slot.available_capacity < 1
                                ? 'text-foreground-muted'
                                : slot.available_capacity > 2 ? 'text-success' : 'text-warning'"
                        >
                            {{ slot.available_capacity < 1 ? 'Full' : `${slot.available_capacity} left` }}
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</template>
