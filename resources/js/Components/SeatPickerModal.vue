<script setup>
import { computed, ref, watch } from 'vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useFerryStore } from '@/stores/ferry';
import { useCartStore } from '@/stores/cart';
import { formatDate, formatMoney, formatTime } from '@/utils/format';

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

// Four seats either side of a central aisle, bow to stern. Row 1 is nearest
// the bow, which is how the deck is drawn below.
const SEATS_PER_ROW = 8;
const rows = computed(() => Array.from({ length: Math.ceil(capacity.value / SEATS_PER_ROW) }, (_, r) => {
    const base = r * SEATS_PER_ROW;
    return {
        number: r + 1,
        left: [1, 2, 3, 4].map((n) => base + n).filter((n) => n <= capacity.value),
        right: [5, 6, 7, 8].map((n) => base + n).filter((n) => n <= capacity.value),
    };
}));

const totalPrice = computed(() => props.seatsNeeded * pricePerSeat.value);
const canConfirm = computed(() => selectedSeats.value.length === props.seatsNeeded);
const seatsRemaining = computed(() => props.seatsNeeded - selectedSeats.value.length);
const freeSeatCount = computed(() => capacity.value - takenSeats.value.length);

const isFree = (seat) => !takenSeats.value.includes(seat);

const seatState = (seat) => {
    if (takenSeats.value.includes(seat)) return 'taken';
    if (selectedSeats.value.includes(seat)) return 'selected';
    return 'available';
};

const SEAT_CLASS = {
    taken: 'seat-taken cursor-not-allowed border-transparent bg-surface-hover text-foreground-muted/60',
    selected: 'border-primary bg-primary text-white shadow-sm',
    available: 'border-strong bg-surface text-foreground-secondary hover:border-primary hover:text-primary',
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

// The first run of free seats on one side of one row, so a party is seated
// together rather than scattered down the deck.
const runIn = (side, needed) => {
    for (let i = 0; i + needed <= side.length; i += 1) {
        const window = side.slice(i, i + needed);
        if (window.every(isFree)) return window;
    }
    return null;
};

const autoPick = () => {
    const needed = props.seatsNeeded;

    for (const row of rows.value) {
        const together = runIn(row.left, needed) ?? runIn(row.right, needed);
        if (together) {
            selectedSeats.value = together;
            return;
        }
    }

    // No block big enough: fall back to the first free seats anywhere, which is
    // still better than leaving the visitor to hunt for them one at a time.
    const picked = [];
    for (let seat = 1; seat <= capacity.value && picked.length < needed; seat += 1) {
        if (isFree(seat)) picked.push(seat);
    }
    selectedSeats.value = picked;
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
    <TModal v-model:show="show" max-width="5xl">
        <template #title>Choose your seats</template>

        <div v-if="loading" class="grid min-h-[60vh] place-items-center text-sm text-foreground-muted">Loading the deck plan…</div>

        <!-- Claims a real share of the viewport rather than sitting as a short
             band with its slack dumped underneath. The deck is the thing being
             looked at, so it gets the height and the seats get big enough to
             hit comfortably; the summary column holds its own scale beside it. -->
        <div v-else-if="schedule" class="grid gap-6 lg:min-h-[58vh] lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-10">
            <!-- The deck. Drawn as a hull with a tapered bow and portholes down
                 both sides, so "which end am I looking at" and "which seats are
                 by a window" are answered by the picture rather than a caption.
                 Centred in its pane and scrolling inside it, so a 25-seat launch
                 sits balanced and a 120-seat vessel doesn't push the summary
                 column off the bottom of the dialog. -->
            <div class="flex min-h-0 flex-col">
                <!-- Hull and legend centre as one unit; the legend reads the
                     deck, so it belongs under it rather than pinned to the
                     bottom of the column with a gap between them. -->
                <div class="flex flex-1 flex-col items-center justify-center gap-5 overflow-y-auto py-1">
                <!-- Hugs the seat grid rather than filling the pane: a hull is
                     as wide as its deck, and a stretched one stops reading as a
                     boat. The min-width keeps a small launch from looking like
                     a canoe. -->
                <div class="deck-hull relative w-fit min-w-[18rem] border-2 bg-surface-sunken px-3 pb-6 pt-14 sm:px-8">
                    <p class="mb-4 flex items-center justify-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground-muted">
                        <TIcon name="anchor" :size="13" /> Bow
                    </p>

                    <div class="mx-auto flex w-fit flex-col gap-2.5">
                        <div v-for="row in rows" :key="row.number" class="flex items-center gap-2 sm:gap-3">
                            <!-- Porthole, aligned to the row it lights. -->
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-strong bg-surface" aria-hidden="true" />

                            <div class="flex gap-2">
                                <button
                                    v-for="seat in row.left"
                                    :key="seat"
                                    type="button"
                                    :disabled="seatState(seat) === 'taken'"
                                    :aria-label="`Seat ${seat}, ${seatState(seat)}`"
                                    :aria-pressed="seatState(seat) === 'selected'"
                                    @click="toggleSeat(seat)"
                                    class="seat relative flex h-11 w-11 items-center justify-center rounded-lg border text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:h-12 sm:w-12 sm:text-sm"
                                    :class="SEAT_CLASS[seatState(seat)]"
                                >
                                    {{ seat }}
                                </button>
                            </div>

                            <span class="w-7 text-center text-xs tabular-nums text-foreground-muted sm:w-9">
                                {{ row.number }}
                            </span>

                            <div class="flex gap-2">
                                <button
                                    v-for="seat in row.right"
                                    :key="seat"
                                    type="button"
                                    :disabled="seatState(seat) === 'taken'"
                                    :aria-label="`Seat ${seat}, ${seatState(seat)}`"
                                    :aria-pressed="seatState(seat) === 'selected'"
                                    @click="toggleSeat(seat)"
                                    class="seat relative flex h-11 w-11 items-center justify-center rounded-lg border text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:h-12 sm:w-12 sm:text-sm"
                                    :class="SEAT_CLASS[seatState(seat)]"
                                >
                                    {{ seat }}
                                </button>
                            </div>

                            <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-strong bg-surface" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="perforation mt-5 opacity-60" aria-hidden="true" />
                    <p class="mt-2 text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-foreground-muted">
                        Stern · boarding
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-foreground-muted">
                    <span class="flex items-center gap-1.5">
                        <span class="seat relative h-4 w-4 rounded border border-strong bg-surface text-foreground-secondary" />
                        Available
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="seat relative h-4 w-4 rounded border border-primary bg-primary text-white" />
                        Yours
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="seat seat-taken relative h-4 w-4 rounded bg-surface-hover text-foreground-muted/60" />
                        Taken
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full border border-strong bg-surface" />
                        Window
                    </span>
                </div>
                </div>
            </div>

            <!-- Trip summary. Everything that decides whether these are the right
                 seats sits in one column, so nothing has to be remembered from
                 the other side of the deck.
                 Two parts, not four: the sailing, the seat picking and the
                 payment choice read as one continuous block at a steady rhythm,
                 and only the total is anchored to the bottom. Distributing the
                 slack across every block instead (justify-between) pushed them
                 apart from each other, which just made three small holes out of
                 one. -->
            <div class="flex flex-col border-t pt-5 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
                <div class="space-y-5">
                <div>
                    <p class="text-base font-semibold tracking-tight text-foreground">
                        {{ formatTime(schedule.departure_time) }} – {{ formatTime(schedule.arrival_time) }}
                    </p>
                    <p class="mt-0.5 text-sm text-foreground-secondary">{{ formatDate(schedule.departure_date) }}</p>
                    <p class="mt-1 inline-flex items-center gap-1.5 text-xs text-foreground-muted">
                        <TIcon name="ferry" :size="13" /> {{ schedule.ferry?.name }} · {{ freeSeatCount }} seats free
                    </p>
                </div>

                <div>
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-sm font-medium text-foreground">
                            {{ selectedSeats.length }} of {{ seatsNeeded }} chosen
                        </p>
                        <button
                            v-if="seatsRemaining > 0"
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            @click="autoPick"
                        >
                            Seat us together
                        </button>
                    </div>
                    <div class="mt-2 flex gap-1">
                        <span
                            v-for="n in seatsNeeded"
                            :key="n"
                            class="h-1.5 flex-1 rounded-full transition-colors"
                            :class="n <= selectedSeats.length ? 'bg-primary' : 'bg-surface-hover'"
                        />
                    </div>

                    <div v-if="selectedSeats.length" class="mt-3 flex flex-wrap gap-1.5">
                        <button
                            v-for="seat in selectedSeats"
                            :key="seat"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary-soft py-1 pl-2.5 pr-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/15"
                            :aria-label="`Release seat ${seat}`"
                            @click="toggleSeat(seat)"
                        >
                            Seat {{ seat }}
                            <TIcon name="x" :size="12" />
                        </button>
                    </div>
                    <p v-else class="mt-3 text-xs text-foreground-muted">
                        Tap a seat on the deck, or let us pick a block together.
                    </p>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-foreground">Payment</p>
                    <div class="space-y-2">
                        <label
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 text-sm transition-colors"
                            :class="paymentMethod === 'online' ? 'border-primary bg-primary-soft' : 'hover:border-strong hover:bg-surface-hover'"
                        >
                            <input type="radio" v-model="paymentMethod" value="online" class="mt-0.5 h-4 w-4 shrink-0 border-strong text-primary focus:ring-2 focus:ring-primary/20" />
                            <span>
                                <span class="block font-medium text-foreground">Pay online now</span>
                                <span class="block text-xs text-foreground-muted">Charged at checkout with the rest of your itinerary</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 text-sm transition-colors"
                            :class="paymentMethod === 'cash' ? 'border-primary bg-primary-soft' : 'hover:border-strong hover:bg-surface-hover'"
                        >
                            <input type="radio" v-model="paymentMethod" value="cash" class="mt-0.5 h-4 w-4 shrink-0 border-strong text-primary focus:ring-2 focus:ring-primary/20" />
                            <span>
                                <span class="block font-medium text-foreground">Pay cash on board</span>
                                <span class="block text-xs text-foreground-muted">Bring exact cash — the operator collects before boarding</span>
                            </span>
                        </label>
                    </div>
                </div>
                </div>

                <!-- `mt-auto` only once the column has a height to push against;
                     stacked on mobile it would collapse to nothing. -->
                <div class="mt-8 border-t pt-4 lg:mt-auto">
                    <div class="flex items-baseline justify-between text-sm text-foreground-secondary">
                        <span>{{ seatsNeeded }} × {{ formatMoney(pricePerSeat) }}</span>
                        <span class="text-lg font-semibold tracking-tight text-foreground">{{ formatMoney(totalPrice) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-foreground-muted">
                        {{ paymentMethod === 'cash' ? 'Due to the operator on board' : 'Due at checkout' }}
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <TButton variant="secondary" @click="show = false">Cancel</TButton>
            <TButton :disabled="!canConfirm" @click="confirm">
                {{ seatsRemaining > 0 ? `Pick ${seatsRemaining} more seat${seatsRemaining === 1 ? '' : 's'}` : 'Add to itinerary' }}
            </TButton>
        </template>
    </TModal>
</template>
