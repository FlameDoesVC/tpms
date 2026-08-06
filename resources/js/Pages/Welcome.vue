<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import IslandMap from '@/Components/IslandMap.vue';
import MediaRail from '@/Components/MediaRail.vue';
import RailCard from '@/Components/RailCard.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useCartStore } from '@/stores/cart';
import { formatDate } from '@/utils/format';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useThemeParkStore } from '@/stores/themepark';
import { usePromotionsStore } from '@/stores/promotions';
import { useTheme } from '@/composables/useTheme';
import { useReveal } from '@/composables/useReveal';

const auth = useAuthStore();
const hotelStore = useHotelStore();
const themeParkStore = useThemeParkStore();
const promotionsStore = usePromotionsStore();
const cart = useCartStore();
const mapLocations = ref([]);
const loadingSections = ref(true);
const { isDark, toggle: toggleTheme } = useTheme();

const page = ref(null);
const { scan } = useReveal(page);

const CATEGORY_ROUTES = {
    hotel: { name: 'hotels.index' },
    themepark: { name: 'themepark.home' },
    ferry: { name: 'ferry.book' },
    general: { name: 'welcome' },
};

const CATEGORY_LABEL = {
    hotel: 'Stay',
    themepark: 'Park',
    ferry: 'Ferry',
    general: 'Offer',
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
    // Sections gated behind this fetch (the offers rail) weren't in the DOM
    // for the observer's first pass.
    scan();
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
    <div ref="page" class="min-h-screen bg-page">
        <!-- ================================ HERO ================================
             A body of water, and a different body of water per theme: a sunlit
             shallow beach that brightens toward the sand, or deep ocean lit at
             the surface and falling away to an abyss. Both come out of the
             .hero-sea tokens, so this markup carries no theme branching beyond
             choosing which optical effect belongs in which water. -->
        <!-- No `isolate` here, and it must not come back: isolation:isolate
             establishes a Backdrop Root, which makes backdrop-filter on any
             descendant sample an empty backdrop - so the itinerary card's frost
             blurred nothing at all. Nothing needed it: `relative` still provides
             the positioning context, `overflow-hidden` still clips the water and
             the surf to this section, and the z-10 content still paints above
             the z-auto decorations because they are all siblings. -->
        <section class="hero-sea relative overflow-hidden">
            <!-- Light shafts at depth; the rippling light net in the shallows. -->
            <div
                v-if="isDark"
                class="sea-rays pointer-events-none absolute inset-0"
                aria-hidden="true"
            />
            <div
                v-else
                class="sea-caustics pointer-events-none absolute inset-x-0 bottom-0 h-2/3"
                aria-hidden="true"
            />

            <!-- Top bar. -->
            <div class="rise-in shell relative z-10 flex items-center justify-between py-5">
                <img src="/images/logo.png" alt="TPMS" class="h-9 w-9 object-contain" />

                <div class="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="rounded-lg p-2 text-white/75 transition-colors hover:bg-white/10 hover:text-white"
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
                            class="text-sm font-medium text-white/85 transition-colors hover:text-white"
                        >
                            Go to dashboard
                        </router-link>
                    </template>
                    <template v-else>
                        <router-link
                            :to="{ name: 'login' }"
                            class="hidden text-sm font-medium text-white/85 transition-colors hover:text-white sm:block"
                        >
                            Log in
                        </router-link>
                        <router-link :to="{ name: 'register' }">
                            <TButton variant="accent" size="sm" as="span">Register</TButton>
                        </router-link>
                    </template>
                </div>
            </div>

            <div
                class="shell relative z-10 grid items-center gap-12 pb-36 pt-12 lg:grid-cols-[1.05fr_minmax(24rem,0.95fr)] lg:gap-16 lg:pb-44 lg:pt-20"
            >
                <!-- Two elements, one effect each. Both the load-in and the
                     scroll-linked sink are animation shorthands, so sharing an
                     element would let whichever rule comes last win outright. -->
                <div class="sink">
                <div class="rise-in" style="--r-delay: 90ms">
                    <p class="beacon-ink text-xs font-semibold uppercase tracking-[0.2em]">
                        Plan your Velaafinolhu visit
                    </p>
                    <h1 class="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                        Everything on the island,<br class="hidden sm:block" />
                        in one itinerary.
                    </h1>
                    <p class="sea-soft mt-6 max-w-xl text-lg leading-relaxed">
                        Rooms, ferry crossings and theme park time slots across
                        <span class="beacon-ink font-semibold">Velaafinolhu</span>, chosen together, paid
                        together. Start without an account and sign in when you check out.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <router-link :to="{ name: 'hotels.index' }">
                            <TButton variant="accent" size="lg" as="span">Start with a room</TButton>
                        </router-link>
                        <!-- Glassed rather than a bare outline: over the pale
                             shallows a transparent button's white label drops
                             under 4.5:1, and its own tint fixes that without
                             needing a second light-theme variant. -->
                        <router-link
                            :to="{ name: 'themepark.home' }"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-white/30 bg-[rgb(var(--sea-a)/0.5)] px-5 py-2.5 text-base font-medium text-white backdrop-blur-sm transition-colors hover:bg-[rgb(var(--sea-a)/0.78)]"
                        >
                            Browse the park
                            <TIcon name="arrowRight" :size="17" />
                        </router-link>
                    </div>
                </div>
                </div>

                <!-- The signature panel: the product itself, shown being filled
                     in. Live from the cart, so it doubles as progress once the
                     visitor has started. Dark glass in both themes - it reads as
                     an instrument resting on the water either way, and it keeps
                     one set of type colours legible over two very different seas. -->
                <div class="sink-slow">
                <div
                    class="trip-card elevated-lg rise-in rounded-xl border border-[var(--card-frame-line)] bg-[var(--card-frame)] p-2"
                    style="--r-delay: 220ms"
                >
                    <div class="rounded-xl bg-[var(--card-bg)] p-5 sm:p-6">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="text-sm font-semibold tracking-tight text-[var(--card-ink)]">One trip, three legs</h2>
                            <span class="text-xs text-[var(--card-ink-soft)]">
                                {{ legs.filter((l) => l.done).length }} of 3 started
                            </span>
                        </div>
                        <div class="trip-rule my-4" aria-hidden="true" />

                        <ol class="relative space-y-1">
                            <!-- Connecting rail. The legs are a real sequence -
                                 the server refuses a ferry seat without a stay -
                                 so the line encodes a dependency, not decoration. -->
                            <span
                                class="absolute bottom-9 left-[2.125rem] top-9 w-px bg-[rgb(var(--card-accent)/0.3)]"
                                aria-hidden="true"
                            />

                            <li v-for="leg in legs" :key="leg.key" class="relative">
                                <router-link
                                    :to="leg.to"
                                    class="group flex items-start gap-4 rounded-lg p-3 transition-colors hover:bg-[var(--card-hover)]"
                                >
                                    <!-- Beacons read --card-accent, not --beacon:
                                         the water-tuned beacon is a bright orange
                                         that needs a dark backdrop and dies on
                                         frosted glass. Still orange by day and
                                         amber by night, only deep enough to hold
                                         on a translucent card. The done state can
                                         use the plain accent as a fill, because a
                                         solid disc doesn't take the water's tint. -->
                                    <span
                                        class="relative z-10 grid h-11 w-11 shrink-0 place-items-center rounded-full ring-1 transition-all"
                                        :class="leg.done
                                            ? 'bg-accent text-accent-fg ring-accent shadow-[0_0_22px_-6px_rgb(var(--color-accent)/0.7)]'
                                            : leg.locked
                                                /* card-disc is a translucent wash in the dark theme (see
                                                   card-disc-base above) - painting it straight as the badge's
                                                   only background let the connecting rail (the span two levels
                                                   up) show straight through instead of stopping at its edge. */
                                                ? 'bg-[var(--card-disc-base)] [background-image:linear-gradient(var(--card-disc),var(--card-disc))] text-[var(--card-ink-faint)] ring-[var(--card-rule)]'
                                                : 'bg-[var(--card-disc-base)] [background-image:linear-gradient(var(--card-disc),var(--card-disc))] text-[rgb(var(--card-accent))] ring-[rgb(var(--card-accent)/0.4)] shadow-[0_0_18px_-7px_rgb(var(--card-accent)/0.55)] group-hover:[background-image:linear-gradient(rgb(var(--card-accent)/0.2),rgb(var(--card-accent)/0.2))]'"
                                    >
                                        <TIcon :name="leg.done ? 'check' : leg.icon" :size="19" />
                                    </span>
                                    <span class="min-w-0 flex-1 pt-0.5">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-[var(--card-ink)]">{{ leg.title }}</span>
                                            <span
                                                v-if="leg.locked"
                                                class="rounded-full border border-[var(--card-rule)] px-2 py-0.5 text-[11px] text-[var(--card-ink-soft)]"
                                            >
                                                {{ leg.note }}
                                            </span>
                                        </span>
                                        <span class="mt-0.5 block text-sm leading-snug text-[var(--card-ink-soft)]">{{ leg.blurb }}</span>
                                    </span>
                                    <TIcon
                                        name="chevronRight"
                                        :size="16"
                                        class="mt-3 text-[var(--card-ink-faint)] transition-transform group-hover:translate-x-0.5 group-hover:text-[var(--card-ink-soft)]"
                                    />
                                </router-link>
                            </li>
                        </ol>

                        <!-- Label in ordinary ink, not accent: accent text on an
                             accent tint over glass measures ~3.4:1, which is
                             fine for the arrow but under AA for the words. -->
                        <router-link
                            v-if="showCart"
                            :to="{ name: 'cart.checkout' }"
                            class="mt-4 flex items-center justify-between rounded-lg bg-accent/15 px-4 py-3 text-sm font-semibold text-[var(--card-ink)] transition-colors hover:bg-accent/25"
                        >
                            Review and check out
                            <TIcon name="arrowRight" :size="16" class="text-[rgb(var(--card-accent))]" />
                        </router-link>
                    </div>
                </div>
                </div>
            </div>

            <!-- Surf. Four layers, painted back to front: a distant swell, a
                 back swell, a foam line and the front band in the page's own
                 colour. Each is longer and slower than the one in front of it,
                 so the set reads as depth rather than as parallel decals. The
                 foam is the front band shifted three pixels up and left
                 underneath it, so it can only ever be exactly on the crest -
                 one shared animation, no phase to keep in sync. -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-20 sm:h-28" aria-hidden="true">
                <div class="wave-band wave-band-swell absolute inset-x-0 bottom-0 h-full" />
                <div class="wave-band wave-band-back absolute inset-x-0 bottom-0 h-full opacity-40" />
                <!-- Foam carries the same modifier as the band it sits on, or
                     the two desync and the highlight leaves the crest. -->
                <div class="wave-band wave-band-lead wave-foam absolute inset-x-0 bottom-[3px] h-3/4" />
                <div class="wave-band wave-band-lead absolute inset-x-0 bottom-0 h-3/4" />
            </div>
        </section>

        <!-- =============================== OFFERS =============================== -->
        <section v-if="promotionsStore.active.length > 0" data-reveal class="r-up shell pt-14">
            <MediaRail
                title="Offers on now"
                hint="Applied automatically at checkout"
                :items="promotionsStore.active"
            >
                <template #card="{ item, index }">
                    <RailCard
                        :to="promoTarget(item)"
                        :title="item.title"
                        :image-url="item.image_url"
                        :index="index"
                        icon="sparkle"
                    >
                        <template #meta>
                            <span class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-white backdrop-blur"
                                >
                                    {{ CATEGORY_LABEL[item.category] ?? item.category }}
                                </span>
                                <span v-if="promoEndsLabel(item)" class="text-xs font-medium text-warning">
                                    {{ promoEndsLabel(item) }}
                                </span>
                            </span>
                        </template>
                        <template v-if="item.description" #more>{{ item.description }}</template>
                    </RailCard>
                </template>
            </MediaRail>
        </section>

        <!-- ================================ PARK ================================ -->
        <section data-reveal class="r-up shell py-14">
            <MediaRail
                title="Popular in the park"
                hint="Rides, shows and beach events — booked by time slot"
                :items="themeParkStore.popularEvents"
                :loading="loadingSections"
                :to="{ name: 'themepark.home' }"
                link-label="See every event"
                empty-text="No events scheduled just yet — check back soon."
            >
                <template #card="{ item, index }">
                    <RailCard
                        :to="{ name: 'themepark.home', query: { event: item.id } }"
                        :title="item.name"
                        :subtitle="item.location"
                        :image-url="item.image_url"
                        :index="index"
                        icon="sparkle"
                    >
                        <template v-if="item.description" #more>{{ item.description }}</template>
                    </RailCard>
                </template>
            </MediaRail>
        </section>

        <!-- =============================== HOTELS ===============================
             The one raised band on the page. Its edges are water rather than
             borders: surface colour washes up over the section above it, and the
             page colour washes back over its foot. -->
        <section class="relative bg-surface">
            <div class="wave-band wave-band-surface absolute inset-x-0 -top-12 h-12" aria-hidden="true" />

            <div data-reveal class="r-up shell py-14">
                <MediaRail
                    title="Places to stay"
                    hint="Every crossing is booked against your stay, so this comes first"
                    :items="hotelStore.popularHotels"
                    :loading="loadingSections"
                    :to="{ name: 'hotels.index' }"
                    link-label="See every hotel"
                    empty-text="No hotels listed just yet — check back soon."
                >
                    <template #card="{ item, index }">
                        <RailCard
                            :to="{ name: 'hotels.index', query: { hotel: item.id } }"
                            :title="item.name"
                            :subtitle="item.address"
                            :image-url="item.image_url"
                            :index="index"
                            icon="hotel"
                        >
                            <template #meta>
                                <span
                                    v-if="item.total_rooms"
                                    class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium text-white backdrop-blur"
                                >
                                    {{ item.total_rooms }} rooms
                                </span>
                            </template>
                            <template v-if="item.description" #more>{{ item.description }}</template>
                        </RailCard>
                    </template>
                </MediaRail>
            </div>

            <div class="wave-band absolute inset-x-0 bottom-0 h-12" aria-hidden="true" />
        </section>

        <!-- ================================= MAP ================================ -->
        <section class="shell pb-16 pt-14">
            <div class="wave-rule mx-auto mb-12 max-w-xl opacity-70" aria-hidden="true" />
            <div data-reveal class="r-grow">
                <IslandMap :events="themeParkStore.events" :locations="mapLocations" />
            </div>
        </section>

        <footer class="relative bg-surface">
            <div class="wave-band wave-band-surface absolute inset-x-0 -top-12 h-12" aria-hidden="true" />
            <p class="shell pb-10 pt-8 text-center text-sm text-muted">
                &copy; {{ new Date().getFullYear() }} ESS. All rights reserved.
            </p>
        </footer>
    </div>
</template>
