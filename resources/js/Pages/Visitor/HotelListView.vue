<script setup>
/**
 * Browse the island's hotels.
 *
 * This page only has to make a hotel worth opening - the rooms, dates and
 * booking now live on the hotel's own page. It used to fetch every hotel's room
 * types on load and again on every date change, one request per hotel, purely
 * so an expanding card could show a price.
 */
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FacilityList from '@/Components/FacilityList.vue';
import FilterRail from '@/Components/FilterRail.vue';
import FilterSection from '@/Components/FilterSection.vue';
import ListingCard from '@/Components/ListingCard.vue';
import PromotionsStrip from '@/Components/PromotionsStrip.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import { formatDateRange, todayIso } from '@/utils/format';
import { facilityMeta } from '@/utils/facilities';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';

const route = useRoute();
const hotelStore = useHotelStore();
const auth = useAuthStore();

const today = todayIso();

const SORTS = [
    { key: 'name', label: 'A – Z' },
    { key: 'rooms', label: 'Most rooms' },
];

const search = ref('');
const selectedFacilities = ref([]);
const sort = ref('name');

// The stay a visitor may already have chosen elsewhere. Carried through to the
// detail page rather than used here - this page no longer prices anything.
const stayQuery = computed(() => {
    const { check_in: checkIn, check_out: checkOut, guests } = route.query;
    return {
        ...(checkIn ? { check_in: String(checkIn) } : {}),
        ...(checkOut ? { check_out: String(checkOut) } : {}),
        ...(guests ? { guests: String(guests) } : {}),
    };
});

// Facets built from the loaded data rather than a fixed list, so a facility
// nobody offers never appears as a dead filter.
const facilityOptions = computed(() => {
    const counts = new Map();
    for (const hotel of hotelStore.hotels) {
        for (const slug of hotel.facilities ?? []) {
            counts.set(slug, (counts.get(slug) ?? 0) + 1);
        }
    }
    return [...counts.entries()]
        .map(([slug, count]) => ({ slug, count, label: facilityMeta(slug).label }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const toggleFacility = (slug) => {
    selectedFacilities.value = selectedFacilities.value.includes(slug)
        ? selectedFacilities.value.filter((s) => s !== slug)
        : [...selectedFacilities.value, slug];
};

const visibleHotels = computed(() => {
    let list = hotelStore.hotels;

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((hotel) =>
            `${hotel.name ?? ''} ${hotel.description ?? ''} ${hotel.address ?? ''}`
                .toLowerCase()
                .includes(query)
        );
    }

    // Every selected facility must be present, not any - a visitor ticking both
    // "pool" and "spa" is describing one hotel, not two.
    if (selectedFacilities.value.length) {
        list = list.filter((hotel) =>
            selectedFacilities.value.every((slug) => (hotel.facilities ?? []).includes(slug))
        );
    }

    const sorted = [...list];
    if (sort.value === 'rooms') {
        sorted.sort((a, b) => (b.total_rooms ?? 0) - (a.total_rooms ?? 0));
    } else {
        sorted.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
    }
    return sorted;
});

const activeFilterCount = computed(
    () =>
        (search.value.trim() ? 1 : 0) +
        (selectedFacilities.value.length ? 1 : 0) +
        (sort.value !== 'name' ? 1 : 0)
);

const clearFilters = () => {
    search.value = '';
    selectedFacilities.value = [];
    sort.value = 'name';
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

onMounted(() => {
    hotelStore.fetchHotels();
    if (auth.isAuthenticated) hotelStore.fetchMyBookings({ silent: true });
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader
                title="Hotels"
                subtitle="Where to stay on the island. Open a hotel to see its rooms and book."
                icon="hotel"
            />
        </template>

        <div class="shell space-y-5 py-6">
            <PromotionsStrip category="hotel" />

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
                <!-- Sticks just below the app header. The page no longer has a
                     sticky search bar of its own for it to clear. -->
                <div class="xl:sticky xl:top-[5.5rem] xl:max-h-[calc(100vh-7rem)] xl:self-start xl:overflow-y-auto">
                    <FilterRail
                        :result-label="resultLabel"
                        :active-count="activeFilterCount"
                        @clear="clearFilters"
                    >
                        <template #search>
                            <TInput v-model="search" placeholder="Search hotels" icon="search" />
                        </template>

                        <FilterSection
                            v-if="facilityOptions.length"
                            title="Facilities"
                            :hint="selectedFacilities.length ? `${selectedFacilities.length} selected` : 'Any'"
                        >
                            <div class="space-y-2.5">
                                <label
                                    v-for="option in facilityOptions"
                                    :key="option.slug"
                                    class="flex cursor-pointer items-center gap-3 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                        :checked="selectedFacilities.includes(option.slug)"
                                        @change="toggleFacility(option.slug)"
                                    />
                                    <span class="flex-1 text-foreground">{{ option.label }}</span>
                                    <span class="text-xs text-foreground-muted">{{ option.count }}</span>
                                </label>
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
                    <div v-if="hotelStore.loading.hotels" class="space-y-4">
                        <div v-for="n in 3" :key="n" class="h-44 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>
                    <p v-else-if="hotelStore.error.hotels" class="text-danger">{{ hotelStore.error.hotels }}</p>
                    <TEmptyState
                        v-else-if="hotelStore.hotels.length === 0"
                        icon="hotel"
                        title="No hotels available yet"
                        description="Check back soon - places to stay will appear here."
                    />
                    <TEmptyState
                        v-else-if="visibleHotels.length === 0"
                        icon="search"
                        title="No hotels match your filters"
                        description="Try removing a facility or clearing the search."
                    />

                    <ListingCard
                        v-for="(hotel, index) in visibleHotels"
                        :key="hotel.id"
                        :to="{ name: 'hotels.show', params: { id: hotel.id }, query: stayQuery }"
                        :title="hotel.name"
                        :description="hotel.description"
                        :image-url="hotel.image_url"
                        :index="index"
                        icon="hotel"
                        cta="See rooms & book"
                    >
                        <template #meta>
                            <span v-if="hotel.address" class="inline-flex items-center gap-1">
                                <TIcon name="pin" :size="12" /> {{ hotel.address }}
                            </span>
                            <span v-if="hotel.total_rooms" class="inline-flex items-center gap-1">
                                <TIcon name="bed" :size="12" /> {{ hotel.total_rooms }} rooms
                            </span>
                        </template>

                        <template v-if="hotel.facilities?.length" #chips>
                            <FacilityList :items="hotel.facilities" variant="chips" :limit="4" />
                        </template>
                    </ListingCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
