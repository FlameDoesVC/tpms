<script setup>
import { computed, ref, watch } from 'vue';
import AppModal from '@/Components/ui/AppModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
    <AppModal v-model:show="show" max-width="lg">
        <template #title>Choose your seats</template>

        <div v-if="loading" class="py-8 text-center text-sm text-gray-500">Loading seat map...</div>

        <div v-else-if="schedule" class="space-y-6">
            <p class="text-sm text-gray-600">
                {{ schedule.ferry?.name }} - {{ schedule.departure_time }} - {{ schedule.arrival_time }}
            </p>
            <p class="text-sm font-medium text-gray-900">
                Select {{ seatsNeeded }} seat{{ seatsNeeded === 1 ? '' : 's' }}
                ({{ selectedSeats.length }}/{{ seatsNeeded }} chosen)
            </p>

            <div class="rounded-lg bg-gray-50 p-4">
                <div class="mx-auto flex w-fit flex-col gap-2">
                    <div v-for="(row, i) in rows" :key="i" class="flex items-center gap-4">
                        <div class="flex gap-1">
                            <button
                                v-for="seat in row.left"
                                :key="seat"
                                type="button"
                                :disabled="seatState(seat) === 'taken'"
                                @click="toggleSeat(seat)"
                                class="flex h-8 w-8 items-center justify-center rounded text-xs font-medium"
                                :class="{
                                    'bg-gray-300 text-gray-400 cursor-not-allowed': seatState(seat) === 'taken',
                                    'bg-indigo-600 text-white': seatState(seat) === 'selected',
                                    'bg-white border border-gray-300 text-gray-700 hover:border-indigo-400': seatState(seat) === 'available',
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
                                class="flex h-8 w-8 items-center justify-center rounded text-xs font-medium"
                                :class="{
                                    'bg-gray-300 text-gray-400 cursor-not-allowed': seatState(seat) === 'taken',
                                    'bg-indigo-600 text-white': seatState(seat) === 'selected',
                                    'bg-white border border-gray-300 text-gray-700 hover:border-indigo-400': seatState(seat) === 'available',
                                }"
                            >
                                {{ seat }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-center gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-white border border-gray-300"></span> Available</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-indigo-600"></span> Selected</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-300"></span> Taken</span>
                </div>
            </div>

            <div class="space-y-3 border-t border-gray-200 pt-4">
                <p class="text-sm font-medium text-gray-900">Payment</p>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" v-model="paymentMethod" value="online" />
                        Pay online now
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" v-model="paymentMethod" value="cash" />
                        Pay cash on board
                    </label>
                </div>

                <p v-if="paymentMethod === 'cash'" class="text-sm text-gray-500">
                    Have exact cash ready - the ferry operator will collect payment before boarding.
                </p>

                <p class="text-sm font-semibold text-gray-900">Total: ${{ totalPrice }}</p>
            </div>
        </div>

        <template #footer>
            <button type="button" @click="show = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                Cancel
            </button>
            <PrimaryButton :disabled="!canConfirm" @click="confirm">
                Add to Cart
            </PrimaryButton>
        </template>
    </AppModal>
</template>
