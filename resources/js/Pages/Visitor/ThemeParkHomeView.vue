<script setup>
/**
 * Browse what's on in the park.
 *
 * Each attraction now has its own page, which is where the schedule and the
 * tickets live. This page used to fetch every event's slots for the chosen
 * date - one request per event, again on every date change - only so an
 * accordion could show a row of times.
 */
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FilterRail from '@/Components/FilterRail.vue';
import FilterSection from '@/Components/FilterSection.vue';
import ListingCard from '@/Components/ListingCard.vue';
import PromotionsStrip from '@/Components/PromotionsStrip.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import { formatDateTime, formatMoney, todayIso } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import { useThemeParkStore } from '@/stores/themepark';

const route = useRoute();
const themeParkStore = useThemeParkStore();
const auth = useAuthStore();
const cart = useCartStore();

const TYPES = [
    { key: 'ride', label: 'Rides' },
    { key: 'show', label: 'Shows' },
    { key: 'beach_event', label: 'Beach events' },
];
const TYPE_LABELS = { ride: 'Ride', show: 'Show', beach_event: 'Beach event' };

const SORTS = [
    { key: 'name', label: 'A – Z' },
    { key: 'price', label: 'Cheapest' },
];

const today = todayIso();

const search = ref('');
const selectedTypes = ref([]);
const maxPrice = ref(null);
const sort = ref('name');

// A date chosen elsewhere rides along to the attraction page rather than
// filtering here - this page no longer knows anything about the schedule.
const dateQuery = computed(() => (route.query.date ? { date: String(route.query.date) } : {}));

// Prices come off the API as decimal strings, so every comparison coerces.
const priceCeiling = computed(() => {
    const prices = themeParkStore.events.map((e) => Number(e.price_per_ticket) || 0);
    return prices.length ? Math.ceil(Math.max(...prices) / 5) * 5 : 0;
});
const priceCap = computed(() => (maxPrice.value == null ? priceCeiling.value : maxPrice.value));
const priceIsCapped = computed(() => priceCeiling.value > 0 && priceCap.value < priceCeiling.value);

const typeCounts = computed(() => {
    const counts = {};
    for (const event of themeParkStore.events) {
        counts[event.type] = (counts[event.type] ?? 0) + 1;
    }
    return counts;
});

const toggleType = (type) => {
    selectedTypes.value = selectedTypes.value.includes(type)
        ? selectedTypes.value.filter((t) => t !== type)
        : [...selectedTypes.value, type];
};

const visibleEvents = computed(() => {
    let list = themeParkStore.events;

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((e) =>
            `${e.name ?? ''} ${e.description ?? ''} ${e.location ?? ''}`.toLowerCase().includes(query)
        );
    }

    if (selectedTypes.value.length) {
        list = list.filter((e) => selectedTypes.value.includes(e.type));
    }

    if (priceIsCapped.value) {
        list = list.filter((e) => (Number(e.price_per_ticket) || 0) <= priceCap.value);
    }

    const sorted = [...list];
    if (sort.value === 'price') {
        sorted.sort((a, b) => Number(a.price_per_ticket) - Number(b.price_per_ticket));
    } else {
        sorted.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
    }
    return sorted;
});

const activeFilterCount = computed(
    () =>
        (search.value.trim() ? 1 : 0) +
        (selectedTypes.value.length ? 1 : 0) +
        (priceIsCapped.value ? 1 : 0) +
        (sort.value !== 'name' ? 1 : 0)
);

const clearFilters = () => {
    search.value = '';
    selectedTypes.value = [];
    maxPrice.value = priceCeiling.value;
    sort.value = 'name';
};

const resultLabel = computed(() => {
    const total = themeParkStore.events.length;
    const shown = visibleEvents.value.length;
    if (!total) return null;
    return shown === total
        ? `${total} attraction${total === 1 ? '' : 's'}`
        : `${shown} of ${total} attractions`;
});

const upcomingBookings = computed(() =>
    themeParkStore.myBookings
        .filter((b) => b.status === 'confirmed' && (b.slot?.slot_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

// How many of this attraction's tickets are already in the itinerary, so a
// second visit to the page does not read as a blank slate.
const ticketsInCart = (eventId) =>
    cart.items
        .filter((item) => item.type === 'themepark' && item.eventId === eventId)
        .reduce((sum, item) => sum + item.ticketCount, 0);

onMounted(async () => {
    await themeParkStore.fetchEvents();
    if (auth.isAuthenticated) themeParkStore.fetchMyBookings({ silent: true });

    maxPrice.value = priceCeiling.value;
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader
                title="Theme Park"
                subtitle="Rides, shows and beach events. Open one to see its times and book."
                icon="sparkle"
            />
        </template>

        <div class="shell space-y-5 pb-6 pt-5">
            <PromotionsStrip category="themepark" />

            <div v-if="upcomingBookings.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border bg-surface-sunken px-4 py-2.5 text-sm">
                <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                    <TIcon name="ticket" :size="15" class="text-primary" /> Already booked
                </span>
                <span v-for="booking in upcomingBookings" :key="booking.id" class="text-foreground-secondary">
                    {{ booking.slot?.event?.name }} ·
                    {{ formatDateTime(booking.slot?.slot_date, booking.slot?.slot_time) }}
                    ({{ booking.ticket_count }})
                </span>
                <router-link :to="{ name: 'trips', query: { tab: 'park' } }" class="ml-auto font-medium text-primary hover:underline">
                    View all
                </router-link>
            </div>

            <div class="grid gap-5 xl:grid-cols-[17rem_minmax(0,1fr)] xl:gap-7">
                <!-- Sticks just below the app header: the page no longer has a
                     sticky date bar of its own for it to clear. -->
                <div class="xl:sticky xl:top-[5.5rem] xl:max-h-[calc(100vh-7rem)] xl:self-start xl:overflow-y-auto">
                    <FilterRail :result-label="resultLabel" :active-count="activeFilterCount" @clear="clearFilters">
                        <template #search>
                            <TInput v-model="search" placeholder="Search attractions" icon="search" />
                        </template>

                        <FilterSection
                            title="Experience"
                            :hint="selectedTypes.length ? `${selectedTypes.length} selected` : 'All'"
                        >
                            <div class="space-y-2.5">
                                <label
                                    v-for="type in TYPES"
                                    :key="type.key"
                                    class="flex cursor-pointer items-center gap-3 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                        :checked="selectedTypes.includes(type.key)"
                                        @change="toggleType(type.key)"
                                    />
                                    <span class="flex-1 text-foreground">{{ type.label }}</span>
                                    <span class="text-xs text-foreground-muted">{{ typeCounts[type.key] ?? 0 }}</span>
                                </label>
                            </div>
                        </FilterSection>

                        <FilterSection
                            v-if="priceCeiling > 0"
                            title="Price per ticket"
                            :hint="priceIsCapped ? `Up to ${formatMoney(priceCap)}` : 'Any'"
                        >
                            <input
                                type="range"
                                class="w-full accent-[rgb(var(--color-primary))]"
                                :min="0"
                                :max="priceCeiling"
                                :step="5"
                                :value="priceCap"
                                @input="maxPrice = Number($event.target.value)"
                            />
                            <div class="mt-1 flex justify-between text-xs text-foreground-muted">
                                <span>{{ formatMoney(0) }}</span>
                                <span>{{ formatMoney(priceCeiling) }}</span>
                            </div>
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
                    <div v-if="themeParkStore.loading.events" class="space-y-4">
                        <div v-for="n in 3" :key="n" class="h-44 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>
                    <p v-else-if="themeParkStore.error.events" class="text-danger">{{ themeParkStore.error.events }}</p>
                    <TEmptyState
                        v-else-if="themeParkStore.events.length === 0"
                        icon="sparkle"
                        title="Nothing on just yet"
                        description="Rides, shows and beach events will appear here."
                    />
                    <TEmptyState
                        v-else-if="visibleEvents.length === 0"
                        icon="search"
                        title="No attractions match your filters"
                        description="Try another experience type, or raise the price cap."
                    />

                    <ListingCard
                        v-for="(event, index) in visibleEvents"
                        :key="event.id"
                        :to="{ name: 'themepark.event', params: { id: event.id }, query: dateQuery }"
                        :title="event.name"
                        :description="event.description"
                        :image-url="event.image_url"
                        :index="index"
                        icon="sparkle"
                        cta="See times & book"
                    >
                        <template #meta>
                            <TBadge variant="neutral" size="sm">{{ TYPE_LABELS[event.type] ?? event.type }}</TBadge>
                            <span v-if="event.location" class="inline-flex items-center gap-1">
                                <TIcon name="pin" :size="12" /> {{ event.location }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <TIcon name="clock" :size="12" /> {{ event.duration_minutes }} min
                            </span>
                            <span v-if="ticketsInCart(event.id)" class="inline-flex items-center gap-1 text-primary">
                                <TIcon name="cart" :size="12" /> {{ ticketsInCart(event.id) }} in your itinerary
                            </span>
                        </template>

                        <template #price>
                            <div>
                                <p class="text-lg font-semibold tracking-tight text-foreground">
                                    {{ formatMoney(event.price_per_ticket) }}
                                </p>
                                <p class="text-xs text-foreground-muted">per ticket</p>
                            </div>
                        </template>
                    </ListingCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
