<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TMonthYearPanel from '@/Components/ui/TMonthYearPanel.vue';
import { toIsoDate, todayIso } from '@/utils/format';

const props = defineProps({
    // ISO date (any day within the month to display), e.g. '2026-08-01'.
    modelValue: { type: String, required: true },
    // Items must each expose a `date` field as an ISO 'YYYY-MM-DD' string.
    items: { type: Array, default: () => [] },
    // The day whose detail is open, so the grid can show which one it is.
    selected: { type: String, default: null },
    // Chips shown before a day collapses to "+N more". A busy day used to grow
    // the whole week's row until the month stopped fitting on screen.
    maxVisible: { type: Number, default: 3 },
    // Wording for the per-cell quick-add affordance.
    addLabel: { type: String, default: 'Add on this day' },
});

const emit = defineEmits(['update:modelValue', 'select', 'add']);

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Shared helpers, not a local copy. The panel beside this grid had its own
// idea of "today" built on toISOString(), which is UTC - so west of Greenwich
// the two disagreed by a day and the grid ringed a cell the panel wouldn't
// call today. One implementation removes the possibility.
const today = todayIso();

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
        const iso = toIsoDate(date);
        const dayItems = itemsByDate.value[iso] ?? [];
        days.push({
            iso,
            day: date.getDate(),
            inCurrentMonth: date.getMonth() === month,
            isToday: iso === today,
            isPast: iso < today,
            items: dayItems,
            visibleItems: dayItems.slice(0, props.maxVisible),
            hiddenCount: Math.max(0, dayItems.length - props.maxVisible),
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
    emit('update:modelValue', today);
    emit('select', today);
};

// — Month/year jump ----------------------------------------------------------
//
// Prev/Next alone meant a schedule two quarters out was six clicks away, and a
// year out was twelve. The title is now the control that gets you there.
const pickerOpen = ref(false);
const pickerRef = ref(null);

const onClickOutside = (event) => {
    if (pickerOpen.value && pickerRef.value && !pickerRef.value.contains(event.target)) {
        pickerOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));

// — Keyboard -----------------------------------------------------------------
//
// One tab stop for the whole grid (roving tabindex) rather than 42. Arrow keys
// walk days, Enter opens the day. Without this the calendar was unreachable
// from the keyboard entirely: the cells were plain divs.
const cellRefs = ref({});
const focusedIso = ref(props.selected ?? today);

const setCellRef = (iso) => (el) => {
    if (el) cellRefs.value[iso] = el;
    else delete cellRefs.value[iso];
};

watch(() => props.selected, (iso) => {
    if (iso) focusedIso.value = iso;
});

// Keeps the roving stop inside the visible month when the month changes.
watch(() => props.modelValue, () => {
    const monthPrefix = props.modelValue.slice(0, 7);
    if (!focusedIso.value.startsWith(monthPrefix)) {
        focusedIso.value = `${monthPrefix}-01`;
    }
});

const shiftDays = async (iso, delta) => {
    const date = new Date(`${iso}T00:00:00`);
    date.setDate(date.getDate() + delta);
    const nextIso = toIsoDate(date);
    focusedIso.value = nextIso;

    // Stepping off the end of the month pages the calendar rather than dropping
    // the focus into a cell that isn't rendered.
    if (nextIso.slice(0, 7) !== props.modelValue.slice(0, 7)) {
        emit('update:modelValue', nextIso);
    }

    await nextTick();
    cellRefs.value[nextIso]?.focus();
};

const onCellKeydown = (event, cell) => {
    const moves = {
        ArrowLeft: -1,
        ArrowRight: 1,
        ArrowUp: -7,
        ArrowDown: 7,
    };

    if (event.key in moves) {
        event.preventDefault();
        shiftDays(cell.iso, moves[event.key]);
        return;
    }
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('select', cell.iso);
    }
};
</script>

<template>
    <div class="rounded-xl border bg-surface">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b p-4">
            <!-- The title is a button: clicking it opens the month/year jump. -->
            <div ref="pickerRef" class="relative">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-semibold text-foreground transition-colors hover:bg-surface-hover"
                    :aria-expanded="pickerOpen"
                    aria-haspopup="dialog"
                    :title="`${monthLabel} — click to jump to another month or year`"
                    @click="pickerOpen = !pickerOpen"
                >
                    {{ monthLabel }}
                    <TIcon
                        name="chevronDown"
                        :size="14"
                        class="text-foreground-muted transition-transform"
                        :class="pickerOpen ? 'rotate-180' : ''"
                    />
                </button>

                <Transition
                    enter-active-class="transition duration-100 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-75 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="pickerOpen"
                        class="elevated-lg absolute left-0 z-50 mt-1 origin-top-left rounded-xl border bg-surface"
                        role="dialog"
                        aria-label="Jump to month or year"
                    >
                        <TMonthYearPanel
                            :model-value="modelValue"
                            @update:model-value="emit('update:modelValue', $event)"
                            @close="pickerOpen = false"
                        />
                    </div>
                </Transition>
            </div>

            <div class="flex items-center gap-1 text-sm">
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-foreground-secondary transition-colors hover:bg-surface-hover"
                    aria-label="Previous month"
                    title="Previous month"
                    @click="goToMonth(-1)"
                >
                    <TIcon name="chevronLeft" :size="16" />
                </button>
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-foreground-secondary transition-colors hover:bg-surface-hover"
                    @click="goToToday"
                >
                    Today
                </button>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-foreground-secondary transition-colors hover:bg-surface-hover"
                    aria-label="Next month"
                    title="Next month"
                    @click="goToMonth(1)"
                >
                    <TIcon name="chevronRight" :size="16" />
                </button>
            </div>
        </div>

        <div v-if="$slots.legend" class="flex justify-end border-b p-2">
            <slot name="legend" />
        </div>

        <p class="border-b px-4 py-1.5 text-xs text-foreground-muted">
            Click a day to open it alongside. Double-click to add to it. Arrow keys move between days.
        </p>

        <div class="grid grid-cols-7 border-b text-xs font-medium text-foreground-muted">
            <div v-for="label in WEEKDAY_LABELS" :key="label" class="p-2 text-center">{{ label }}</div>
        </div>

        <div class="divide-y divide-[rgb(var(--color-border))]" role="grid" :aria-label="monthLabel">
            <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7 divide-x divide-[rgb(var(--color-border))]" role="row">
                <!-- A div, not a button: the day's own items are buttons and
                     nesting those inside one is invalid. Given the role and the
                     roving tabindex it behaves like a grid cell regardless. -->
                <div
                    v-for="cell in week"
                    :key="cell.iso"
                    :ref="setCellRef(cell.iso)"
                    role="gridcell"
                    :tabindex="focusedIso === cell.iso ? 0 : -1"
                    :aria-selected="selected === cell.iso"
                    :aria-current="cell.isToday ? 'date' : undefined"
                    :aria-label="`${cell.iso}, ${cell.items.length} scheduled. ${addLabel} by double-clicking.`"
                    class="group relative min-h-[6.5rem] cursor-pointer p-1.5 align-top transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/50"
                    :class="[
                        cell.inCurrentMonth ? 'bg-surface' : 'bg-surface-hover text-foreground-muted',
                        selected === cell.iso ? 'ring-2 ring-inset ring-primary' : 'hover:bg-surface-hover',
                    ]"
                    @click="emit('select', cell.iso)"
                    @dblclick="emit('add', cell.iso)"
                    @keydown="onCellKeydown($event, cell)"
                >
                    <div class="mb-1 flex items-center gap-1">
                        <!-- Today is marked in the grid. The header had a "Today"
                             button but nothing showed which cell it meant. -->
                        <span
                            class="grid h-5 min-w-5 place-items-center rounded-full px-1 text-xs tabular-nums"
                            :class="cell.isToday
                                ? 'bg-primary font-semibold text-white'
                                : cell.isPast ? 'text-foreground-muted' : ''"
                        >
                            {{ cell.day }}
                        </span>
                    </div>

                    <slot
                        name="day"
                        :date="cell.iso"
                        :items="cell.visibleItems"
                        :all-items="cell.items"
                        :hidden-count="cell.hiddenCount"
                    />

                    <!-- Overflow is a control, not just a label: it opens the day
                         where the rest of them are listed. -->
                    <button
                        v-if="cell.hiddenCount"
                        type="button"
                        class="mt-1 w-full rounded px-1.5 py-0.5 text-left text-[11px] font-medium text-primary hover:bg-primary-soft"
                        @click.stop="emit('select', cell.iso)"
                    >
                        +{{ cell.hiddenCount }} more
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
