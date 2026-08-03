<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import IslandMap from '@/Components/IslandMap.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useCartStore } from '@/stores/cart';
import { formatDate } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useThemeParkStore } from '@/stores/themepark';
import { usePromotionsStore } from '@/stores/promotions';
import { useTheme } from '@/composables/useTheme';

const auth = useAuthStore();
const hotelStore = useHotelStore();
const themeParkStore = useThemeParkStore();
const promotionsStore = usePromotionsStore();
const cart = useCartStore();
const mapLocations = ref([]);
const loadingSections = ref(true);
const { isDark, toggle: toggleTheme } = useTheme();

const CATEGORY_ROUTES = {
    hotel: { name: 'hotels.index' },
    themepark: { name: 'themepark.home' },
    ferry: { name: 'ferry.book' },
    general: { name: 'welcome' },
};

const CATEGORY_VARIANT = {
    hotel: 'success',
    themepark: 'info',
    ferry: 'warning',
    general: 'neutral',
};

onMounted(async () => {
    if (!auth.loaded) auth.fetchUser();

    // Awaited together so the sections show a skeleton until there's a real
    // answer - otherwise "No hotels yet." renders while the request is still
    // in flight, which reads as an empty island.
    await Promise.allSettled([
        hotelStore.fetchPopularHotels(),
        themeParkStore.fetchPopularEvents(),
        themeParkStore.fetchEvents(),
        promotionsStore.fetchActive(),
        // The only fetch on this page that bypasses a store; an unhandled
        // rejection here used to leave the map silently empty.
        axios.get('/api/map/locations')
            .then(({ data }) => { mapLocations.value = data; })
            .catch(() => { mapLocations.value = []; }),
    ]);

    loadingSections.value = false;
});

// A promotion deep-links to the matching entity when its title names one, so
// "50% off Sunset Cruise" doesn't just dump you on the unfiltered ferry page.
const promoTarget = (promo) => {
    const events = themeParkStore.events ?? [];
    const match = events.find((e) => promo.title?.toLowerCase().includes(e.name?.toLowerCase()));
    if (match) return { name: 'themepark.home', query: { event: match.id } };

    const hotels = hotelStore.popularHotels ?? [];
    const hotelMatch = hotels.find((h) => promo.title?.toLowerCase().includes(h.name?.toLowerCase()));
    if (hotelMatch) return { name: 'hotels.index', query: { hotel: hotelMatch.id } };

    return CATEGORY_ROUTES[promo.category] ?? CATEGORY_ROUTES.general;
};

const promoEndsLabel = (promo) => (promo.ends_at ? `Ends ${formatDate(promo.ends_at, { weekday: false })}` : '');

const showCart = computed(() => cart.count > 0);

// The three legs of an island trip, in the order the system actually enforces:
// a room comes first, because a ferry seat is booked against a stay. Showing
// that dependency up front is the whole job of this panel - it's the rule
// visitors otherwise only discover when the ferry page turns them away.
const hasType = (type) => cart.items.some((item) => item.type === type);

const legs = computed(() => [
    {
        key: 'stay',
        icon: 'hotel',
        title: 'Find a room',
        blurb: hotelStore.popularHotels.length
            ? `${hotelStore.popularHotels.length} place${hotelStore.popularHotels.length === 1 ? '' : 's'} to stay on the island`
            : 'Pick your hotel and the dates you are on the island',
        to: { name: 'hotels.index' },
        done: hasType('hotel'),
        locked: false,
        note: null,
    },
    {
        key: 'cross',
        icon: 'ferry',
        title: 'Reserve ferry seats',
        blurb: 'Crossings are fixed to your check-in and check-out days',
        to: { name: 'ferry.book' },
        done: hasType('ferry'),
        locked: !hasType('hotel'),
        note: 'Add a room first',
    },
    {
        key: 'play',
        icon: 'sparkle',
        title: 'Book park time',
        blurb: themeParkStore.events.length
            ? `${themeParkStore.events.length} ride${themeParkStore.events.length === 1 ? '' : 's'}, show${themeParkStore.events.length === 1 ? '' : 's'} and beach events`
            : 'Rides, shows and beach events by time slot',
        to: { name: 'themepark.home' },
        done: hasType('themepark'),
        locked: false,
        note: null,
    },
]);
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Hero. Deep-water gradient: near-black at the top corner falling to
             teal, with a warm sun bloom off to one side. Fixed in both themes -
             it reads as the sea, not as a surface. The nav sits inside it so
             the page opens on water rather than on a white strip. -->
        <section class="relative overflow-hidden bg-[#061f22] bg-gradient-to-br from-[#04181b] via-[#0a3f42] to-[#10635f]">
            <div
                class="pointer-events-none absolute -right-32 -top-40 h-[32rem] w-[32rem] rounded-full bg-accent/20 blur-3xl"
                aria-hidden="true"
            />

            <!-- Top bar. The wordmark is written out here rather than reusing
                 ApplicationLogo because that one is teal from the theme tokens,
                 and the hero is dark in both themes. -->
            <div class="relative z-10 shell flex items-center justify-between py-5">
                <span class="inline-flex items-center gap-1">
                    <span class="text-xl font-bold tracking-tight text-white">TPMS</span>
                    <span class="mb-0.5 h-1.5 w-1.5 self-end rounded-full bg-accent" aria-hidden="true" />
                </span>

                <div class="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="rounded-lg p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                    >
                        <TIcon :name="isDark ? 'sun' : 'moon'" :size="19" />
                    </button>

                    <!-- Landing has no app nav, so a visitor returning here with
                         items in their itinerary would otherwise lose every route
                         back to them. -->
                    <router-link
                        v-if="showCart"
                        :to="{ name: 'cart.checkout' }"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-white/20"
                    >
                        <TIcon name="cart" :size="16" />
                        Itinerary ({{ cart.count }})
                    </router-link>

                    <template v-if="auth.isAuthenticated">
                        <router-link
                            :to="{ name: 'dashboard' }"
                            class="text-sm font-medium text-white/80 transition-colors hover:text-white"
                        >
                            Go to dashboard
                        </router-link>
                    </template>
                    <template v-else>
                        <router-link
                            :to="{ name: 'login' }"
                            class="hidden text-sm font-medium text-white/80 transition-colors hover:text-white sm:block"
                        >
                            Log in
                        </router-link>
                        <router-link :to="{ name: 'register' }">
                            <TButton variant="accent" size="sm" as="span">Register</TButton>
                        </router-link>
                    </template>
                </div>
            </div>

            <div class="relative z-10 shell grid items-center gap-12 pb-32 pt-12 lg:grid-cols-[1.05fr_minmax(24rem,0.95fr)] lg:gap-16 lg:pb-40 lg:pt-20">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">
                        Plan your island visit
                    </p>
                    <h1 class="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Everything on the island,<br class="hidden sm:block" />
                        in one itinerary.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/70">
                        Rooms, ferry crossings and theme park time slots — chosen together, paid together.
                        Start without an account and sign in when you check out.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <router-link :to="{ name: 'hotels.index' }">
                            <TButton variant="accent" size="lg" as="span">Start with a room</TButton>
                        </router-link>
                        <router-link
                            :to="{ name: 'themepark.home' }"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-white/25 px-5 py-2.5 text-base font-medium text-white transition-colors hover:bg-white/10"
                        >
                            Browse the park
                            <TIcon name="arrowRight" :size="17" />
                        </router-link>
                    </div>
                </div>

                <!-- The signature panel: the product itself, shown being filled
                     in. Live from the cart, so it doubles as progress once the
                     visitor has started. -->
                <div class="elevated-lg rounded-xl border border-white/10 bg-white/[0.07] p-2 backdrop-blur-md">
                    <div class="rounded-xl bg-[#04191c]/60 p-5 sm:p-6">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="text-sm font-semibold tracking-tight text-white">One trip, three legs</h2>
                            <span class="text-xs text-white/50">
                                {{ legs.filter((l) => l.done).length }} of 3 started
                            </span>
                        </div>
                        <div class="perforation my-4 opacity-20" aria-hidden="true" />

                        <ol class="relative space-y-1">
                            <!-- Connecting rail. The legs are a real sequence -
                                 the server refuses a ferry seat without a stay -
                                 so the line encodes a dependency, not decoration. -->
                            <span
                                class="absolute bottom-9 left-[2.125rem] top-9 w-px bg-white/25"
                                aria-hidden="true"
                            />

                            <li v-for="leg in legs" :key="leg.key" class="relative">
                                <router-link
                                    :to="leg.to"
                                    class="group flex items-start gap-4 rounded-lg p-3 transition-colors hover:bg-white/10"
                                >
                                    <span
                                        class="relative z-10 grid h-11 w-11 shrink-0 place-items-center rounded-full border transition-colors"
                                        :class="leg.done
                                            ? 'border-accent bg-accent text-accent-fg'
                                            : 'border-white/20 bg-[#04191c] text-white/70 group-hover:border-white/40 group-hover:text-white'"
                                    >
                                        <TIcon :name="leg.done ? 'check' : leg.icon" :size="19" />
                                    </span>
                                    <span class="min-w-0 flex-1 pt-0.5">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-white">{{ leg.title }}</span>
                                            <span
                                                v-if="leg.locked"
                                                class="rounded-full border border-white/15 px-2 py-0.5 text-[11px] text-white/50"
                                            >
                                                {{ leg.note }}
                                            </span>
                                        </span>
                                        <span class="mt-0.5 block text-sm leading-snug text-white/55">{{ leg.blurb }}</span>
                                    </span>
                                    <TIcon
                                        name="chevronRight"
                                        :size="16"
                                        class="mt-3 text-white/30 transition-transform group-hover:translate-x-0.5 group-hover:text-white/60"
                                    />
                                </router-link>
                            </li>
                        </ol>

                        <router-link
                            v-if="showCart"
                            :to="{ name: 'cart.checkout' }"
                            class="mt-4 flex items-center justify-between rounded-lg bg-accent/15 px-4 py-3 text-sm font-medium text-accent transition-colors hover:bg-accent/25"
                        >
                            Review and check out
                            <TIcon name="arrowRight" :size="16" />
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Shoreline. Two offset wave bands in the page colour, so the hero
                 meets the content as water meets sand rather than as a hard rule. -->
            <svg
                class="pointer-events-none absolute inset-x-0 bottom-0 h-16 w-full sm:h-24"
                viewBox="0 0 1440 120"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    d="M0 62c120-26 240-26 360 0s240 26 360 0 240-26 360 0 240 26 360 0v58H0z"
                    fill="rgb(var(--color-background))"
                    opacity="0.35"
                />
                <path
                    d="M0 86c120-24 240-24 360 0s240 24 360 0 240-24 360 0 240 24 360 0v34H0z"
                    fill="rgb(var(--color-background))"
                />
            </svg>
        </section>

        <!-- Offers. The reading sections deliberately sit on a narrower measure
             than the hero and the map: a row of three teasers stretched across
             the full band reads as a half-empty shelf, and the change of width
             is what gives the page a rhythm instead of one uniform column. -->
        <section v-if="promotionsStore.active.length > 0" class="shell pb-4 pt-12">
            <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-foreground">Offers on now</h2>
                <p class="text-sm text-foreground-muted">Applied automatically at checkout</p>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <router-link
                    v-for="promo in promotionsStore.active"
                    :key="promo.id"
                    :to="promoTarget(promo)"
                    class="elevated lift group flex flex-col overflow-hidden rounded-xl border bg-surface"
                >
                    <div class="flex h-40 w-full items-center justify-center overflow-hidden bg-surface-hover text-foreground-muted">
                        <img
                            v-if="promo.image_url"
                            :src="promo.image_url"
                            :alt="promo.title"
                            loading="lazy"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <TIcon v-else name="sparkle" :size="28" />
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-center gap-2">
                            <TBadge :variant="CATEGORY_VARIANT[promo.category]">{{ promo.category }}</TBadge>
                            <span v-if="promoEndsLabel(promo)" class="text-xs font-medium text-warning">
                                {{ promoEndsLabel(promo) }}
                            </span>
                        </div>
                        <h3 class="font-semibold text-foreground group-hover:text-primary">{{ promo.title }}</h3>
                        <p v-if="promo.description" class="text-sm text-foreground-muted">{{ promo.description }}</p>
                    </div>
                </router-link>
            </div>
            </div>
        </section>

        <!-- Popular theme park events -->
        <section class="shell py-12">
            <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-foreground">Popular in the park</h2>
                <router-link :to="{ name: 'themepark.home' }" class="text-sm font-medium text-primary hover:underline">
                    See every event &rarr;
                </router-link>
            </div>

            <div v-if="loadingSections" class="grid grid-cols-2 gap-5 lg:grid-cols-3">
                <div v-for="n in 3" :key="n" class="h-56 animate-pulse rounded-xl border bg-surface-hover" />
            </div>
            <div v-else-if="themeParkStore.popularEvents.length === 0" class="text-sm text-foreground-muted">
                No events scheduled just yet — check back soon.
            </div>
            <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <router-link
                    v-for="event in themeParkStore.popularEvents"
                    :key="event.id"
                    :to="{ name: 'themepark.home', query: { event: event.id } }"
                    class="elevated lift group block overflow-hidden rounded-xl border bg-surface"
                >
                    <div class="flex aspect-[3/2] items-center justify-center overflow-hidden bg-surface-hover text-foreground-muted">
                        <img
                            v-if="event.image_url"
                            :src="event.image_url"
                            :alt="event.name"
                            loading="lazy"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <TIcon v-else name="sparkle" :size="26" />
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-foreground group-hover:text-primary">{{ event.name }}</h3>
                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-foreground-muted">
                            <TIcon name="pin" :size="13" /> {{ event.location }}
                        </p>
                    </div>
                </router-link>
            </div>
            </div>
        </section>

        <!-- Popular hotels -->
        <section class="border-y bg-surface">
            <div class="shell py-12">
                <div class="mx-auto max-w-6xl">
                <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">Places to stay</h2>
                    <router-link :to="{ name: 'hotels.index' }" class="text-sm font-medium text-primary hover:underline">
                        See every hotel &rarr;
                    </router-link>
                </div>

                <div v-if="loadingSections" class="grid grid-cols-2 gap-5 lg:grid-cols-3">
                    <div v-for="n in 3" :key="n" class="h-56 animate-pulse rounded-xl border bg-page" />
                </div>
                <div v-else-if="hotelStore.popularHotels.length === 0" class="text-sm text-foreground-muted">
                    No hotels listed just yet — check back soon.
                </div>
                <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <router-link
                        v-for="hotel in hotelStore.popularHotels"
                        :key="hotel.id"
                        :to="{ name: 'hotels.index', query: { hotel: hotel.id } }"
                        class="elevated lift group block overflow-hidden rounded-xl border bg-page"
                    >
                        <div class="flex aspect-[3/2] items-center justify-center overflow-hidden bg-surface-hover text-foreground-muted">
                            <img
                                v-if="hotel.image_url"
                                :src="hotel.image_url"
                                :alt="hotel.name"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            <TIcon v-else name="hotel" :size="26" />
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-foreground group-hover:text-primary">{{ hotel.name }}</h3>
                            <p class="mt-1 inline-flex items-center gap-1 text-xs text-foreground-muted">
                                <TIcon name="pin" :size="13" /> {{ hotel.address }}
                            </p>
                        </div>
                    </router-link>
                </div>
                </div>
            </div>
        </section>

        <!-- Island map -->
        <section class="shell py-12">
            <IslandMap :events="themeParkStore.events" :locations="mapLocations" />
        </section>
    </div>
</template>
