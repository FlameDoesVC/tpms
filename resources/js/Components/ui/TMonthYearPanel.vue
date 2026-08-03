<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { toIsoDate, todayIso } from '@/utils/format';

/**
 * Jump to a month, or to a year, in one or two clicks.
 *
 * A bare panel with no popover chrome, because its two hosts want to present it
 * differently: the schedule calendar hangs it under its title as a popover,
 * while TDatePicker swaps its day grid for it in place - nesting a popover
 * inside that one would overflow it.
 *
 * Drills month -> year the way native pickers do, so "March 2028" is two clicks
 * rather than twenty presses of Next.
 */
const props = defineProps({
    // ISO date; only its month and year are read.
    modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'close']);

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const YEARS_PER_PAGE = 12;

const viewed = computed(() => new Date(`${props.modelValue}T00:00:00`));
const today = new Date(`${todayIso()}T00:00:00`);

const mode = ref('month');
const cursorYear = ref(viewed.value.getFullYear());

// One year back and ten forward, rather than a decade-aligned block. Aligning
// to multiples of 12 put 2026 second-from-last on a page of mostly-past years,
// which is the wrong way round for work that is nearly always scheduled ahead.
const pageStartFor = (year) => year - 1;
const yearPageStart = ref(pageStartFor(viewed.value.getFullYear()));

// Reopening should always land on the month being looked at, not wherever the
// panel was left last time.
watch(() => props.modelValue, () => {
    cursorYear.value = viewed.value.getFullYear();
    yearPageStart.value = pageStartFor(viewed.value.getFullYear());
    mode.value = 'month';
});

const yearPage = computed(() =>
    Array.from({ length: YEARS_PER_PAGE }, (_, i) => yearPageStart.value + i)
);

const heading = computed(() =>
    mode.value === 'month'
        ? String(cursorYear.value)
        : `${yearPageStart.value} – ${yearPageStart.value + YEARS_PER_PAGE - 1}`
);

const step = (direction) => {
    if (mode.value === 'month') {
        cursorYear.value += direction;
        return;
    }
    yearPageStart.value += YEARS_PER_PAGE * direction;
};

const commit = (year, monthIndex) => {
    emit('update:modelValue', toIsoDate(new Date(year, monthIndex, 1)));
    emit('close');
};

const pickMonth = (monthIndex) => commit(cursorYear.value, monthIndex);

const pickYear = (year) => {
    cursorYear.value = year;
    mode.value = 'month';
};

// Relative jumps, because scheduling work is usually "a quarter out" rather than
// a date someone has in mind.
const jumpMonths = (count) => {
    const next = new Date(viewed.value);
    next.setMonth(next.getMonth() + count);
    commit(next.getFullYear(), next.getMonth());
};

const goToToday = () => commit(today.getFullYear(), today.getMonth());

const isViewedMonth = (monthIndex) =>
    cursorYear.value === viewed.value.getFullYear() && monthIndex === viewed.value.getMonth();
const isThisMonth = (monthIndex) =>
    cursorYear.value === today.getFullYear() && monthIndex === today.getMonth();

// — Keyboard: the grid is 4 wide, so left/right step one and up/down step four.
const cells = ref([]);
const setCellRef = (i) => (el) => { cells.value[i] = el; };

const onGridKeydown = async (event, index, length) => {
    const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -4, ArrowDown: 4 };
    if (!(event.key in moves)) return;

    event.preventDefault();
    const next = index + moves[event.key];
    if (next < 0 || next >= length) {
        // Stepping off the grid pages to the neighbouring year or year-page and
        // keeps going, rather than stopping dead at the edge.
        step(next < 0 ? -1 : 1);
        await nextTick();
        const wrapped = next < 0 ? length + next : next - length;
        cells.value[wrapped]?.focus();
        return;
    }
    cells.value[next]?.focus();
};
</script>

<template>
    <div class="w-[17rem] p-2" @keydown.esc.stop="$emit('close')">
        <div class="mb-1 flex items-center justify-between gap-1">
            <button
                type="button"
                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                :aria-label="mode === 'month' ? 'Previous year' : 'Earlier years'"
                @click="step(-1)"
            >
                <TIcon name="chevronLeft" :size="16" />
            </button>

            <!-- The heading is the drill-up control: click the year to pick a
                 different one. -->
            <button
                v-if="mode === 'month'"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-semibold text-foreground transition-colors hover:bg-surface-hover"
                aria-label="Choose a year"
                @click="mode = 'year'"
            >
                {{ heading }}
                <TIcon name="chevronDown" :size="14" class="text-foreground-muted" />
            </button>
            <span v-else class="px-2 py-1 text-sm font-semibold text-foreground">{{ heading }}</span>

            <button
                type="button"
                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                :aria-label="mode === 'month' ? 'Next year' : 'Later years'"
                @click="step(1)"
            >
                <TIcon name="chevronRight" :size="16" />
            </button>
        </div>

        <div v-if="mode === 'month'" class="grid grid-cols-4 gap-1">
            <button
                v-for="(label, i) in MONTHS_SHORT"
                :key="label"
                :ref="setCellRef(i)"
                type="button"
                class="rounded-lg px-2 py-2 text-sm font-medium transition-colors"
                :class="isViewedMonth(i)
                    ? 'bg-primary text-white'
                    : isThisMonth(i)
                        ? 'bg-primary-soft text-primary hover:bg-primary/15'
                        : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                :aria-current="isViewedMonth(i) ? 'true' : undefined"
                @click="pickMonth(i)"
                @keydown="onGridKeydown($event, i, 12)"
            >
                {{ label }}
            </button>
        </div>

        <div v-else class="grid grid-cols-4 gap-1">
            <button
                v-for="(year, i) in yearPage"
                :key="year"
                :ref="setCellRef(i)"
                type="button"
                class="rounded-lg px-2 py-2 text-sm font-medium transition-colors"
                :class="year === viewed.getFullYear()
                    ? 'bg-primary text-white'
                    : year === today.getFullYear()
                        ? 'bg-primary-soft text-primary hover:bg-primary/15'
                        : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                @click="pickYear(year)"
                @keydown="onGridKeydown($event, i, YEARS_PER_PAGE)"
            >
                {{ year }}
            </button>
        </div>

        <div class="mt-2 flex items-center gap-1 border-t pt-2">
            <button
                type="button"
                class="rounded-lg px-2 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary-soft"
                @click="goToToday"
            >
                Today
            </button>
            <span class="text-foreground-muted/50" aria-hidden="true">·</span>
            <button
                v-for="jump in [{ label: '+3m', months: 3 }, { label: '+6m', months: 6 }, { label: '+1y', months: 12 }]"
                :key="jump.label"
                type="button"
                class="rounded-lg px-2 py-1 text-xs font-medium text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
                :aria-label="`Jump forward ${jump.months} months`"
                @click="jumpMonths(jump.months)"
            >
                {{ jump.label }}
            </button>
        </div>
    </div>
</template>
