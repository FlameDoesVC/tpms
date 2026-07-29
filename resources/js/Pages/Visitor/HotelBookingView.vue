<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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

const canBook = (hotelId, group) => {
    const quantity = quantityFor(hotelId, group);
    return quantity >= 1 && quantity <= group.available_count && quantity * group.max_guests >= guests.value;
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

                <div class="elevated sticky top-[4.25rem] z-10 flex flex-wrap gap-4 rounded-xl border bg-surface p-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-foreground-secondary">Hotels</label>
                            <button
                                v-if="selectedHotelIds.length"
                                type="button"
                                @click="selectedHotelIds = []"
                                class="text-xs text-primary hover:underline"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="mt-1 flex max-h-20 w-48 flex-col gap-1 overflow-y-auto rounded-lg border p-2">
                            <label
                                v-for="hotel in hotelStore.hotels"
                                :key="hotel.id"
                                class="flex items-center gap-2 text-sm text-foreground-secondary"
                            >
                                <input type="checkbox" :value="hotel.id" v-model="selectedHotelIds" />
                                {{ hotel.name }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Check in</label>
                        <input
                            type="date"
                            v-model="checkIn"
                            class="mt-1 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Check out</label>
                        <input
                            type="date"
                            v-model="checkOut"
                            class="mt-1 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Guests</label>
                        <input
                            type="number"
                            min="1"
                            v-model.number="guests"
                            class="mt-1 w-20 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
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

                <div v-else class="space-y-8">
                    <div
                        v-for="hotel in visibleHotels"
                        :key="hotel.id"
                        class="elevated rounded-xl border bg-surface p-6"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted">
                                <img v-if="hotel.image_url" :src="hotel.image_url" :alt="hotel.name" class="h-full w-full object-cover" />
                                <span v-else>No image</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-foreground">{{ hotel.name }}</h3>
                                    <TBadge :variant="hotel.is_active ? 'success' : 'neutral'" class="shrink-0">
                                        {{ hotel.is_active ? 'Open' : 'Closed' }}
                                    </TBadge>
                                </div>
                                <p class="mt-1 line-clamp-2 text-sm text-foreground-secondary">{{ hotel.description }}</p>
                                <p class="mt-1 text-xs text-foreground-muted">{{ hotel.address }}</p>
                            </div>
                        </div>

                        <p
                            v-if="hotelMessages[hotel.id]"
                            class="mt-4 text-sm"
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

                        <div class="mt-4">
                            <div v-if="loadingTypes[hotel.id]" class="text-sm text-foreground-muted">Loading rooms...</div>
                            <div
                                v-else-if="(roomTypesByHotel[hotel.id]?.length ?? 0) === 0"
                                class="rounded-lg bg-surface-hover p-4 text-sm text-foreground-muted"
                            >
                                Fully booked for these dates.
                            </div>
                            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div
                                    v-for="group in roomTypesByHotel[hotel.id]"
                                    :key="`${group.type}-${group.price_per_night}-${group.max_guests}`"
                                    class="flex items-center justify-between gap-4 rounded-xl border p-4"
                                >
                                    <div>
                                        <p class="font-semibold capitalize text-foreground">{{ group.type }}</p>
                                        <p class="text-sm text-foreground-muted">Up to {{ group.max_guests }} guests / room</p>
                                        <p class="mt-1 font-medium text-foreground">${{ group.price_per_night }} / night</p>
                                        <p class="mt-1 text-xs text-foreground-muted">{{ group.available_count }} available</p>
                                        <p v-if="maxCapacity(group) < guests" class="mt-1 text-xs font-medium text-danger">
                                            Doesn't fit {{ guests }} guests, only {{ group.available_count }} room(s) available.
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                                            Rooms
                                            <input
                                                type="number"
                                                min="1"
                                                :max="group.available_count"
                                                :value="quantityFor(hotel.id, group)"
                                                @input="setQuantity(hotel.id, group, Number($event.target.value))"
                                                class="w-16 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                                            />
                                        </label>
                                        <TButton :disabled="!canBook(hotel.id, group)" @click="addToCart(hotel, group)">
                                            Add to Cart
                                        </TButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
