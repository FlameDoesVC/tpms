<script setup>
defineProps({
    rows: { type: Array, required: true },
    // (seatNumber) => tailwind class string for that seat's current state.
    seatClass: { type: Function, required: true },
    // Omit for a read-only grid (plain divs); pass to make seats clickable.
    onSeatClick: { type: Function, default: null },
});
</script>

<template>
    <div class="mx-auto flex w-fit flex-col gap-1.5">
        <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-3">
            <div class="flex gap-1">
                <component
                    :is="onSeatClick ? 'button' : 'div'"
                    v-for="seat in row.left"
                    :key="seat"
                    type="button"
                    @click="onSeatClick?.(seat)"
                    class="flex h-6 w-6 items-center justify-center rounded text-[10px] font-medium"
                    :class="seatClass(seat)"
                >
                    {{ seat }}
                </component>
            </div>
            <div class="w-4"></div>
            <div class="flex gap-1">
                <component
                    :is="onSeatClick ? 'button' : 'div'"
                    v-for="seat in row.right"
                    :key="seat"
                    type="button"
                    @click="onSeatClick?.(seat)"
                    class="flex h-6 w-6 items-center justify-center rounded text-[10px] font-medium"
                    :class="seatClass(seat)"
                >
                    {{ seat }}
                </component>
            </div>
        </div>
    </div>
</template>
