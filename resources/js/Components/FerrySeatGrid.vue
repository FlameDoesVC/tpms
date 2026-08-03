<script setup>
defineProps({
    // [{ number, cells: [seatNumber | null] }] — one entry per deck row, one
    // cell per grid column, null where the deck has no seat. Driven by the
    // ferry's own layout rather than a fixed left/right split, so a boat with
    // an off-centre gangway or a tapered bow draws correctly at the gate.
    rows: { type: Array, required: true },
    // (seatNumber) => tailwind class string for that seat's current state.
    seatClass: { type: Function, required: true },
    // Omit for a read-only grid (plain divs); pass to make seats clickable.
    onSeatClick: { type: Function, default: null },
});
</script>

<template>
    <div class="mx-auto flex w-fit flex-col gap-1.5">
        <div v-for="row in rows" :key="row.number" class="flex items-center gap-1.5">
            <span class="w-4 shrink-0 text-right text-[10px] tabular-nums text-foreground-muted">
                {{ row.number }}
            </span>
            <div class="flex gap-1">
                <template v-for="(seat, c) in row.cells" :key="`${row.number}-${c}`">
                    <component
                        v-if="seat !== null"
                        :is="onSeatClick ? 'button' : 'div'"
                        type="button"
                        @click="onSeatClick?.(seat)"
                        class="flex h-6 w-6 items-center justify-center rounded text-[10px] font-medium"
                        :class="seatClass(seat)"
                    >
                        {{ seat }}
                    </component>
                    <!-- Holds the column so aisles and notches stay aligned. -->
                    <span v-else class="h-6 w-6" aria-hidden="true" />
                </template>
            </div>
        </div>
    </div>
</template>
