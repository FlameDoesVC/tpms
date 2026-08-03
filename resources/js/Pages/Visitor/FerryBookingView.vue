<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import PromotionsStrip from '@/Components/PromotionsStrip.vue';
import { formatDate, formatDateRange, formatDateTime, formatMoney, formatTime, nightsBetween, todayIso } from '@/utils/format';
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

// A multi-room purchase (quantity > 1) splits guests_count evenly across
// several Booking rows server-side, linked by group_booking_id - a single
// row's own guests_count understates the party (see Booking::partyGuestsCount
// on the backend). Group by that anchor so a 3-room booking becomes one
// eligible option with the true total, not three options each showing a
// fraction of it. party_guests_count comes from the API (BookingController);
// summing guests_count across the group is an exact fallback if it's ever
// missing, since every sibling row belongs to this same visitor.
const eligibleBookings = computed(() => {
    const partiesByAnchor = new Map();
    for (const b of hotelStore.myBookings) {
        if (b.status !== 'confirmed') continue;
        const anchorId = b.group_booking_id ?? b.id;
        if (!partiesByAnchor.has(anchorId)) partiesByAnchor.set(anchorId, []);
        partiesByAnchor.get(anchorId).push(b);
    }

    const confirmed = Array.from(partiesByAnchor.entries()).map(([anchorId, rooms]) => {
        const anchor = rooms.find((b) => b.id === anchorId) ?? rooms[0];
        const guestsCount = anchor.party_guests_count ?? rooms.reduce((sum, b) => sum + (b.guests_count ?? 0), 0);
        const checkIn = anchor.check_in_date?.slice(0, 10);
        const checkOut = anchor.check_out_date?.slice(0, 10);
        return {
            key: `booking-${anchorId}`,
            bookingId: anchorId,
            hotelCartItemId: null,
            confirmed: true,
            hotelName: anchor.room?.hotel?.name ?? 'Your stay',
            detail: anchor.reference_code,
            roomsLabel: rooms.length > 1 ? `${rooms.length} rooms` : (anchor.room?.type ?? '1 room'),
            label: `${anchor.reference_code} - ${anchor.room?.hotel?.name} (${checkIn} to ${checkOut})`,
            checkIn,
            checkOut,
            guestsCount,
        };
    });

    // Rooms for one stay can be spread over several cart rows (a party split
    // across room types), each carrying only the guests it holds. Grouped by
    // hotel and dates so the picker offers one trip needing the party's full
    // seat count, not one trip per room type each short of it. The server
    // links these rows into a single booking party at checkout, so any one of
    // their ids resolves to the whole group.
    const staysByKey = new Map();
    for (const item of cart.items) {
        if (item.type !== 'hotel') continue;
        const key = `${item.hotelId}|${item.checkIn}|${item.checkOut}`;
        if (!staysByKey.has(key)) staysByKey.set(key, []);
        staysByKey.get(key).push(item);
    }

    const pending = Array.from(staysByKey.values()).map((rows) => {
        const first = rows[0];
        const guestsCount = rows.reduce((sum, r) => sum + (r.guestsCount ?? 0), 0);
        const roomCount = rows.reduce((sum, r) => sum + (r.quantity ?? 0), 0);
        const roomsLabel = rows.length > 1
            ? `${roomCount} rooms across ${rows.length} types`
            : `${roomCount} ${first.roomType}`;

        return {
            key: `cart-${first.id}`,
            bookingId: null,
            hotelCartItemId: first.id,
            confirmed: false,
            hotelName: first.hotelName,
            detail: 'In your itinerary',
            roomsLabel,
            label: `${first.hotelName} - ${roomsLabel} (${guestsCount} guests, in cart, ${first.checkIn} to ${first.checkOut})`,
            checkIn: first.checkIn,
            checkOut: first.checkOut,
            guestsCount,
        };
    });

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

// Dates this party already holds a real, issued ticket for.
const ticketedDates = computed(() => {
    const anchorId = partyAnchorId.value;
    if (!anchorId) return new Set();
    return new Set(
        ferryStore.myTickets
            .filter((t) => (t.booking?.group_booking_id ?? t.booking?.id) === anchorId)
            .map((t) => (t.schedule?.departure_date ?? '').slice(0, 10))
    );
});

// The backend only rejects a second purchase for the same party+date at
// submit time - this hides "Select Seats" up front so the page doesn't look
// like you can book a different boat on a date you've already ticketed. Keyed
// by date (not schedule id): a party only makes one trip per leg, so a
// second, different ferry on the same day is still a duplicate of that leg.
const alreadyBookedDates = computed(() => new Set([...ticketedDates.value, ...cartedDates.value]));

const today = todayIso();
const upcomingTickets = computed(() =>
    ferryStore.myTickets
        .filter((t) => t.status === 'issued' && (t.schedule?.departure_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

// The trip's two legs are fixed by the hotel stay - arrival on check-in day,
// return on check-out day. Neither date is user-editable.
const legs = computed(() => [
    {
        key: 'arrival',
        title: 'Out to the island',
        caption: 'Arrive on your check-in day',
        icon: 'arrowRight',
        date: selectedBooking.value?.checkIn,
        schedules: arrivalSchedules.value,
    },
    {
        key: 'departure',
        title: 'Back to the mainland',
        caption: 'Leave on your check-out day',
        icon: 'arrowLeft',
        date: selectedBooking.value?.checkOut,
        schedules: departureSchedules.value,
    },
].map((leg) => ({
    ...leg,
    status: !leg.date ? 'none'
        : ticketedDates.value.has(leg.date) ? 'booked'
        : cartedDates.value.includes(leg.date) ? 'in-cart'
        : 'needed',
})));

const legsSettled = computed(() => legs.value.filter((l) => l.status !== 'needed' && l.status !== 'none').length);

const STATUS_TEXT = {
    booked: 'Ticket issued',
    'in-cart': 'In your itinerary',
    needed: 'Seats not chosen',
    none: '',
};

// Older schedules may predate the status column; treat a missing value as
// bookable so they don't silently vanish from the page.
const bookableSchedule = (schedule) => (schedule.status ?? 'scheduled') === 'scheduled';

const refreshSchedules = async () => {
    const booking = selectedBooking.value;
    if (!booking) {
        arrivalSchedules.value = [];
        departureSchedules.value = [];
        return;
    }

    loadingSchedules.value = true;
    try {
        const [arrival, departure] = await Promise.all([
            ferryStore.getSchedulesForDate(booking.checkIn),
            ferryStore.getSchedulesForDate(booking.checkOut),
        ]);
        // Filtered here rather than in the store: staff pages share this
        // endpoint and need cancelled/departed rows. A cancelled sailing keeps
        // its seat count, so without this it renders with a live Select Seats
        // button (the server now refuses the purchase either way).
        arrivalSchedules.value = arrival.filter(bookableSchedule);
        departureSchedules.value = departure.filter(bookableSchedule);
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
// to nothing selected rather than leaving a dangling reference. A single
// remaining stay is selected outright: there is nothing to choose between,
// and making the visitor pick it before any sailing appears is a dead step.
watch(eligibleBookings, (list) => {
    if (selectedBookingKey.value && !list.some((b) => b.key === selectedBookingKey.value)) {
        selectedBookingKey.value = '';
    }
    if (!selectedBookingKey.value && list.length === 1) {
        selectedBookingKey.value = list[0].key;
    }
}, { immediate: true });

onMounted(async () => {
    if (auth.isAuthenticated) {
        await Promise.allSettled([
            hotelStore.fetchMyBookings({ silent: true }),
            ferryStore.fetchMyTickets({ silent: true }),
        ]);
    }
});

const seatsNeeded = computed(() => selectedBooking.value?.guestsCount ?? 1);

const fillPercent = (schedule) => {
    const capacity = schedule.ferry?.capacity || 1;
    return Math.round(((capacity - schedule.available_seats) / capacity) * 100);
};

// One reason per sailing, so a disabled button always says why it is disabled.
const blockedReason = (schedule, leg) => {
    if (cartedScheduleIds.value.includes(schedule.id)) return null;
    if (alreadyBookedDates.value.has(leg.date)) {
        return ticketedDates.value.has(leg.date)
            ? 'You already have a ticket for this leg'
            : 'This leg is already in your itinerary';
    }
    if (schedule.available_seats < seatsNeeded.value) {
        return `Only ${schedule.available_seats} seat${schedule.available_seats === 1 ? '' : 's'} left — your party needs ${seatsNeeded.value}`;
    }
    return null;
};

const ctaLabel = (schedule, leg) => {
    if (cartedScheduleIds.value.includes(schedule.id)) return 'Added';
    if (alreadyBookedDates.value.has(leg.date)) return 'Leg covered';
    if (schedule.available_seats < seatsNeeded.value) return 'Not enough seats';
    return 'Choose seats';
};

const isBlocked = (schedule, leg) =>
    cartedScheduleIds.value.includes(schedule.id) ||
    alreadyBookedDates.value.has(leg.date) ||
    schedule.available_seats < seatsNeeded.value;

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
            <TPageHeader
                title="Ferry"
                subtitle="Crossings are tied to the stay you are travelling for"
                icon="ferry"
            />
        </template>

        <div class="shell space-y-5 py-6">
            <PromotionsStrip category="ferry" />

            <div v-if="upcomingTickets.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border bg-surface-sunken px-4 py-2.5 text-sm">
                <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                    <TIcon name="ticket" :size="15" class="text-primary" /> Already booked
                </span>
                <span v-for="ticket in upcomingTickets" :key="ticket.id" class="text-foreground-secondary">
                    {{ ticket.schedule?.ferry?.name }} ·
                    {{ formatDateTime(ticket.schedule?.departure_date, ticket.schedule?.departure_time) }}
                    (seat {{ ticket.seat_number }})
                </span>
                <router-link :to="{ name: 'trips', query: { tab: 'ferry' } }" class="ml-auto font-medium text-primary hover:underline">
                    View all
                </router-link>
            </div>

            <TEmptyState
                v-if="!hasEligibleBooking"
                title="Pick where you're staying first"
                description="A ferry seat is booked against a stay, so the crossing dates match your check-in and check-out. Add a room and this page fills in."
                icon="hotel"
            >
                <template #action>
                    <router-link :to="{ name: 'hotels.index' }">
                        <TButton as="span">Browse hotels</TButton>
                    </router-link>
                </template>
            </TEmptyState>

            <template v-else>
                <!-- Step 1. Cards rather than a dropdown: the thing being chosen
                     is a whole stay — hotel, dates, party size — and every one of
                     those is what makes one option right and another wrong. A
                     dropdown showed them as one long truncated line. -->
                <section>
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold tracking-tight text-foreground">
                            <span class="text-foreground-muted">1 ·</span> Which stay are you travelling for?
                        </h2>
                        <p v-if="eligibleBookings.length === 1" class="text-xs text-foreground-muted">
                            Selected automatically — it's your only stay
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        <label
                            v-for="option in eligibleBookings"
                            :key="option.key"
                            class="elevated flex cursor-pointer items-start gap-3 rounded-xl border bg-surface p-4 transition-colors"
                            :class="selectedBookingKey === option.key
                                ? 'border-primary ring-2 ring-primary/20'
                                : 'hover:border-strong hover:bg-surface-hover'"
                        >
                            <input
                                type="radio"
                                name="stay"
                                class="mt-1 h-4 w-4 shrink-0 border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                :value="option.key"
                                :checked="selectedBookingKey === option.key"
                                :aria-label="option.label"
                                @change="selectedBookingKey = option.key"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-foreground">{{ option.hotelName }}</p>
                                    <TBadge :variant="option.confirmed ? 'success' : 'warning'" size="sm" dot>
                                        {{ option.confirmed ? 'Confirmed' : 'In itinerary' }}
                                    </TBadge>
                                </div>
                                <p class="mt-1 text-sm text-foreground-secondary">
                                    {{ formatDateRange(option.checkIn, option.checkOut) }}
                                    · {{ nightsBetween(option.checkIn, option.checkOut) }} night{{ nightsBetween(option.checkIn, option.checkOut) === 1 ? '' : 's' }}
                                </p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-foreground-muted">
                                    <span class="inline-flex items-center gap-1"><TIcon name="users" :size="13" /> {{ option.guestsCount }} guest{{ option.guestsCount === 1 ? '' : 's' }}</span>
                                    <span class="inline-flex items-center gap-1 capitalize"><TIcon name="bed" :size="13" /> {{ option.roomsLabel }}</span>
                                    <span v-if="option.confirmed" class="font-mono">{{ option.detail }}</span>
                                </p>
                            </div>
                        </label>
                    </div>
                </section>

                <!-- Step 2. The journey, not a schedule table: both legs are shown
                     side by side with their own state, so "I booked the way out and
                     forgot the way back" is visible instead of being something you
                     find out at checkout. -->
                <section v-if="selectedBooking">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold tracking-tight text-foreground">
                            <span class="text-foreground-muted">2 ·</span> Pick a sailing for each leg
                        </h2>
                        <p class="text-xs text-foreground-muted">
                            {{ seatsNeeded }} seat{{ seatsNeeded === 1 ? '' : 's' }} needed · {{ legsSettled }} of 2 legs covered
                        </p>
                    </div>

                    <div
                        v-if="legsSettled === 2"
                        class="mb-4 flex items-center gap-2 rounded-xl border border-success/40 bg-success-soft px-4 py-3 text-sm text-success"
                    >
                        <TIcon name="checkCircle" :size="16" />
                        Both crossings are covered. Nothing else to do here.
                    </div>

                    <div v-if="loadingSchedules" class="grid gap-5 md:grid-cols-2">
                        <div v-for="n in 2" :key="n" class="h-64 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>

                    <div v-else class="grid gap-5 md:grid-cols-2 xl:gap-7">
                        <div v-for="leg in legs" :key="leg.key" class="min-w-0">
                            <div
                                class="elevated mb-3 overflow-hidden rounded-xl border bg-surface"
                                :class="leg.status === 'needed' ? '' : 'border-success/40'"
                            >
                                <div class="flex items-start gap-3 p-4">
                                    <span
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-full"
                                        :class="leg.status === 'needed' ? 'bg-primary-soft text-primary' : 'bg-success-soft text-success'"
                                    >
                                        <TIcon :name="leg.status === 'needed' ? leg.icon : 'check'" :size="17" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-foreground">{{ leg.title }}</p>
                                        <p class="text-sm text-foreground-secondary">
                                            {{ formatDate(leg.date) }} · {{ leg.caption }}
                                        </p>
                                    </div>
                                    <TBadge :variant="leg.status === 'needed' ? 'neutral' : 'success'" size="sm" dot class="shrink-0">
                                        {{ STATUS_TEXT[leg.status] }}
                                    </TBadge>
                                </div>
                            </div>

                            <p v-if="leg.schedules.length === 0" class="rounded-xl border bg-surface-sunken p-4 text-sm text-foreground-muted">
                                No departures scheduled for {{ formatDate(leg.date) }}. This date is fixed to your stay, so change
                                the stay dates if you need a different crossing.
                            </p>

                            <ul v-else class="space-y-3">
                                <li
                                    v-for="schedule in leg.schedules"
                                    :key="schedule.id"
                                    class="elevated overflow-hidden rounded-xl border bg-surface"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                                        <div class="min-w-0">
                                            <!-- Departure → arrival reads as a crossing, which is
                                                 what's being bought; the boat's name is secondary. -->
                                            <p class="flex items-center gap-2 text-lg font-semibold tracking-tight text-foreground">
                                                {{ formatTime(schedule.departure_time) }}
                                                <TIcon name="arrowRight" :size="16" class="text-foreground-muted" />
                                                {{ formatTime(schedule.arrival_time) }}
                                            </p>
                                            <p class="mt-0.5 inline-flex items-center gap-1.5 text-sm text-foreground-muted">
                                                <TIcon name="ferry" :size="14" /> {{ schedule.ferry?.name }}
                                            </p>
                                        </div>
                                        <!-- The fare lives on the ferry, not the sailing; the
                                             schedules endpoint eager-loads it, so it can be shown
                                             here instead of only after opening the seat picker. -->
                                        <div v-if="schedule.ferry?.price_per_seat" class="text-right">
                                            <p class="font-semibold text-foreground">
                                                {{ formatMoney(schedule.ferry.price_per_seat) }}
                                            </p>
                                            <p class="text-xs text-foreground-muted">per seat</p>
                                        </div>
                                    </div>

                                    <div class="px-4">
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-hover">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="fillPercent(schedule) > 90 ? 'bg-danger' : 'bg-primary'"
                                                :style="{ width: fillPercent(schedule) + '%' }"
                                            />
                                        </div>
                                        <p class="mt-1.5 text-xs text-foreground-muted">
                                            {{ schedule.available_seats }} of {{ schedule.ferry?.capacity }} seats left
                                        </p>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t bg-surface-sunken/50 px-4 py-3">
                                        <p class="min-w-0 flex-1 text-xs text-foreground-muted">
                                            {{ blockedReason(schedule, leg) ?? `Seats for ${seatsNeeded} passenger${seatsNeeded === 1 ? '' : 's'}` }}
                                        </p>
                                        <TButton
                                            size="sm"
                                            :disabled="isBlocked(schedule, leg)"
                                            @click="openSeatPicker(schedule)"
                                        >
                                            {{ ctaLabel(schedule, leg) }}
                                        </TButton>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>
            </template>
        </div>

        <SeatPickerModal
            v-model:show="showSeatPicker"
            :schedule="activeSchedule"
            :booking-id="selectedBooking?.bookingId"
            :hotel-cart-item-id="selectedBooking?.hotelCartItemId"
            :seats-needed="seatsNeeded"
            @added="onAddedToCart"
        />
    </AuthenticatedLayout>
</template>
