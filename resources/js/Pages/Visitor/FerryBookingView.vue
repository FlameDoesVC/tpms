<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import SeatPickerModal from '@/Components/SeatPickerModal.vue';
import { useFerryStore } from '@/stores/ferry';
import { useHotelStore } from '@/stores/hotel';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';

const ferryStore = useFerryStore();
const hotelStore = useHotelStore();
const cart = useCartStore();
const auth = useAuthStore();

const cartedScheduleIds = ref([]);
const cartedDates = ref([]);
const selectedBookingKey = ref('');
const arrivalSchedules = ref([]);
const departureSchedules = ref([]);
const loadingSchedules = ref(false);
const showSeatPicker = ref(false);
const activeSchedule = ref(null);

// A multi-room cart purchase (quantity > 1) splits guests across several
// Booking rows server-side, but they all share one party - the seat count
// only needs to fit the whole party's total, not any single room's own
// guests_count, so one entry per cart hotel item is enough here.
const eligibleBookings = computed(() => {
    const confirmed = hotelStore.myBookings
        .filter((b) => b.status === 'confirmed')
        .map((b) => ({
            key: `booking-${b.id}`,
            bookingId: b.id,
            hotelCartItemId: null,
            label: `${b.reference_code} - ${b.room?.hotel?.name} (${b.check_in_date?.slice(0, 10)} to ${b.check_out_date?.slice(0, 10)})`,
            checkIn: b.check_in_date?.slice(0, 10),
            checkOut: b.check_out_date?.slice(0, 10),
            guestsCount: b.guests_count,
        }));

    const pending = cart.items
        .filter((item) => item.type === 'hotel')
        .map((item) => ({
            key: `cart-${item.id}`,
            bookingId: null,
            hotelCartItemId: item.id,
            label: `${item.hotelName} - ${item.roomType} (${item.guestsCount} guest${item.guestsCount === 1 ? '' : 's'}, in cart, ${item.checkIn} to ${item.checkOut})`,
            checkIn: item.checkIn,
            checkOut: item.checkOut,
            guestsCount: item.guestsCount,
        }));

    return [...confirmed, ...pending];
});
const hasEligibleBooking = computed(() => eligibleBookings.value.length > 0);
const selectedBooking = computed(() =>
    eligibleBookings.value.find((b) => b.key === selectedBookingKey.value) ?? null
);

// A multi-room booking's party shares one group_booking_id anchor (null on
// the anchor room itself) - resolve it so already-issued tickets for ANY
// room in the party are recognized, not just the one this hotel item
// happens to reference. Cart-pending hotel items have no real booking yet,
// so they can never already have tickets.
const partyAnchorId = computed(() => {
    const bookingId = selectedBooking.value?.bookingId;
    if (!bookingId) return null;
    const booking = hotelStore.myBookings.find((b) => b.id === bookingId);
    return booking ? (booking.group_booking_id ?? booking.id) : null;
});

// The backend only rejects a second purchase for the same party+date at
// submit time - this hides "Select Seats" up front so the page doesn't look
// like you can book a different boat on a date you've already ticketed. Keyed
// by date (not schedule id): a party only makes one trip per leg, so a
// second, different ferry on the same day is still a duplicate of that leg.
const alreadyBookedDates = computed(() => {
    const anchorId = partyAnchorId.value;
    const ticketDates = anchorId
        ? ferryStore.myTickets
              .filter((t) => (t.booking?.group_booking_id ?? t.booking?.id) === anchorId)
              .map((t) => (t.schedule?.departure_date ?? '').slice(0, 10))
        : [];
    return new Set([...ticketDates, ...cartedDates.value]);
});

const today = new Date().toISOString().slice(0, 10);
const upcomingTickets = computed(() =>
    ferryStore.myTickets
        .filter((t) => t.status === 'issued' && (t.schedule?.departure_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

// The trip's two legs are fixed by the hotel stay - arrival on check-in day,
// return on check-out day. Neither date is user-editable.
const columns = computed(() => [
    { key: 'arrival', title: 'Getting to the island', date: selectedBooking.value?.checkIn, schedules: arrivalSchedules.value },
    { key: 'departure', title: 'Coming back', date: selectedBooking.value?.checkOut, schedules: departureSchedules.value },
]);

const refreshSchedules = async () => {
    const booking = selectedBooking.value;
    if (!booking) {
        arrivalSchedules.value = [];
        departureSchedules.value = [];
        return;
    }

    loadingSchedules.value = true;
    try {
        [arrivalSchedules.value, departureSchedules.value] = await Promise.all([
            ferryStore.getSchedulesForDate(booking.checkIn),
            ferryStore.getSchedulesForDate(booking.checkOut),
        ]);
    } finally {
        loadingSchedules.value = false;
    }
};

watch(selectedBookingKey, () => {
    cartedScheduleIds.value = [];
    cartedDates.value = [];
    refreshSchedules();
});

// If the selected hotel item gets removed from the cart (directly, or via
// the cascade-delete when its dependent ferry ticket is removed), fall back
// to nothing selected rather than leaving a dangling reference.
watch(eligibleBookings, (list) => {
    if (selectedBookingKey.value && !list.some((b) => b.key === selectedBookingKey.value)) {
        selectedBookingKey.value = '';
    }
});

onMounted(() => {
    if (auth.isAuthenticated) {
        hotelStore.fetchMyBookings({ silent: true });
        ferryStore.fetchMyTickets({ silent: true });
    }
});

const fillPercent = (schedule) => {
    const capacity = schedule.ferry?.capacity || 1;
    return Math.round(((capacity - schedule.available_seats) / capacity) * 100);
};

const openSeatPicker = (schedule) => {
    activeSchedule.value = schedule;
    showSeatPicker.value = true;
};

const onAddedToCart = () => {
    if (activeSchedule.value) {
        cartedScheduleIds.value = [...cartedScheduleIds.value, activeSchedule.value.id];
        cartedDates.value = [...cartedDates.value, (activeSchedule.value.departure_date ?? '').slice(0, 10)];
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Ferry Schedules" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="auth.isAuthenticated" class="elevated rounded-xl border bg-surface p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-foreground">My Tickets</h3>
                        <router-link :to="{ name: 'ferry.my-tickets' }" class="text-sm text-primary hover:underline">
                            View all
                        </router-link>
                    </div>

                    <p v-if="upcomingTickets.length === 0" class="mt-2 text-sm text-foreground-muted">
                        No upcoming tickets.
                    </p>
                    <div v-else class="mt-3 space-y-2">
                        <div
                            v-for="ticket in upcomingTickets"
                            :key="ticket.id"
                            class="flex items-center justify-between rounded-lg bg-surface-hover px-3 py-2 text-sm text-foreground-secondary"
                        >
                            <span>
                                {{ ticket.schedule?.ferry?.name }} -
                                {{ ticket.schedule?.departure_date?.slice(0, 10) }} at {{ ticket.schedule?.departure_time }}
                                (seat {{ ticket.seat_number }})
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="hasEligibleBooking" class="elevated rounded-xl border bg-surface p-4">
                    <label class="block text-sm font-medium text-foreground-secondary">Hotel booking</label>
                    <select
                        v-model="selectedBookingKey"
                        class="mt-1 block w-full rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="" disabled>Select the hotel booking this trip is for</option>
                        <option v-for="b in eligibleBookings" :key="b.key" :value="b.key">
                            {{ b.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-foreground-muted">
                        Ferry departures are fixed to this booking's check-in and check-out dates. Picking a hotel
                        room still in your cart also removes this ticket if that room is removed later.
                    </p>
                </div>
                <div v-else class="rounded-xl bg-warning-soft p-4 text-sm text-warning">
                    You need a confirmed hotel booking, or a hotel room in your cart, to add a ferry ticket.
                    <router-link :to="{ name: 'hotels.index' }" class="font-medium underline">Browse hotels</router-link>
                </div>

                <div v-if="loadingSchedules" class="text-foreground-muted">Loading schedules...</div>

                <div v-else-if="selectedBooking" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-for="column in columns" :key="column.key">
                        <div class="mb-3 rounded-xl bg-primary-soft p-3">
                            <p class="text-sm font-semibold text-primary">{{ column.title }}</p>
                            <p class="text-xs text-primary">{{ column.date }} (fixed to your booking)</p>
                        </div>

                        <div v-if="column.schedules.length === 0" class="text-sm text-foreground-muted">
                            No departures scheduled for this date.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="schedule in column.schedules"
                                :key="schedule.id"
                                class="elevated rounded-xl border bg-surface p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-foreground">{{ schedule.ferry?.name }}</p>
                                        <p class="text-sm text-foreground-muted">
                                            {{ schedule.departure_time }} - {{ schedule.arrival_time }}
                                        </p>
                                    </div>
                                    <TButton
                                        :disabled="schedule.available_seats < selectedBooking.guestsCount || cartedScheduleIds.includes(schedule.id) || alreadyBookedDates.has(schedule.departure_date?.slice(0, 10))"
                                        @click="openSeatPicker(schedule)"
                                    >
                                        {{ cartedScheduleIds.includes(schedule.id) ? 'In Cart' : alreadyBookedDates.has(schedule.departure_date?.slice(0, 10)) ? 'Already Booked' : 'Select Seats' }}
                                    </TButton>
                                </div>

                                <div class="mt-3">
                                    <div class="h-2 w-full overflow-hidden rounded-sm bg-surface-hover">
                                        <div
                                            class="h-full rounded-sm"
                                            :class="fillPercent(schedule) > 90 ? 'bg-danger' : 'bg-primary'"
                                            :style="{ width: fillPercent(schedule) + '%' }"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-foreground-muted">
                                        {{ schedule.available_seats }} of {{ schedule.ferry?.capacity }} seats available
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <SeatPickerModal
            v-model:show="showSeatPicker"
            :schedule="activeSchedule"
            :booking-id="selectedBooking?.bookingId"
            :hotel-cart-item-id="selectedBooking?.hotelCartItemId"
            :seats-needed="selectedBooking?.guestsCount ?? 1"
            @added="onAddedToCart"
        />
    </AuthenticatedLayout>
</template>
