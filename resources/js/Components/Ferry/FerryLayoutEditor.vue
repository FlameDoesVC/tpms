<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TButton from '@/Components/ui/TButton.vue';

/**
 * Deck plan editor.
 *
 * The layout is a rectangle of cells - 'S' for a seat, '.' for empty space -
 * plus boarding points on the hull edges. Aisles aren't a separate concept: an
 * aisle is a column of empty cells, which is why an off-centre gangway, a
 * stairwell notch or a tapered bow all fall out of the same model instead of
 * each needing their own setting.
 *
 * Seats number row by row over the seat cells, so the numbering shown here is
 * exactly the numbering a passenger sees on their ticket.
 */
const model = defineModel({
    type: Object,
    default: () => ({ grid: [], entrances: [] }),
});

const props = defineProps({
    // Seats at or below this number are sold, so removing them is refused by
    // the server. Shown inline rather than saved-and-rejected.
    highestSoldSeat: { type: Number, default: 0 },
});

const SEAT = 'S';
const EMPTY = '.';
const MAX_ROWS = 40;
const MAX_COLS = 20;

// Whole decks, not column patterns: picking "2 + 2" should give you a usable
// boat immediately, which is the difference between a preset and a hint.
const PRESETS = [
    { key: '2-2', label: '2 + 2', hint: 'Small launch', row: 'SS.SS', rows: 6 },
    { key: '3-3', label: '3 + 3', hint: 'Mid-size', row: 'SSS.SSS', rows: 7 },
    { key: '4-4', label: '4 + 4', hint: 'Standard ferry', row: 'SSSS.SSSS', rows: 8 },
    { key: '2-3-2', label: '2 + 3 + 2', hint: 'Wide deck, twin aisles', row: 'SS.SSS.SS', rows: 8 },
];

const EDGE_LABELS = {
    bow: 'Bow',
    stern: 'Stern',
    port: 'Port',
    starboard: 'Starboard',
};

const grid = computed(() => model.value?.grid ?? []);
const entrances = computed(() => model.value?.entrances ?? []);
const rowCount = computed(() => grid.value.length);
const colCount = computed(() => (grid.value[0] ?? '').length);

const seatTotal = computed(() =>
    grid.value.reduce((sum, row) => sum + [...row].filter((c) => c === SEAT).length, 0)
);

// Row-major seat numbers, keyed by "row:col", so a cell can show the number a
// ticket for it would carry.
const seatNumbers = computed(() => {
    const map = {};
    let n = 0;
    grid.value.forEach((row, r) => {
        [...row].forEach((cell, c) => {
            if (cell === SEAT) map[`${r}:${c}`] = ++n;
        });
    });
    return map;
});

const commit = (next) => {
    model.value = { grid: next.grid ?? grid.value, entrances: next.entrances ?? entrances.value };
};

/** Drops entrances that no longer have an edge to sit on after a resize. */
const clampEntrances = (rows, cols) =>
    entrances.value.filter((e) =>
        e.index >= 1 && e.index <= (e.edge === 'bow' || e.edge === 'stern' ? cols : rows)
    );

const applyPreset = (preset) => {
    const next = Array.from({ length: preset.rows }, () => preset.row);
    commit({ grid: next, entrances: clampEntrances(preset.rows, preset.row.length) });
};

const setRows = (count) => {
    const rows = Math.max(1, Math.min(MAX_ROWS, count));
    const width = colCount.value || 5;
    const blank = EMPTY.repeat(width);
    const next = Array.from({ length: rows }, (_, i) => grid.value[i] ?? grid.value.at(-1) ?? blank);
    commit({ grid: next, entrances: clampEntrances(rows, width) });
};

const setCols = (count) => {
    const cols = Math.max(1, Math.min(MAX_COLS, count));
    const next = grid.value.map((row) =>
        row.length > cols ? row.slice(0, cols) : row + EMPTY.repeat(cols - row.length)
    );
    commit({ grid: next, entrances: clampEntrances(next.length, cols) });
};

const setCell = (r, c, value) => {
    if (grid.value[r]?.[c] === undefined) return;
    // Already right, so nothing to write. This is also what makes a drag cheap:
    // pointermove fires many times inside one cell, and only a real change
    // reaches the model.
    if (grid.value[r][c] === value) return;

    const next = [...grid.value];
    const cells = [...next[r]];
    cells[c] = value;
    next[r] = cells.join('');
    commit({ grid: next });
};

const toggleCell = (r, c) => setCell(r, c, grid.value[r][c] === SEAT ? EMPTY : SEAT);

// — Drag to paint ----------------------------------------------------------
//
// The whole stroke writes ONE value, decided by the cell you started on:
// begin on a seat and you're erasing, begin on empty space and you're placing.
// Toggling each cell as it's entered would flip-flop as soon as the pointer
// crossed a mixed area, so a single drag could both add and remove seats.
const painting = ref(false);
const paintValue = ref(SEAT);
const lastCell = ref(null);

/**
 * Paints every cell on the straight line between two samples (Bresenham).
 *
 * A pointer doesn't report every pixel it crosses - the browser coalesces
 * moves, so a quick drag can jump several cells between events and only the
 * cell landed in gets `pointerenter`. Without this, a fast stroke left holes:
 * the cell pressed and the cell released on, with the ones between untouched.
 */
const paintLine = (r0, c0, r1, c1) => {
    const dr = Math.abs(r1 - r0);
    const dc = Math.abs(c1 - c0);
    const stepR = r0 < r1 ? 1 : -1;
    const stepC = c0 < c1 ? 1 : -1;
    let err = dc - dr;
    let r = r0;
    let c = c0;

    for (;;) {
        setCell(r, c, paintValue.value);
        if (r === r1 && c === c1) break;
        const e2 = 2 * err;
        if (e2 > -dr) { err -= dr; c += stepC; }
        if (e2 < dc) { err += dc; r += stepR; }
    }
};

const startPaint = (r, c, event) => {
    // Primary button only; a right-click shouldn't start editing the deck.
    if (event.button !== 0) return;

    painting.value = true;
    paintValue.value = grid.value[r][c] === SEAT ? EMPTY : SEAT;
    lastCell.value = { r, c };
    setCell(r, c, paintValue.value);

    // Touch and pen get implicit pointer capture on the element that was
    // pressed, which would send every later event back to that one cell and
    // make the drag paint nothing. Releasing it lets the cells the finger
    // actually passes over receive pointerenter.
    if (event.pointerType !== 'mouse') {
        event.currentTarget?.releasePointerCapture?.(event.pointerId);
    }
};

const paintInto = (r, c) => {
    if (!painting.value) return;

    const from = lastCell.value;
    if (from) paintLine(from.r, from.c, r, c);
    else setCell(r, c, paintValue.value);

    lastCell.value = { r, c };
};

// Leaving the deck forgets where the stroke was, so coming back in starts from
// the cell re-entered instead of ruling a line across from wherever it left.
const forgetLastCell = () => {
    lastCell.value = null;
};

const stopPaint = () => {
    painting.value = false;
    lastCell.value = null;
};

// On window, not on the grid: a stroke that ends with the pointer outside the
// deck still has to finish, or the next hover would keep painting.
onMounted(() => {
    window.addEventListener('pointerup', stopPaint);
    window.addEventListener('pointercancel', stopPaint);
});
onBeforeUnmount(() => {
    window.removeEventListener('pointerup', stopPaint);
    window.removeEventListener('pointercancel', stopPaint);
});

// Bulk toggles are what stop this being tedious: a whole column becomes an
// aisle, or a whole row becomes a bench, in one click.
const rowIsFull = (r) => [...grid.value[r]].every((c) => c === SEAT);
const colIsFull = (c) => grid.value.every((row) => row[c] === SEAT);

const toggleRow = (r) => {
    const fill = rowIsFull(r) ? EMPTY : SEAT;
    const next = [...grid.value];
    next[r] = fill.repeat(colCount.value);
    commit({ grid: next });
};

const toggleCol = (c) => {
    const fill = colIsFull(c) ? EMPTY : SEAT;
    const next = grid.value.map((row) => {
        const cells = [...row];
        cells[c] = fill;
        return cells.join('');
    });
    commit({ grid: next });
};

const entranceAt = (edge, index) => entrances.value.find((e) => e.edge === edge && e.index === index) ?? null;

const toggleEntrance = (edge, index) => {
    const existing = entranceAt(edge, index);
    const next = existing
        ? entrances.value.filter((e) => !(e.edge === edge && e.index === index))
        : [...entrances.value, { edge, index, label: null }];
    commit({ entrances: next });
};

const renameEntrance = (edge, index, label) => {
    commit({
        entrances: entrances.value.map((e) =>
            e.edge === edge && e.index === index ? { ...e, label: label || null } : e
        ),
    });
};

// Would this cell's removal drop a seat that's already sold? Only meaningful
// for seats at or under the highest sold number.
const isSoldSeat = (r, c) => {
    const n = seatNumbers.value[`${r}:${c}`];
    return n !== undefined && n <= props.highestSoldSeat;
};

const rowIndexes = computed(() => Array.from({ length: rowCount.value }, (_, i) => i));
const colIndexes = computed(() => Array.from({ length: colCount.value }, (_, i) => i));
</script>

<template>
    <div class="space-y-4">
        <!-- Presets first: most boats are one of these, and starting from a real
             deck beats building one cell by cell. -->
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Start from</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in PRESETS"
                    :key="preset.key"
                    type="button"
                    class="rounded-lg border bg-surface px-3 py-2 text-left transition-colors hover:border-strong hover:bg-surface-hover"
                    @click="applyPreset(preset)"
                >
                    <span class="block text-sm font-medium text-foreground">{{ preset.label }}</span>
                    <span class="block text-xs text-foreground-muted">{{ preset.hint }}</span>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-4 border-t pt-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">Rows</label>
                <div class="flex items-stretch overflow-hidden rounded-lg border bg-surface shadow-xs">
                    <button type="button" class="grid w-8 place-items-center border-r text-foreground-muted hover:bg-surface-hover" @click="setRows(rowCount - 1)">
                        <TIcon name="minus" :size="14" />
                    </button>
                    <span class="grid h-9 w-10 place-items-center text-sm tabular-nums text-foreground">{{ rowCount }}</span>
                    <button type="button" class="grid w-8 place-items-center border-l text-foreground-muted hover:bg-surface-hover" @click="setRows(rowCount + 1)">
                        <TIcon name="plus" :size="14" />
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">Columns</label>
                <div class="flex items-stretch overflow-hidden rounded-lg border bg-surface shadow-xs">
                    <button type="button" class="grid w-8 place-items-center border-r text-foreground-muted hover:bg-surface-hover" @click="setCols(colCount - 1)">
                        <TIcon name="minus" :size="14" />
                    </button>
                    <span class="grid h-9 w-10 place-items-center text-sm tabular-nums text-foreground">{{ colCount }}</span>
                    <button type="button" class="grid w-8 place-items-center border-l text-foreground-muted hover:bg-surface-hover" @click="setCols(colCount + 1)">
                        <TIcon name="plus" :size="14" />
                    </button>
                </div>
            </div>

            <div class="ml-auto rounded-lg border bg-surface-sunken px-4 py-2 text-right">
                <p class="text-xs text-foreground-muted">Capacity</p>
                <p class="text-2xl font-semibold tabular-nums text-foreground">{{ seatTotal }}</p>
            </div>
        </div>

        <p class="text-xs text-foreground-muted">
            Click a cell to add or remove a seat, or <strong class="font-semibold text-foreground-secondary">drag
            across several</strong> — whether you're placing or clearing is set by the cell you start on. Use the
            row and column headers to fill or clear a whole line; an aisle is just a cleared column. Click a
            <TIcon name="logout" :size="12" class="inline align-text-bottom" />
            marker on the hull edge to put a boarding door there.
        </p>

        <!-- The deck. Bow at the top, matching the passenger-facing seat map. -->
        <div v-if="rowCount && colCount" class="overflow-x-auto rounded-xl border bg-surface-sunken p-4">
            <div class="mx-auto w-fit" @pointerleave="forgetLastCell">
                <!-- Bow edge: door markers, then the column headers -->
                <div class="flex items-center gap-1 pl-14">
                    <button
                        v-for="c in colIndexes"
                        :key="`bow-${c}`"
                        type="button"
                        class="grid h-5 w-8 place-items-center rounded transition-colors"
                        :class="entranceAt('bow', c + 1)
                            ? 'bg-accent text-accent-fg'
                            : 'text-foreground-muted/40 hover:bg-surface-hover hover:text-foreground'"
                        :title="entranceAt('bow', c + 1) ? 'Remove bow door' : 'Add a bow door here'"
                        @click="toggleEntrance('bow', c + 1)"
                    >
                        <TIcon name="logout" :size="12" class="-rotate-90" />
                    </button>
                </div>
                <p class="mb-1 pl-14 text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground-muted">Bow</p>

                <div class="flex items-center gap-1 pl-14">
                    <button
                        v-for="c in colIndexes"
                        :key="`col-${c}`"
                        type="button"
                        class="grid h-6 w-8 place-items-center rounded text-[11px] font-medium text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                        :title="colIsFull(c) ? 'Clear this column' : 'Fill this column with seats'"
                        @click="toggleCol(c)"
                    >
                        {{ c + 1 }}
                    </button>
                </div>

                <!-- Rows: port door, row header, cells, starboard door -->
                <div v-for="r in rowIndexes" :key="`row-${r}`" class="flex items-center gap-1">
                    <button
                        type="button"
                        class="grid h-8 w-5 place-items-center rounded transition-colors"
                        :class="entranceAt('port', r + 1)
                            ? 'bg-accent text-accent-fg'
                            : 'text-foreground-muted/40 hover:bg-surface-hover hover:text-foreground'"
                        :title="entranceAt('port', r + 1) ? 'Remove port door' : 'Add a port door here'"
                        @click="toggleEntrance('port', r + 1)"
                    >
                        <TIcon name="logout" :size="12" />
                    </button>

                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded text-[11px] font-medium text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                        :title="rowIsFull(r) ? 'Clear this row' : 'Fill this row with seats'"
                        @click="toggleRow(r)"
                    >
                        {{ r + 1 }}
                    </button>

                    <button
                        v-for="c in colIndexes"
                        :key="`cell-${r}-${c}`"
                        type="button"
                        draggable="false"
                        class="grid h-8 w-8 touch-none select-none place-items-center rounded-lg border text-[10px] font-semibold tabular-nums transition-colors"
                        :class="grid[r][c] === 'S'
                            ? (isSoldSeat(r, c)
                                ? 'border-warning bg-warning-soft text-warning'
                                : 'border-primary bg-primary-soft text-primary hover:bg-primary/15')
                            : 'border-dashed border-[rgb(var(--color-border-strong))] bg-surface text-transparent hover:bg-surface-hover'"
                        :title="grid[r][c] === 'S'
                            ? (isSoldSeat(r, c)
                                ? `Seat ${seatNumbers[`${r}:${c}`]} — already sold, removing it will be refused`
                                : `Seat ${seatNumbers[`${r}:${c}`]} — click, or drag across, to remove`)
                            : 'Empty — click, or drag across, to place seats'"
                        @pointerdown="startPaint(r, c, $event)"
                        @pointerenter="paintInto(r, c)"
                        @dragstart.prevent
                        @click="$event.detail === 0 && toggleCell(r, c)"
                    >
                        {{ seatNumbers[`${r}:${c}`] ?? '' }}
                    </button>

                    <button
                        type="button"
                        class="grid h-8 w-5 place-items-center rounded transition-colors"
                        :class="entranceAt('starboard', r + 1)
                            ? 'bg-accent text-accent-fg'
                            : 'text-foreground-muted/40 hover:bg-surface-hover hover:text-foreground'"
                        :title="entranceAt('starboard', r + 1) ? 'Remove starboard door' : 'Add a starboard door here'"
                        @click="toggleEntrance('starboard', r + 1)"
                    >
                        <TIcon name="logout" :size="12" class="rotate-180" />
                    </button>
                </div>

                <!-- Stern edge -->
                <p class="mt-1 pl-14 text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground-muted">Stern</p>
                <div class="flex items-center gap-1 pl-14">
                    <button
                        v-for="c in colIndexes"
                        :key="`stern-${c}`"
                        type="button"
                        class="grid h-5 w-8 place-items-center rounded transition-colors"
                        :class="entranceAt('stern', c + 1)
                            ? 'bg-accent text-accent-fg'
                            : 'text-foreground-muted/40 hover:bg-surface-hover hover:text-foreground'"
                        :title="entranceAt('stern', c + 1) ? 'Remove stern door' : 'Add a stern door here'"
                        @click="toggleEntrance('stern', c + 1)"
                    >
                        <TIcon name="logout" :size="12" class="rotate-90" />
                    </button>
                </div>
            </div>
        </div>

        <p v-if="highestSoldSeat > 0" class="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning-soft px-3 py-2 text-xs text-warning">
            <TIcon name="alert" :size="14" class="mt-0.5 shrink-0" />
            Seats 1–{{ highestSoldSeat }} are sold on this ferry (shown amber). The deck can be reshaped, but it
            can't end up with fewer than {{ highestSoldSeat }} seats or those tickets would point at a seat that
            no longer exists.
        </p>

        <!-- Doors get names so a manifest can say "board at the bow ramp". -->
        <div v-if="entrances.length" class="border-t pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                Boarding points ({{ entrances.length }})
            </p>
            <ul class="space-y-2">
                <li
                    v-for="entrance in entrances"
                    :key="`${entrance.edge}-${entrance.index}`"
                    class="flex flex-wrap items-center gap-2 rounded-lg border bg-surface px-3 py-2"
                >
                    <span class="grid h-6 w-6 place-items-center rounded bg-accent text-accent-fg">
                        <TIcon name="logout" :size="12" />
                    </span>
                    <span class="text-sm font-medium text-foreground">
                        {{ EDGE_LABELS[entrance.edge] }}
                        <span class="font-normal text-foreground-muted">
                            · {{ entrance.edge === 'bow' || entrance.edge === 'stern' ? 'column' : 'row' }}
                            {{ entrance.index }}
                        </span>
                    </span>
                    <input
                        :value="entrance.label ?? ''"
                        type="text"
                        maxlength="40"
                        placeholder="Name this door (optional)"
                        class="ml-auto w-56 rounded-lg border bg-surface px-2.5 py-1 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @input="renameEntrance(entrance.edge, entrance.index, $event.target.value)"
                    />
                    <TButton
                        variant="ghost"
                        size="xs"
                        :aria-label="`Remove the ${EDGE_LABELS[entrance.edge]} door`"
                        @click="toggleEntrance(entrance.edge, entrance.index)"
                    >
                        <TIcon name="trash" :size="14" />
                    </TButton>
                </li>
            </ul>
        </div>
    </div>
</template>
