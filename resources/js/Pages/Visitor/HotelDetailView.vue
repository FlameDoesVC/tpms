<script setup>
/**
 * A hotel's own page: photographs, what the place is, what it has, and the
 * rooms you can actually book - the thing the combined browse page had no room
 * to be.
 *
 * The stay (dates and guests) lives in the URL, so a link to this page carries
 * the search that produced it and a refresh does not lose it.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FacilityList from '@/Components/FacilityList.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import StickyBookingBar from '@/Components/StickyBookingBar.vue';
import RoomTypeCard from '@/Pages/Visitor/Hotel/RoomTypeCard.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import { formatMoney, nightsBetween, todayIso } from '@/utils/format';
import * as roomSelection from '@/utils/roomSelection';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useHotelStore } from '@/stores/hotel';
import { useThemeParkStore } from '@/stores/themepark';

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const themeParkStore = useThemeParkStore();
const cart = useCartStore();
const auth = useAuthStore();

// Stepped in UTC so the ISO date is the one asked for rather than whatever the
// local offset shifts it to.
const addDays = (dateStr, days) => {
    const d = new Date(`${dateStr}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
};

const today = todayIso();
const hotelId = computed(() => Number(route.params.id));

const checkIn = ref(addDays(today, 1));
const checkOut = ref(addDays(today, 2));
const guests = ref(1);

const roomTypes = ref([]);
const refreshingAvailability = ref(false);
const message = ref(null);

const hotel = computed(() => hotelStore.hotel);
const nights = computed(() => nightsBetween(checkIn.value, checkOut.value));

const galleryImages = computed(() => {
    if (!hotel.value) return [];
    const gallery = hotel.value.gallery ?? [];
    return hotel.value.image_url
        ? [{ id: 'cover', url: hotel.value.image_url }, ...gallery]
        : gallery;
});

const hasContactDetails = computed(
    () =>
        !!hotel.value &&
        (hotel.value.check_in_time ||
            hotel.value.check_out_time ||
            hotel.value.phone ||
            hotel.value.email ||
            hotel.value.website)
);

const cheapest = computed(() => {
    const priced = roomTypes.value.map((type) => Number(type.price_per_night)).filter(Number.isFinite);
    return priced.length ? Math.min(...priced) : null;
});

// --- Selection, ported from the combined page ---
// A stay is one hotel across one set of dates; capacity accumulates across
// every room type chosen for it, so the party can be split however suits them.
const quantityOverrides = reactive({});

const stay = computed(() => ({ hotelId: hotelId.value, checkIn: checkIn.value, checkOut: checkOut.value }));
const stayRows = computed(() => roomSelection.roomsForStay(cart.items, stay.value));
const bookedCapacity = computed(() => roomSelection.bookedCapacity(stayRows.value));
const bookedRooms = computed(() => roomSelection.bookedRooms(stayRows.value));
const guestsStillNeeded = computed(() => roomSelection.guestsStillNeeded(guests.value, bookedCapacity.value));

const defaultQuantity = (roomType) =>
    roomSelection.suggestedQuantity({
        guests: guests.value,
        capacityBooked: bookedCapacity.value,
        maxGuests: roomType.max_guests,
        availableCount: roomType.available_count,
    });

// Not clamped on the way in: clamping every keystroke fights the user mid-edit.
// An out-of-range value simply is not bookable.
const quantityFor = (roomType) =>
    roomType.id in quantityOverrides ? quantityOverrides[roomType.id] : defaultQuantity(roomType);

const verdictFor = (roomType) =>
    roomSelection.evaluateRoomChoice({
        guests: guests.value,
        quantity: quantityFor(roomType),
        maxGuests: roomType.max_guests,
        availableCount: roomType.available_count,
        capacityBooked: bookedCapacity.value,
        roomsBooked: bookedRooms.value,
    });

const addToCart = (roomType) => {
    message.value = null;

    const verdict = verdictFor(roomType);
    if (!verdict.canBook) {
        message.value = {
            type: 'error',
            text:
                verdict.status === 'exceeds-stock'
                    ? `Only ${roomType.available_count} of this room type left for these dates.`
                    : 'Enter how many rooms you want - at least one.',
        };
        return;
    }

    const quantity = quantityFor(roomType);
    const rowGuests = roomSelection.guestsForRow({
        guests: guests.value,
        capacityBooked: bookedCapacity.value,
        quantity,
        maxGuests: roomType.max_guests,
    });

    cart.addItem({
        type: 'hotel',
        hotelId: hotel.value.id,
        hotelName: hotel.value.name,
        roomTypeId: roomType.id,
        roomTypeName: roomType.name,
        pricePerNight: roomType.price_per_night,
        maxGuests: roomType.max_guests,
        quantity,
        checkIn: checkIn.value,
        checkOut: checkOut.value,
        guestsCount: rowGuests,
        subtotal: quantity * nights.value * Number(roomType.price_per_night),
    });

    const remaining = guestsStillNeeded.value;
    message.value = {
        type: 'success',
        text:
            remaining > 0
                ? `Added ${quantity} × ${roomType.name}. Still ${remaining} guest${remaining === 1 ? '' : 's'} to place - add another room type.`
                : `Added ${quantity} × ${roomType.name}. Everyone has a bed.`,
    };
};

// --- URL state ---
// Replaced rather than pushed: a date picker should not fill the back button
// with every intermediate value.
const syncQueryToUrl = () => {
    router.replace({
        query: {
            ...route.query,
            check_in: checkIn.value,
            check_out: checkOut.value,
            guests: String(guests.value),
        },
    });
};

const readQueryFromUrl = () => {
    const q = route.query;
    if (!q.check_in) return false;

    // A stay in the past would return no availability at all, so a stale link
    // is nudged forward rather than showing an empty hotel.
    const start = q.check_in >= today ? String(q.check_in) : addDays(today, 1);
    const end = q.check_out && q.check_out > start ? String(q.check_out) : addDays(start, 1);

    checkIn.value = start;
    checkOut.value = end;
    guests.value = Math.max(1, Number(q.guests) || 1);
    return true;
};

/**
 * A party already holding park tickets needs a room for that many guests over
 * at least the ticket dates. Only used to seed the fields, and only when the
 * URL did not already say what the stay is.
 */
const applyDefaultsFromTickets = () => {
    const dates = [];
    let ticketSum = 0;

    for (const item of cart.items) {
        if (item.type === 'themepark' && item.slotDate >= today) {
            dates.push(item.slotDate);
            ticketSum += item.ticketCount;
        }
    }
    for (const booking of themeParkStore.myBookings) {
        const slotDate = (booking.slot?.slot_date ?? '').slice(0, 10);
        if (booking.status === 'confirmed' && slotDate >= today) {
            dates.push(slotDate);
            ticketSum += booking.ticket_count;
        }
    }

    if (dates.length) {
        const minDate = dates.reduce((a, d) => (d < a ? d : a));
        const maxDate = dates.reduce((a, d) => (d > a ? d : a));
        checkIn.value = minDate;
        checkOut.value = maxDate > minDate ? maxDate : addDays(minDate, 1);
    }

    guests.value = Math.max(1, ticketSum);
};

const dates = () => ({ checkIn: checkIn.value, checkOut: checkOut.value });

// Availability alone, so changing a date does not blank the photographs and
// copy the visitor is reading.
const refreshAvailability = async () => {
    refreshingAvailability.value = true;
    try {
        roomTypes.value = await hotelStore.fetchRoomTypes(hotelId.value, dates());
    } finally {
        refreshingAvailability.value = false;
    }
};

// A checkout on or before check-in has no nights in it.
watch(checkIn, (value) => {
    if (checkOut.value <= value) checkOut.value = addDays(value, 1);
});

watch([checkIn, checkOut], () => {
    syncQueryToUrl();
    refreshAvailability();
});

watch(guests, syncQueryToUrl);

onMounted(async () => {
    if (!Number.isFinite(hotelId.value)) {
        hotelStore.hotelNotFound = true;
        return;
    }

    const fromUrl = readQueryFromUrl();

    if (!fromUrl) {
        if (auth.isAuthenticated) await themeParkStore.fetchMyBookings({ silent: true });
        applyDefaultsFromTickets();
    }

    // Dates always go with the request - without them the response carries no
    // availability and the room steppers would have no ceiling.
    const data = await hotelStore.fetchHotel(hotelId.value, dates());
    roomTypes.value = data?.room_types ?? [];

    if (!fromUrl) syncQueryToUrl();
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="shell space-y-5 py-6">
            <router-link
                :to="{ name: 'hotels.index', query: { check_in: checkIn, check_out: checkOut, guests: String(guests) } }"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-foreground-secondary transition-colors hover:text-foreground"
            >
                <TIcon name="arrowLeft" :size="15" />
                All hotels
            </router-link>

            <!-- Not found, deactivated, or an id that was never a number. Never
                 render a booking widget for a hotel that cannot be booked. -->
            <TEmptyState
                v-if="hotelStore.hotelNotFound"
                icon="search"
                title="This hotel isn't available"
                description="It may have been removed, or the link may be out of date."
            >
                <template #action>
                    <router-link :to="{ name: 'hotels.index' }">
                        <TButton>Browse hotels</TButton>
                    </router-link>
                </template>
            </TEmptyState>

            <div v-else-if="hotelStore.loading.hotel && !hotel" class="space-y-5">
                <div class="h-72 animate-pulse rounded-xl border bg-surface-hover" />
                <div class="h-6 w-1/3 animate-pulse rounded bg-surface-hover" />
                <div class="h-20 animate-pulse rounded-xl border bg-surface-hover" />
                <div v-for="n in 2" :key="n" class="h-48 animate-pulse rounded-xl border bg-surface-hover" />
            </div>

            <p v-else-if="hotelStore.error.hotel" class="text-danger">{{ hotelStore.error.hotel }}</p>

            <template v-else-if="hotel">
                <ImageGallery :images="galleryImages" :alt="hotel.name" variant="hero" icon="hotel" />

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ hotel.name }}</h1>
                            <TBadge :variant="hotel.is_active ? 'success' : 'neutral'" size="sm">
                                {{ hotel.is_active ? 'Open' : 'Closed' }}
                            </TBadge>
                        </div>
                        <p v-if="hotel.address" class="mt-1 inline-flex items-center gap-1.5 text-sm text-foreground-secondary">
                            <TIcon name="pin" :size="14" class="text-foreground-muted" />
                            {{ hotel.address }}
                        </p>
                        <FacilityList
                            v-if="hotel.facilities?.length"
                            class="mt-3"
                            :items="hotel.facilities"
                            variant="chips"
                            :limit="6"
                        />
                    </div>

                    <div class="text-right">
                        <template v-if="cheapest !== null">
                            <p class="text-xs text-foreground-muted">from</p>
                            <p class="text-2xl font-semibold tracking-tight text-foreground">
                                {{ formatMoney(cheapest) }}
                            </p>
                            <p class="text-xs text-foreground-muted">per night</p>
                        </template>
                        <router-link :to="{ hash: '#room-types', query: route.query }" class="mt-2 inline-block">
                            <TButton>Choose your room</TButton>
                        </router-link>
                    </div>
                </div>

                <StickyBookingBar
                    title="Your stay"
                    hint="Every room below is priced, checked and booked for exactly these dates and guests."
                >
                    <!-- Stepper and chip columns are `auto`: a stepper stretched
                         across a wide column splits its -/+ buttons apart. -->
                    <div class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-end lg:w-auto lg:min-w-[36rem]">
                        <TDatePicker v-model="checkIn" :min="today" label="Check in" />
                        <TDatePicker v-model="checkOut" :min="addDays(checkIn, 1)" label="Check out" />
                        <TNumberInput v-model="guests" label="Guests" :min="1" />
                        <p class="self-end rounded-lg border bg-surface px-3 py-2 text-sm font-medium tabular-nums text-foreground">
                            {{ nights }} night{{ nights === 1 ? '' : 's' }}
                        </p>
                    </div>
                </StickyBookingBar>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <section v-if="hotel.description" class="rounded-xl border bg-surface p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">About</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-foreground-secondary">
                            {{ hotel.description }}
                        </p>
                    </section>

                    <section v-if="hasContactDetails" class="rounded-xl border bg-surface p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">Good to know</h2>
                        <dl class="mt-3 space-y-2.5 text-sm">
                            <div v-if="hotel.check_in_time" class="flex items-center gap-2.5">
                                <TIcon name="clock" :size="16" class="text-primary" />
                                <dt class="text-foreground-secondary">Check-in from</dt>
                                <dd class="ml-auto font-medium tabular-nums text-foreground">{{ hotel.check_in_time }}</dd>
                            </div>
                            <div v-if="hotel.check_out_time" class="flex items-center gap-2.5">
                                <TIcon name="clock" :size="16" class="text-primary" />
                                <dt class="text-foreground-secondary">Check-out by</dt>
                                <dd class="ml-auto font-medium tabular-nums text-foreground">{{ hotel.check_out_time }}</dd>
                            </div>
                            <div v-if="hotel.phone" class="flex items-center gap-2.5">
                                <TIcon name="phone" :size="16" class="text-primary" />
                                <dd class="truncate text-foreground-secondary">{{ hotel.phone }}</dd>
                            </div>
                            <div v-if="hotel.email" class="flex items-center gap-2.5">
                                <TIcon name="mail" :size="16" class="text-primary" />
                                <dd class="truncate">
                                    <a :href="`mailto:${hotel.email}`" class="text-primary hover:underline">{{ hotel.email }}</a>
                                </dd>
                            </div>
                            <div v-if="hotel.website" class="flex items-center gap-2.5">
                                <TIcon name="globe" :size="16" class="text-primary" />
                                <dd class="truncate">
                                    <a :href="hotel.website" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                                        Visit website
                                    </a>
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section v-if="hotel.facilities?.length" class="rounded-xl border bg-surface p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">Facilities</h2>
                    <FacilityList class="mt-3" :items="hotel.facilities" variant="grid" />
                </section>

                <section id="room-types" class="scroll-mt-28 space-y-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold tracking-tight text-foreground">Choose your room</h2>
                        <p class="text-xs text-foreground-muted">
                            {{ nights }} night{{ nights === 1 ? '' : 's' }} ·
                            <span class="font-semibold text-foreground">{{ guests }}</span>
                            guest{{ guests === 1 ? '' : 's' }} — set in the bar above
                        </p>
                    </div>

                    <!-- Running total for the stay. Rooms accumulate across
                         types, so without this the visitor has to add up
                         capacities in their head to know the party is covered. -->
                    <div
                        v-if="bookedRooms"
                        class="flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2 text-xs"
                        :class="guestsStillNeeded > 0
                            ? 'border-warning/40 bg-warning-soft text-warning'
                            : 'border-success/40 bg-success-soft text-success'"
                    >
                        <TIcon :name="guestsStillNeeded > 0 ? 'alert' : 'checkCircle'" :size="14" />
                        <span class="font-medium">
                            {{ bookedRooms }} room{{ bookedRooms === 1 ? '' : 's' }} in your itinerary
                            · sleeps {{ bookedCapacity }} of {{ guests }}
                        </span>
                        <span v-if="guestsStillNeeded > 0">— {{ guestsStillNeeded }} still to place.</span>
                        <span v-else>— everyone has a bed.</span>
                    </div>

                    <p v-if="refreshingAvailability" class="text-xs text-foreground-muted">
                        Updating availability…
                    </p>

                    <TEmptyState
                        v-if="roomTypes.length === 0"
                        icon="bed"
                        title="No rooms listed yet"
                        description="This hotel hasn't published any room types."
                    />

                    <RoomTypeCard
                        v-for="(roomType, index) in roomTypes"
                        :key="roomType.id"
                        :room-type="roomType"
                        :index="index"
                        :quantity="quantityFor(roomType)"
                        :verdict="verdictFor(roomType)"
                        :guests="guests"
                        :nights="nights"
                        @update:quantity="(value) => (quantityOverrides[roomType.id] = value)"
                        @add="addToCart(roomType)"
                    />

                    <p
                        v-if="message"
                        class="text-sm"
                        :class="message.type === 'error' ? 'text-danger' : 'text-success'"
                    >
                        {{ message.text }}
                        <router-link
                            v-if="message.type === 'success'"
                            :to="{ name: 'ferry.book' }"
                            class="font-medium underline"
                        >
                            Book a ferry to take you to the island?
                        </router-link>
                    </p>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
