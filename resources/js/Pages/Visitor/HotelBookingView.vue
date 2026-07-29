<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TMultiSelect from '@/Components/ui/TMultiSelect.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useHotelStore } from '@/stores/hotel';
import { useCartStore } from '@/stores/cart';
import { useThemeParkStore } from '@/stores/themepark';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const hotelStore = useHotelStore();
const cart = useCartStore();
const themeParkStore = useThemeParkStore();
const auth = useAuthStore();

const addDays = (dateStr, days) => {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};

const checkIn = ref(addDays(new Date().toISOString().slice(0, 10), 1));
const checkOut = ref(addDays(new Date().toISOString().slice(0, 10), 2));
const guests = ref(1);
const selectedHotelIds = ref([]);

const roomTypesByHotel = reactive({});
const loadingTypes = reactive({});
const hotelMessages = reactive({});

// Empty selection means "show every hotel" - the filter only narrows things
// down once the visitor actually picks one or more.
const visibleHotels = computed(() =>
    selectedHotelIds.value.length === 0
        ? hotelStore.hotels
        : hotelStore.hotels.filter((h) => selectedHotelIds.value.includes(h.id))
);

const hotelOptions = computed(() => hotelStore.hotels.map((h) => ({ value: h.id, label: h.name })));

const today = new Date().toISOString().slice(0, 10);
const upcomingBookings = computed(() =>
    hotelStore.myBookings
        .filter((b) => b.status !== 'cancelled' && (b.check_out_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

// Quantity is derived live from `guests` on every render (like `canBook`
// below) rather than being copied onto each group once - a manual
// watch-and-replace was too easy to get out of sync with guest count changes.
const groupKey = (hotelId, group) => `${hotelId}|${group.type}|${group.price_per_night}|${group.max_guests}`;
const quantityOverrides = reactive({});
const expandedHotels = reactive({});

const toggleExpanded = (hotelId) => { expandedHotels[hotelId] = !expandedHotels[hotelId]; };

// Summarizes the room list while it's collapsed, so collapsing doesn't hide
// whether there's anything worth expanding for.
const roomsTeaser = (hotel) => {
    if (loadingTypes[hotel.id]) return 'Loading rooms…';
    const groups = roomTypesByHotel[hotel.id] ?? [];
    if (groups.length === 0) return 'Fully booked for these dates';
    const cheapest = groups.reduce((min, g) => (g.price_per_night < min.price_per_night ? g : min), groups[0]);
    return `${groups.length} room type${groups.length === 1 ? '' : 's'} from $${cheapest.price_per_night}/night`;
};

const defaultQuantity = (group) =>
    Math.min(group.available_count, Math.max(1, Math.ceil(guests.value / group.max_guests)));

const quantityFor = (hotelId, group) => {
    const key = groupKey(hotelId, group);
    // Not clamped here - clamping on every keystroke fights the user mid-edit
    // (e.g. snapping back to a smaller number as soon as the field is cleared
    // to type a new one). Out-of-range values are simply not bookable, below.
    return key in quantityOverrides ? quantityOverrides[key] : defaultQuantity(group);
};

const setQuantity = (hotelId, group, value) => {
    quantityOverrides[groupKey(hotelId, group)] = value;
};

const loadTypesFor = async (hotelId) => {
    if (!checkIn.value || !checkOut.value) return;

    loadingTypes[hotelId] = true;
    try {
        roomTypesByHotel[hotelId] = await hotelStore.fetchRoomTypes(hotelId, { checkIn: checkIn.value, checkOut: checkOut.value });
    } finally {
        loadingTypes[hotelId] = false;
    }
};

const loadAllTypes = () => Promise.all(hotelStore.hotels.map((h) => loadTypesFor(h.id)));

watch([checkIn, checkOut], loadAllTypes);

// A party already holding theme-park tickets needs a room for that many
// guests, spanning at least from the earliest to the latest ticket date -
// pulled from both the cart (not yet booked) and already-confirmed bookings.
// Past/already-happened tickets don't count. This only sets the initial
// defaults; it never overwrites the fields once the visitor edits them.
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

onMounted(async () => {
    await hotelStore.fetchHotels();
    if (auth.isAuthenticated) {
        hotelStore.fetchMyBookings({ silent: true });
        await themeParkStore.fetchMyBookings({ silent: true });
    }

    applyDefaultsFromTickets();

    // Arriving from a specific hotel's card (e.g. Welcome.vue) pre-filters
    // the page down to just that one hotel instead of showing everything.
    if (route.query.hotel) {
        selectedHotelIds.value = [Number(route.query.hotel)];
    }

    await loadAllTypes();
});

// More rooms of this type than there are guests means at least one of them
// would sit empty - a single bigger room is never flagged by this (quantity
// stays at 1 no matter how much larger max_guests is than guests), only
// renting rooms nobody's in. Two rooms sharing one guest has no reading
// other than a stale guest count, so that specific case blocks rather than
// just warns; anything less extreme (e.g. 4 rooms for 3 guests) still might
// be deliberate - a caregiver's room, overflow space - so it only nudges.
const roomsExceedGuests = (hotelId, group) => quantityFor(hotelId, group) > guests.value;
const mustFixRoomCount = (hotelId, group) => roomsExceedGuests(hotelId, group) && guests.value === 1;

const canBook = (hotelId, group) => {
    const quantity = quantityFor(hotelId, group);
    return (
        quantity >= 1 &&
        quantity <= group.available_count &&
        quantity * group.max_guests >= guests.value &&
        !mustFixRoomCount(hotelId, group)
    );
};

// Even at the max available quantity, some room types simply can't seat the
// whole party - flagged directly rather than only via a silently-disabled
// button, since the auto-filled quantity would otherwise look like a real fit.
const maxCapacity = (group) => group.available_count * group.max_guests;

const nights = computed(() => {
    const ms = new Date(checkOut.value) - new Date(checkIn.value);
    return Math.max(1, Math.round(ms / 86400000));
});

const addToCart = (hotel, group) => {
    hotelMessages[hotel.id] = null;

    if (!canBook(hotel.id, group)) {
        hotelMessages[hotel.id] = { type: 'error', text: 'Adjust the room quantity to fit your party size.' };
        return;
    }

    const quantity = quantityFor(hotel.id, group);
    const subtotal = quantity * nights.value * group.price_per_night;

    cart.addItem({
        type: 'hotel',
        hotelId: hotel.id,
        hotelName: hotel.name,
        roomType: group.type,
        pricePerNight: group.price_per_night,
        maxGuests: group.max_guests,
        quantity,
        checkIn: checkIn.value,
        checkOut: checkOut.value,
        guestsCount: guests.value,
        representativeRoomId: group.representative_room_id,
        subtotal,
    });

    hotelMessages[hotel.id] = { type: 'success', text: `Added ${quantity} ${group.type} room(s) to cart.` };
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Hotels" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="auth.isAuthenticated" class="elevated rounded-xl border bg-surface p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-foreground">My Bookings</h3>
                        <router-link :to="{ name: 'bookings.my' }" class="text-sm text-primary hover:underline">
                            View all
                        </router-link>
                    </div>

                    <p v-if="upcomingBookings.length === 0" class="mt-2 text-sm text-foreground-muted">
                        No upcoming bookings.
                    </p>
                    <div v-else class="mt-3 space-y-2">
                        <div
                            v-for="booking in upcomingBookings"
                            :key="booking.id"
                            class="flex items-center justify-between rounded-lg bg-surface-hover px-3 py-2 text-sm text-foreground-secondary"
                        >
                            <span>
                                {{ booking.room?.hotel?.name }} -
                                {{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}
                            </span>
                            <router-link
                                :to="{ name: 'bookings.confirm', query: { ids: String(booking.id) } }"
                                class="font-medium text-primary hover:underline"
                            >
                                {{ booking.status === 'pending' ? 'Complete Payment' : 'View' }}
                            </router-link>
                        </div>
                    </div>
                </div>

                <div class="elevated sticky top-[4.25rem] z-10 rounded-xl border bg-surface p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Find your stay</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <TMultiSelect v-model="selectedHotelIds" label="Hotels" placeholder="All hotels" :options="hotelOptions" width="12rem" />
                        <div class="hidden h-10 w-px self-stretch bg-[rgb(var(--color-border))] sm:block" aria-hidden="true" />
                        <TDatePicker v-model="checkIn" label="Check in" class="w-40" />
                        <TDatePicker v-model="checkOut" label="Check out" class="w-40" />
                        <TNumberInput v-model="guests" label="Guests" :min="1" />
                    </div>
                </div>

                <div v-if="hotelStore.loading.hotels" class="text-foreground-muted">Loading hotels...</div>
                <div v-else-if="hotelStore.error.hotels" class="text-danger">{{ hotelStore.error.hotels }}</div>
                <TEmptyState
                    v-else-if="hotelStore.hotels.length === 0"
                    title="No hotels available yet"
                    description="Check back soon for places to stay on the island."
                    icon="inbox"
                />
                <TEmptyState
                    v-else-if="visibleHotels.length === 0"
                    title="No hotels match the selected filter"
                    description="Try clearing the hotel filter to see everything available."
                    icon="search"
                />

                <div v-else class="space-y-6">
                    <div
                        v-for="hotel in visibleHotels"
                        :key="hotel.id"
                        class="elevated overflow-hidden rounded-xl border bg-surface"
                    >
                        <!-- Header + teaser together are the whole click-to-expand surface.
                             The room list below is a separate sibling on purpose: it's full
                             of its own interactive controls (stepper, Add to Cart), and this
                             way a click there simply never reaches the toggle handler, rather
                             than needing `.stop` on every one of them. -->
                        <div
                            role="button"
                            tabindex="0"
                            class="cursor-pointer transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40"
                            :aria-expanded="!!expandedHotels[hotel.id]"
                            :aria-controls="`hotel-panel-${hotel.id}`"
                            @click="toggleExpanded(hotel.id)"
                            @keydown.enter="toggleExpanded(hotel.id)"
                            @keydown.space.prevent="toggleExpanded(hotel.id)"
                        >
                            <div class="flex items-start gap-4 p-5">
                                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted">
                                    <img v-if="hotel.image_url" :src="hotel.image_url" :alt="hotel.name" class="h-full w-full object-cover" />
                                    <span v-else>No image</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-foreground">{{ hotel.name }}</h3>
                                        <TBadge :variant="hotel.is_active ? 'success' : 'neutral'" dot class="shrink-0">
                                            {{ hotel.is_active ? 'Open' : 'Closed' }}
                                        </TBadge>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-sm text-foreground-secondary">{{ hotel.description }}</p>
                                    <p class="mt-2 inline-flex items-center gap-1 text-xs text-foreground-muted">
                                        <TIcon name="pin" :size="13" /> {{ hotel.address }}
                                    </p>
                                </div>
                            </div>

                            <!-- Room list collapsed by default - with several hotels each listing
                                 several room types, showing every row eagerly was most of each
                                 card's height. The teaser keeps it from being a dead end when closed. -->
                            <div class="flex items-center justify-between gap-3 border-t px-5 py-3">
                                <span class="text-sm text-foreground-secondary">{{ roomsTeaser(hotel) }}</span>
                                <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary">
                                    {{ expandedHotels[hotel.id] ? 'Hide rooms' : 'View rooms' }}
                                    <TIcon name="chevronDown" :size="15" class="transition-transform" :class="expandedHotels[hotel.id] ? 'rotate-180' : ''" />
                                </span>
                            </div>
                        </div>

                        <div :id="`hotel-panel-${hotel.id}`" class="grid transition-[grid-template-rows] duration-200 ease-out" :style="{ gridTemplateRows: expandedHotels[hotel.id] ? '1fr' : '0fr' }">
                            <div class="overflow-hidden">
                                <div class="bg-surface-sunken/40 p-5 pt-4">
                                    <!-- Same `guests` value as the filter bar up top - not a
                                         per-hotel copy - so it stays right here where rooms are
                                         actually picked instead of only living somewhere the user
                                         has already scrolled past. -->
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">Room types</p>
                                        <TNumberInput v-model="guests" label="Guests" label-position="left" :min="1" size="sm" />
                                    </div>

                                    <!-- Room list: price/capacity/availability read left-to-right as
                                         fixed columns, and the qty stepper + CTA sit at the end of the
                                         same row they act on, not floated elsewhere on the card. -->
                                    <div v-if="loadingTypes[hotel.id]" class="text-sm text-foreground-muted">Loading rooms...</div>
                                    <div
                                        v-else-if="(roomTypesByHotel[hotel.id]?.length ?? 0) === 0"
                                        class="rounded-lg bg-surface-hover p-3 text-sm text-foreground-muted"
                                    >
                                        Fully booked for these dates.
                                    </div>
                                    <div v-else class="divide-y divide-[rgb(var(--color-border))] overflow-hidden rounded-lg border bg-surface">
                                        <div
                                            v-for="group in roomTypesByHotel[hotel.id]"
                                            :key="`${group.type}-${group.price_per_night}-${group.max_guests}`"
                                            class="flex flex-wrap items-center gap-4 p-4"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-semibold capitalize text-foreground">{{ group.type }}</p>
                                                    <TBadge variant="neutral" size="sm">Up to {{ group.max_guests }} guests</TBadge>
                                                    <TBadge :variant="group.available_count > 2 ? 'success' : 'warning'" size="sm" dot>
                                                        {{ group.available_count }} left
                                                    </TBadge>
                                                </div>
                                                <p v-if="maxCapacity(group) < guests" class="mt-1 text-xs font-medium text-danger">
                                                    Doesn't fit {{ guests }} guests, only {{ group.available_count }} room(s) available.
                                                </p>
                                                <p v-else-if="mustFixRoomCount(hotel.id, group)" class="mt-1 text-xs font-medium text-danger">
                                                    {{ quantityFor(hotel.id, group) }} rooms for 1 guest - set quantity to 1, or update Guests above if more people are joining.
                                                </p>
                                                <p v-else-if="roomsExceedGuests(hotel.id, group)" class="mt-1 text-xs font-medium text-warning">
                                                    {{ quantityFor(hotel.id, group) }} rooms for {{ guests }} guest{{ guests === 1 ? '' : 's' }} - double check that's the party size you meant.
                                                </p>
                                            </div>

                                            <p class="shrink-0 font-semibold text-foreground">
                                                ${{ group.price_per_night }} <span class="font-normal text-foreground-muted">/ night</span>
                                            </p>

                                            <div class="ml-auto flex shrink-0 items-center gap-3">
                                                <TNumberInput
                                                    label="Rooms"
                                                    label-position="left"
                                                    :model-value="quantityFor(hotel.id, group)"
                                                    @update:model-value="v => setQuantity(hotel.id, group, v)"
                                                    :min="1"
                                                    :max="group.available_count"
                                                    size="sm"
                                                />
                                                <TButton :disabled="!canBook(hotel.id, group)" @click="addToCart(hotel, group)">
                                                    Add to Cart
                                                </TButton>
                                            </div>
                                        </div>
                                    </div>

                                    <p
                                        v-if="hotelMessages[hotel.id]"
                                        class="mt-3 text-sm"
                                        :class="hotelMessages[hotel.id].type === 'error' ? 'text-danger' : 'text-success'"
                                    >
                                        {{ hotelMessages[hotel.id].text }}
                                        <router-link
                                            v-if="hotelMessages[hotel.id].type === 'success'"
                                            :to="{ name: 'ferry.book' }"
                                            class="font-medium underline"
                                        >
                                            Book a ferry to take you to the island?
                                        </router-link>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
