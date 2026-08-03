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
import TInput from '@/Components/ui/TInput.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import FilterRail from '@/Components/FilterRail.vue';
import FilterSection from '@/Components/FilterSection.vue';
import PromotionsStrip from '@/Components/PromotionsStrip.vue';
import { formatDateRange, formatMoney, nightsBetween, todayIso } from '@/utils/format';
import * as roomSelection from '@/utils/roomSelection';
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

// Stepped in UTC throughout, so the returned ISO date is the one that was
// asked for rather than whatever the local offset shifts it to.
const addDays = (dateStr, days) => {
    const d = new Date(`${dateStr}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
};

const today = todayIso();

const SORTS = [
    { key: 'price', label: 'Cheapest' },
    { key: 'choice', label: 'Most rooms' },
    { key: 'name', label: 'A – Z' },
];

// — Primary search: what decides which rooms exist at all.
const checkIn = ref(addDays(today, 1));
const checkOut = ref(addDays(today, 2));
const guests = ref(1);
const search = ref('');

// — Refinements.
const selectedRoomTypes = ref([]);
const maxNightlyPrice = ref(null);
const hideUnavailable = ref(false);
const sort = ref('price');

// Arriving from a specific hotel's card (e.g. Welcome.vue) pins the page to
// that one hotel. Kept separate from the filters so it can be lifted with one
// click without disturbing anything else the visitor has set.
const focusedHotelId = ref(null);
const focusedHotel = computed(() => hotelStore.hotels.find((h) => h.id === focusedHotelId.value) ?? null);

const roomTypesByHotel = reactive({});
const loadingTypes = reactive({});
const hotelMessages = reactive({});

const nights = computed(() => nightsBetween(checkIn.value, checkOut.value));

// Room types the loaded results actually contain — a facet list built from the
// data rather than a hardcoded set, so a new room type never goes unfilterable.
const roomTypeOptions = computed(() => {
    const counts = new Map();
    for (const groups of Object.values(roomTypesByHotel)) {
        for (const group of groups ?? []) {
            counts.set(group.type, (counts.get(group.type) ?? 0) + 1);
        }
    }
    return [...counts.entries()]
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([type, count]) => ({ type, count }));
});

const priceCeiling = computed(() => {
    const prices = Object.values(roomTypesByHotel)
        .flatMap((groups) => (groups ?? []).map((g) => Number(g.price_per_night) || 0));
    return prices.length ? Math.ceil(Math.max(...prices) / 25) * 25 : 0;
});
const priceCap = computed(() => (maxNightlyPrice.value == null ? priceCeiling.value : maxNightlyPrice.value));
const priceIsCapped = computed(() => priceCeiling.value > 0 && priceCap.value < priceCeiling.value);

// The refinements apply to room types, so they narrow the rows inside a card as
// well as which cards survive - a visitor who capped the nightly rate shouldn't
// have to scroll past suites to reach the room that matched.
const groupsFor = (hotelId) => {
    let groups = roomTypesByHotel[hotelId] ?? [];
    if (selectedRoomTypes.value.length) {
        groups = groups.filter((g) => selectedRoomTypes.value.includes(g.type));
    }
    if (priceIsCapped.value) {
        groups = groups.filter((g) => (Number(g.price_per_night) || 0) <= priceCap.value);
    }
    return groups;
};

const cheapestFor = (hotelId) => {
    const groups = groupsFor(hotelId);
    if (!groups.length) return null;
    return groups.reduce((min, g) => (Number(g.price_per_night) < Number(min.price_per_night) ? g : min), groups[0]);
};
const roomsLeftFor = (hotelId) => groupsFor(hotelId).reduce((sum, g) => sum + (g.available_count ?? 0), 0);

const visibleHotels = computed(() => {
    let list = hotelStore.hotels;

    if (focusedHotelId.value) {
        list = list.filter((h) => h.id === focusedHotelId.value);
    }

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((h) =>
            `${h.name ?? ''} ${h.description ?? ''} ${h.address ?? ''}`.toLowerCase().includes(query)
        );
    }

    if (selectedRoomTypes.value.length || priceIsCapped.value || hideUnavailable.value) {
        list = list.filter((h) => groupsFor(h.id).length > 0);
    }

    const sorted = [...list];
    if (sort.value === 'name') {
        sorted.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
    } else if (sort.value === 'choice') {
        sorted.sort((a, b) => roomsLeftFor(b.id) - roomsLeftFor(a.id));
    } else {
        // Hotels with nothing left for these dates sort last rather than first,
        // which a plain ascending sort on a missing price would do.
        const price = (id) => Number(cheapestFor(id)?.price_per_night ?? Infinity);
        sorted.sort((a, b) => price(a.id) - price(b.id));
    }
    return sorted;
});

const activeFilterCount = computed(() =>
    (selectedRoomTypes.value.length ? 1 : 0) +
    (priceIsCapped.value ? 1 : 0) +
    (hideUnavailable.value ? 1 : 0) +
    (sort.value !== 'price' ? 1 : 0)
);

const clearFilters = () => {
    selectedRoomTypes.value = [];
    maxNightlyPrice.value = priceCeiling.value;
    hideUnavailable.value = false;
    sort.value = 'price';
};

const toggleRoomType = (type) => {
    selectedRoomTypes.value = selectedRoomTypes.value.includes(type)
        ? selectedRoomTypes.value.filter((t) => t !== type)
        : [...selectedRoomTypes.value, type];
};

const resultLabel = computed(() => {
    const total = hotelStore.hotels.length;
    const shown = visibleHotels.value.length;
    if (!total) return null;
    return shown === total ? `${total} hotel${total === 1 ? '' : 's'}` : `${shown} of ${total} hotels`;
});

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
    const groups = groupsFor(hotel.id);
    if (groups.length === 0) {
        return (roomTypesByHotel[hotel.id]?.length ?? 0) === 0
            ? 'Fully booked for these dates'
            : 'No rooms match your filters';
    }
    const cheapest = cheapestFor(hotel.id);
    return `${groups.length} room type${groups.length === 1 ? '' : 's'} from ${formatMoney(cheapest.price_per_night)}/night`;
};

// A stay is one hotel across one set of dates. Capacity accumulates across
// every room type chosen for it, so a party can be split however suits them.
// The rules themselves live in utils/roomSelection so the matrix of party
// sizes, room sizes and part-finished selections can be tested directly.
const stayOf = (hotelId) => ({ hotelId, checkIn: checkIn.value, checkOut: checkOut.value });

const bookedCapacity = (hotelId) => roomSelection.bookedCapacity(roomSelection.roomsForStay(cart.items, stayOf(hotelId)));
const bookedRooms = (hotelId) => roomSelection.bookedRooms(roomSelection.roomsForStay(cart.items, stayOf(hotelId)));
const guestsStillNeeded = (hotelId) => roomSelection.guestsStillNeeded(guests.value, bookedCapacity(hotelId));

const defaultQuantity = (hotelId, group) =>
    roomSelection.suggestedQuantity({
        guests: guests.value,
        capacityBooked: bookedCapacity(hotelId),
        maxGuests: group.max_guests,
        availableCount: group.available_count,
    });

const quantityFor = (hotelId, group) => {
    const key = groupKey(hotelId, group);
    // Not clamped here - clamping on every keystroke fights the user mid-edit
    // (e.g. snapping back to a smaller number as soon as the field is cleared
    // to type a new one). Out-of-range values are simply not bookable, below.
    return key in quantityOverrides ? quantityOverrides[key] : defaultQuantity(hotelId, group);
};

// The verdict for one row, given everything already chosen for this stay.
const verdictFor = (hotelId, group) =>
    roomSelection.evaluateRoomChoice({
        guests: guests.value,
        quantity: quantityFor(hotelId, group),
        maxGuests: group.max_guests,
        availableCount: group.available_count,
        capacityBooked: bookedCapacity(hotelId),
        roomsBooked: bookedRooms(hotelId),
    });

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

// A checkout date at or before check-in has no nights in it, so the range is
// nudged forward rather than silently querying an empty stay.
watch(checkIn, (value) => {
    if (checkOut.value <= value) checkOut.value = addDays(value, 1);
});

// A cap left at the old ceiling would silently filter everything out once a
// pricier set of rooms loads; track the ceiling until the visitor moves it.
watch(priceCeiling, (ceiling, previous) => {
    if (maxNightlyPrice.value == null || maxNightlyPrice.value === previous) {
        maxNightlyPrice.value = ceiling;
    }
});

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

    if (route.query.hotel) {
        focusedHotelId.value = Number(route.query.hotel);
        expandedHotels[focusedHotelId.value] = true;
    }

    await loadAllTypes();
});

const canBook = (hotelId, group) => verdictFor(hotelId, group).canBook;

const addToCart = (hotel, group) => {
    hotelMessages[hotel.id] = null;

    const verdict = verdictFor(hotel.id, group);
    if (!verdict.canBook) {
        hotelMessages[hotel.id] = {
            type: 'error',
            text: verdict.status === 'exceeds-stock'
                ? `Only ${group.available_count} ${group.type} room${group.available_count === 1 ? '' : 's'} left for these dates.`
                : 'Enter how many rooms you want — at least one.',
        };
        return;
    }

    const quantity = quantityFor(hotel.id, group);
    const subtotal = quantity * nights.value * group.price_per_night;
    const rowGuests = roomSelection.guestsForRow({
        guests: guests.value,
        capacityBooked: bookedCapacity(hotel.id),
        quantity,
        maxGuests: group.max_guests,
    });

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
        guestsCount: rowGuests,
        representativeRoomId: group.representative_room_id,
        subtotal,
    });

    const remaining = guestsStillNeeded(hotel.id);
    hotelMessages[hotel.id] = {
        type: 'success',
        text: remaining > 0
            ? `Added ${quantity} ${group.type} room${quantity === 1 ? '' : 's'}. Still ${remaining} guest${remaining === 1 ? '' : 's'} to place - add another room type.`
            : `Added ${quantity} ${group.type} room${quantity === 1 ? '' : 's'}. Everyone has a bed.`,
    };
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <!-- Describes the page; the sticky search bar below reports the
                 current search, so the two don't say the same thing twice. -->
            <TPageHeader title="Hotels" subtitle="Places to stay on the island" icon="hotel" />
        </template>

        <div class="shell space-y-5 py-6">
            <PromotionsStrip category="hotel" />

            <!-- Primary search. Dates and party size aren't refinements — they
                 decide which rooms exist at all — so they stay out of the rail
                 and follow the visitor down the page.
                 A grid rather than a flex row: grid items stretch to their
                 column on their own, so the fields divide the full width
                 instead of huddling at the left edge, and the search field is
                 wide enough that its placeholder isn't cut off. -->
            <div class="elevated sticky top-[4.25rem] z-20 rounded-xl border bg-surface">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b px-4 py-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">Find your stay</h2>
                    <!-- The live search, restated in words. The page header
                         carries the same thing but scrolls away; this bar
                         doesn't, so it's the one that has to answer "what am I
                         looking at" once you're deep in the list. -->
                    <p class="text-xs text-foreground-secondary">
                        {{ formatDateRange(checkIn, checkOut) }} · {{ nights }} night{{ nights === 1 ? '' : 's' }}
                        · {{ guests }} guest{{ guests === 1 ? '' : 's' }}
                    </p>
                </div>
                <!-- The stepper column is `auto`, not a fraction: a quantity
                     stepper stretched to a wide column puts its -/+ buttons at
                     opposite ends with dead space between (see TNumberInput).
                     Letting it keep its natural width hands the slack to the
                     text and date fields, and the row still ends flush right. -->
                <div class="grid gap-3 px-4 py-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <TInput
                        v-model="search"
                        type="search"
                        label="Search"
                        placeholder="Hotel name or area"
                    >
                        <template #prefix><TIcon name="search" :size="16" /></template>
                    </TInput>
                    <TDatePicker v-model="checkIn" :min="today" label="Check in" />
                    <TDatePicker v-model="checkOut" :min="addDays(checkIn, 1)" label="Check out" />
                    <TNumberInput v-model="guests" label="Guests" :min="1" />
                </div>
            </div>

            <div v-if="upcomingBookings.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border bg-surface-sunken px-4 py-2.5 text-sm">
                <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                    <TIcon name="bed" :size="15" class="text-primary" /> Already booked
                </span>
                <span v-for="booking in upcomingBookings" :key="booking.id" class="text-foreground-secondary">
                    {{ booking.room?.hotel?.name }} · {{ formatDateRange(booking.check_in_date, booking.check_out_date) }}
                    <router-link
                        :to="{ name: 'bookings.confirm', query: { ids: String(booking.id) } }"
                        class="font-medium text-primary hover:underline"
                    >
                        {{ booking.status === 'pending' ? 'Complete payment' : 'View' }}
                    </router-link>
                </span>
                <router-link :to="{ name: 'trips', query: { tab: 'hotel' } }" class="ml-auto font-medium text-primary hover:underline">
                    View all
                </router-link>
            </div>

            <div class="grid gap-5 xl:grid-cols-[17rem_minmax(0,1fr)] xl:gap-7">
                <!-- Parked below the sticky search bar. Erring low on purpose:
                     the bar sits at z-20, so a rail that stuck too high would
                     slide under it rather than just leaving a gap. -->
                <div class="xl:sticky xl:top-[11.5rem] xl:max-h-[calc(100vh-13rem)] xl:self-start xl:overflow-y-auto">
                    <FilterRail :result-label="resultLabel" :active-count="activeFilterCount" @clear="clearFilters">
                        <FilterSection
                            v-if="priceCeiling > 0"
                            title="Price per night"
                            :hint="priceIsCapped ? `Up to ${formatMoney(priceCap)}` : 'Any'"
                        >
                            <input
                                v-model.number="maxNightlyPrice"
                                type="range"
                                :min="0"
                                :max="priceCeiling"
                                :step="25"
                                class="w-full accent-primary"
                                aria-label="Maximum price per night"
                            />
                            <div class="mt-1 flex justify-between text-xs text-foreground-muted">
                                <span>{{ formatMoney(0) }}</span>
                                <span>{{ formatMoney(priceCeiling) }}</span>
                            </div>
                        </FilterSection>

                        <FilterSection
                            v-if="roomTypeOptions.length"
                            title="Room type"
                            :hint="selectedRoomTypes.length ? `${selectedRoomTypes.length} selected` : 'Any'"
                        >
                            <div class="space-y-2.5">
                                <label
                                    v-for="option in roomTypeOptions"
                                    :key="option.type"
                                    class="flex cursor-pointer items-center gap-3 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                        :checked="selectedRoomTypes.includes(option.type)"
                                        @change="toggleRoomType(option.type)"
                                    />
                                    <span class="flex-1 capitalize text-foreground">{{ option.type }}</span>
                                    <span class="text-xs text-foreground-muted">{{ option.count }}</span>
                                </label>
                            </div>
                        </FilterSection>

                        <FilterSection title="Availability">
                            <TSwitch v-model="hideUnavailable" label="Only show hotels with rooms" />
                        </FilterSection>

                        <FilterSection title="Sort by" :hint="SORTS.find((s) => s.key === sort)?.label">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in SORTS"
                                    :key="option.key"
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                    :class="sort === option.key
                                        ? 'border-primary bg-primary-soft text-primary'
                                        : 'bg-surface text-foreground-secondary hover:border-strong hover:text-foreground'"
                                    @click="sort = option.key"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </FilterSection>
                    </FilterRail>
                </div>

                <div class="min-w-0 space-y-4">
                    <div v-if="focusedHotel" class="flex items-center gap-2 text-sm">
                        <span class="inline-flex items-center gap-2 rounded-lg bg-primary-soft py-1.5 pl-3 pr-1.5 font-medium text-primary">
                            Showing only {{ focusedHotel.name }}
                            <button
                                type="button"
                                class="rounded p-1 transition-colors hover:bg-primary/15"
                                aria-label="Show all hotels"
                                @click="focusedHotelId = null"
                            >
                                <TIcon name="x" :size="13" />
                            </button>
                        </span>
                    </div>

                    <div v-if="hotelStore.loading.hotels" class="space-y-4">
                        <div v-for="n in 3" :key="n" class="h-44 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>
                    <div v-else-if="hotelStore.error.hotels" class="text-danger">{{ hotelStore.error.hotels }}</div>
                    <TEmptyState
                        v-else-if="hotelStore.hotels.length === 0"
                        title="No hotels available yet"
                        description="Check back soon for places to stay on the island."
                        icon="inbox"
                    />
                    <TEmptyState
                        v-else-if="visibleHotels.length === 0"
                        title="Nothing matches these filters"
                        description="Widen the nightly rate, clear a room type, or try different dates."
                        icon="search"
                    />

                    <template v-else>
                    <article
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
                            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:gap-5 sm:p-5">
                                <div class="flex h-36 w-full shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted sm:h-28 sm:w-44 lg:h-32 lg:w-52">
                                    <img v-if="hotel.image_url" :src="hotel.image_url" :alt="hotel.name" class="h-full w-full object-cover" />
                                    <span v-else>No image</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold tracking-tight text-foreground">{{ hotel.name }}</h3>
                                        <TBadge :variant="hotel.is_active ? 'success' : 'neutral'" dot class="shrink-0">
                                            {{ hotel.is_active ? 'Open' : 'Closed' }}
                                        </TBadge>
                                    </div>
                                    <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-foreground-secondary">{{ hotel.description }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-foreground-muted">
                                        <span class="inline-flex items-center gap-1"><TIcon name="pin" :size="13" /> {{ hotel.address }}</span>
                                        <span v-if="roomsLeftFor(hotel.id)" class="inline-flex items-center gap-1">
                                            <TIcon name="bed" :size="13" /> {{ roomsLeftFor(hotel.id) }} rooms left
                                        </span>
                                        <span v-if="bookedRooms(hotel.id)" class="inline-flex items-center gap-1 font-medium text-primary">
                                            <TIcon name="check" :size="13" /> {{ bookedRooms(hotel.id) }} in your itinerary
                                        </span>
                                    </div>
                                </div>

                                <!-- The width the wider layout bought goes here: the nightly
                                     rate stops being one more item in a metadata row and
                                     becomes a column you can compare down. -->
                                <div class="flex shrink-0 items-end justify-between gap-3 border-t pt-3 sm:w-40 sm:flex-col sm:items-end sm:justify-center sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0 lg:w-44">
                                    <div class="sm:text-right">
                                        <template v-if="cheapestFor(hotel.id)">
                                            <p class="text-xs text-foreground-muted">from</p>
                                            <p class="text-xl font-semibold tracking-tight text-foreground">
                                                {{ formatMoney(cheapestFor(hotel.id).price_per_night) }}
                                            </p>
                                            <p class="text-xs text-foreground-muted">per night</p>
                                        </template>
                                        <p v-else class="text-sm text-foreground-muted">No rooms</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary">
                                        {{ expandedHotels[hotel.id] ? 'Hide rooms' : 'View rooms' }}
                                        <TIcon name="chevronDown" :size="15" class="transition-transform" :class="expandedHotels[hotel.id] ? 'rotate-180' : ''" />
                                    </span>
                                </div>
                            </div>

                            <!-- Room list collapsed by default - with several hotels each listing
                                 several room types, showing every row eagerly was most of each
                                 card's height. The teaser keeps it from being a dead end when closed. -->
                            <div class="border-t px-4 py-2.5 text-sm text-foreground-secondary sm:px-5">
                                {{ roomsTeaser(hotel) }}
                            </div>
                        </div>

                        <div :id="`hotel-panel-${hotel.id}`" class="grid transition-[grid-template-rows] duration-200 ease-out" :style="{ gridTemplateRows: expandedHotels[hotel.id] ? '1fr' : '0fr' }">
                            <div class="overflow-hidden">
                                <div class="bg-surface-sunken/40 p-4 sm:p-5">
                                    <!-- Same `guests` value as the search bar up top - not a
                                         per-hotel copy - so it stays right here where rooms are
                                         actually picked instead of only living somewhere the user
                                         has already scrolled past. -->
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                            Room types · {{ nights }} night{{ nights === 1 ? '' : 's' }}
                                        </p>
                                        <TNumberInput v-model="guests" label="Guests" label-position="left" :min="1" size="sm" />
                                    </div>

                                    <!-- Running total for the stay. Rooms accumulate across types,
                                         so without this the visitor has to add up capacities in
                                         their head to know whether the party is covered. -->
                                    <div
                                        v-if="bookedRooms(hotel.id)"
                                        class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2 text-xs"
                                        :class="guestsStillNeeded(hotel.id) > 0
                                            ? 'border-warning/40 bg-warning-soft text-warning'
                                            : 'border-success/40 bg-success-soft text-success'"
                                    >
                                        <TIcon :name="guestsStillNeeded(hotel.id) > 0 ? 'alert' : 'checkCircle'" :size="14" />
                                        <span class="font-medium">
                                            {{ bookedRooms(hotel.id) }} room{{ bookedRooms(hotel.id) === 1 ? '' : 's' }} in your itinerary
                                            · sleeps {{ bookedCapacity(hotel.id) }} of {{ guests }}
                                        </span>
                                        <span v-if="guestsStillNeeded(hotel.id) > 0">
                                            — {{ guestsStillNeeded(hotel.id) }} still to place.
                                        </span>
                                        <span v-else>— everyone has a bed.</span>
                                    </div>

                                    <!-- Room list: name/price/stock/qty/CTA sit in fixed grid
                                         columns rather than a wrapping flex row, so prices and
                                         steppers line up down the list instead of drifting with
                                         the length of each room's guidance line. -->
                                    <div v-if="loadingTypes[hotel.id]" class="text-sm text-foreground-muted">Loading rooms...</div>
                                    <div
                                        v-else-if="groupsFor(hotel.id).length === 0"
                                        class="rounded-lg bg-surface-hover p-3 text-sm text-foreground-muted"
                                    >
                                        {{ (roomTypesByHotel[hotel.id]?.length ?? 0) === 0
                                            ? 'Fully booked for these dates.'
                                            : 'No room types here match your filters.' }}
                                    </div>
                                    <div v-else class="divide-y divide-[rgb(var(--color-border))] overflow-hidden rounded-lg border bg-surface">
                                        <div
                                            v-for="group in groupsFor(hotel.id)"
                                            :key="`${group.type}-${group.price_per_night}-${group.max_guests}`"
                                            class="grid items-center gap-x-5 gap-y-3 p-4 lg:grid-cols-[minmax(0,1fr)_8rem_auto]"
                                        >
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-semibold capitalize text-foreground">{{ group.type }}</p>
                                                    <TBadge variant="neutral" size="sm">Up to {{ group.max_guests }} guests</TBadge>
                                                    <TBadge :variant="group.available_count > 2 ? 'success' : 'warning'" size="sm" dot>
                                                        {{ group.available_count }} left
                                                    </TBadge>
                                                </div>
                                                <!-- Graded, in order of severity. Only a quantity the
                                                     hotel can't supply disables the button; the rest
                                                     are guidance for choices that are perfectly
                                                     legitimate, and go quiet once the party is
                                                     covered rather than repeating on every row. -->
                                                <p
                                                    v-if="verdictFor(hotel.id, group).severity"
                                                    class="mt-1 text-xs"
                                                    :class="{
                                                        'font-medium text-danger': verdictFor(hotel.id, group).severity === 'danger',
                                                        'font-medium text-warning': verdictFor(hotel.id, group).severity === 'warning',
                                                        'text-foreground-muted': verdictFor(hotel.id, group).severity === 'info',
                                                    }"
                                                >
                                                    <template v-if="verdictFor(hotel.id, group).status === 'exceeds-stock'">
                                                        Only {{ group.available_count }} left for these dates.
                                                    </template>
                                                    <template v-else-if="verdictFor(hotel.id, group).status === 'over-roomed'">
                                                        That's {{ verdictFor(hotel.id, group).projectedRooms }} rooms for {{ guests }} guest{{ guests === 1 ? '' : 's' }}. Fine if that's deliberate.
                                                    </template>
                                                    <template v-else>
                                                        Sleeps {{ verdictFor(hotel.id, group).projectedCapacity }} of {{ guests }}.
                                                        <template v-if="verdictFor(hotel.id, group).cannotFinishAlone">
                                                            Not enough of this type left for everyone - add another room type too.
                                                        </template>
                                                        <template v-else>
                                                            Add more of these, or mix in another room type.
                                                        </template>
                                                    </template>
                                                </p>
                                            </div>

                                            <div class="lg:text-right">
                                                <p class="font-semibold text-foreground">
                                                    {{ formatMoney(group.price_per_night) }}
                                                    <span class="font-normal text-foreground-muted">/ night</span>
                                                </p>
                                                <p class="text-xs text-foreground-muted">
                                                    {{ formatMoney(quantityFor(hotel.id, group) * nights * group.price_per_night) }} total
                                                </p>
                                            </div>

                                            <div class="flex items-center justify-end gap-3">
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
                                                    Add to itinerary
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
                    </article>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
