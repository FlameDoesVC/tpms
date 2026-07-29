<script setup>
import { computed, ref, watch } from 'vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useFerryStore } from '@/stores/ferry';
import { useCartStore } from '@/stores/cart';

const props = defineProps({
    schedule: { type: Object, default: null },
    // Exactly one of these identifies which stay this ticket is for - a real,
    // already-confirmed booking, or a hotel room still sitting in the cart.
    bookingId: { type: [Number, String], default: null },
    hotelCartItemId: { type: String, default: null },
    seatsNeeded: { type: Number, default: 1 },
});

const emit = defineEmits(['added']);

const show = defineModel('show', { type: Boolean, default: false });

const ferryStore = useFerryStore();
const cart = useCartStore();

const loading = ref(false);
const capacity = ref(0);
const pricePerSeat = ref(0);
const takenSeats = ref([]);
const selectedSeats = ref([]);
const paymentMethod = ref('online');

const rows = computed(() => Array.from({ length: Math.ceil(capacity.value / 8) }, (_, r) => {
    const left = [1, 2, 3, 4].map((n) => r * 8 + n).filter((n) => n <= capacity.value);
    const right = [5, 6, 7, 8].map((n) => r * 8 + n).filter((n) => n <= capacity.value);
    return { left, right };
}));

const totalPrice = computed(() => (props.seatsNeeded * pricePerSeat.value).toFixed(2));
const canConfirm = computed(() => selectedSeats.value.length === props.seatsNeeded);

const seatState = (seat) => {
    if (takenSeats.value.includes(seat)) return 'taken';
    if (selectedSeats.value.includes(seat)) return 'selected';
    return 'available';
};

const toggleSeat = (seat) => {
    if (takenSeats.value.includes(seat)) return;
    if (selectedSeats.value.includes(seat)) {
        selectedSeats.value = selectedSeats.value.filter((s) => s !== seat);
        return;
    }
    if (selectedSeats.value.length >= props.seatsNeeded) return;
    selectedSeats.value = [...selectedSeats.value, seat];
};

const reset = () => {
    selectedSeats.value = [];
    paymentMethod.value = 'online';
};

watch(show, async (visible) => {
    if (!visible || !props.schedule) return;
    reset();
    loading.value = true;
    try {
        const data = await ferryStore.getSeatMap(props.schedule.id);
        capacity.value = data.capacity;
        pricePerSeat.value = Number(data.price_per_seat);
        takenSeats.value = data.taken_seats;
    } finally {
        loading.value = false;
    }
});

// Nothing is actually purchased here - seats are only really locked in at
// checkout, where the real POST /api/ferry/tickets call happens.
const confirm = () => {
    cart.addItem({
        type: 'ferry',
        scheduleId: props.schedule.id,
        bookingId: props.bookingId,
        hotelCartItemId: props.hotelCartItemId,
        ferryName: props.schedule.ferry?.name,
        departureDate: props.schedule.departure_date?.slice(0, 10),
        departureTime: props.schedule.departure_time,
        seatNumbers: [...selectedSeats.value],
        pricePerSeat: pricePerSeat.value,
        paymentMethod: paymentMethod.value,
        subtotal: props.seatsNeeded * pricePerSeat.value,
    });
    emit('added');
    show.value = false;
};
</script>

<template>
    <TModal v-model:show="show" max-width="lg">
        <template #title>Choose your seats</template>

        <div v-if="loading" class="py-8 text-center text-sm text-foreground-muted">Loading seat map...</div>

        <div v-else-if="schedule" class="space-y-6">
            <p class="text-sm text-foreground-secondary">
                {{ schedule.ferry?.name }} - {{ schedule.departure_time }} - {{ schedule.arrival_time }}
            </p>
            <p class="text-sm font-medium text-foreground">
                Select {{ seatsNeeded }} seat{{ seatsNeeded === 1 ? '' : 's' }}
                ({{ selectedSeats.length }}/{{ seatsNeeded }} chosen)
            </p>

            <div class="rounded-lg bg-surface-hover p-4">
                <div class="mx-auto flex w-fit flex-col gap-2">
                    <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-4">
                        <div class="flex gap-1">
                            <button
                                v-for="seat in row.left"
                                :key="seat"
                                type="button"
                                :disabled="seatState(seat) === 'taken'"
                                @click="toggleSeat(seat)"
                                class="flex h-8 w-8 items-center justify-center rounded text-xs font-medium transition-colors"
                                :class="{
                                    'bg-foreground-muted/30 text-foreground-muted cursor-not-allowed': seatState(seat) === 'taken',
                                    'bg-primary text-white': seatState(seat) === 'selected',
                                    'bg-surface border text-foreground-secondary hover:border-primary': seatState(seat) === 'available',
                                }"
                            >
                                {{ seat }}
                            </button>
                        </div>
                        <div class="w-6"></div>
                        <div class="flex gap-1">
                            <button
                                v-for="seat in row.right"
                                :key="seat"
                                type="button"
                                :disabled="seatState(seat) === 'taken'"
                                @click="toggleSeat(seat)"
                                class="flex h-8 w-8 items-center justify-center rounded text-xs font-medium transition-colors"
                                :class="{
                                    'bg-foreground-muted/30 text-foreground-muted cursor-not-allowed': seatState(seat) === 'taken',
                                    'bg-primary text-white': seatState(seat) === 'selected',
                                    'bg-surface border text-foreground-secondary hover:border-primary': seatState(seat) === 'available',
                                }"
                            >
                                {{ seat }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-center gap-4 text-xs text-foreground-muted">
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded border bg-surface"></span> Available</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-primary"></span> Selected</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-foreground-muted/30"></span> Taken</span>
                </div>
            </div>

            <div class="space-y-3 border-t pt-4">
                <p class="text-sm font-medium text-foreground">Payment</p>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                        <input type="radio" v-model="paymentMethod" value="online" class="text-primary focus:ring-primary/30" />
                        Pay online now
                    </label>
                    <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                        <input type="radio" v-model="paymentMethod" value="cash" class="text-primary focus:ring-primary/30" />
                        Pay cash on board
                    </label>
                </div>

                <p v-if="paymentMethod === 'cash'" class="text-sm text-foreground-muted">
                    Have exact cash ready - the ferry operator will collect payment before boarding.
                </p>

                <p class="text-sm font-semibold text-foreground">Total: ${{ totalPrice }}</p>
            </div>
        </div>

        <template #footer>
            <TButton variant="secondary" @click="show = false">Cancel</TButton>
            <TButton :disabled="!canConfirm" @click="confirm">Add to Cart</TButton>
        </template>
    </TModal>
</template>
