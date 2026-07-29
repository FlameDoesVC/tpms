<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IslandMap from '@/Components/IslandMap.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useThemeParkStore } from '@/stores/themepark';
import { useTheme } from '@/composables/useTheme';

const auth = useAuthStore();
const hotelStore = useHotelStore();
const themeParkStore = useThemeParkStore();
const { isDark, toggle: toggleTheme } = useTheme();

const slides = [
    {
        title: 'Plan your island visit',
        subtitle: 'Book hotels, ferry transfers, and theme park events in one place. No account required to get started.',
        cta: 'Browse Hotels',
        to: { name: 'hotels.index' },
    },
    {
        title: 'Get there by ferry',
        subtitle: 'Reserve a seat on inter-island departures once you have a confirmed hotel stay.',
        cta: 'View Ferry Schedules',
        to: { name: 'ferry.book' },
    },
    {
        title: 'Rides, shows, and beach events',
        subtitle: 'Book theme park time slots for your visit, from thrill rides to sunset shows.',
        cta: 'Explore Theme Park',
        to: { name: 'themepark.home' },
    },
];

const activeSlide = ref(0);
let interval = null;

const goToSlide = (index) => (activeSlide.value = index);

onMounted(() => {
    interval = setInterval(() => {
        activeSlide.value = (activeSlide.value + 1) % slides.length;
    }, 6000);

    if (!auth.loaded) auth.fetchUser();
    hotelStore.fetchPopularHotels();
    themeParkStore.fetchPopularEvents();
    themeParkStore.fetchEvents();
});
onUnmounted(() => clearInterval(interval));
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Top bar -->
        <div class="flex items-center justify-between px-6 py-4">
            <ApplicationLogo class="text-2xl" />
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="toggleTheme"
                    class="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                >
                    <svg v-if="isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>
                <router-link
                    v-if="auth.isAuthenticated"
                    :to="{ name: 'dashboard' }"
                    class="text-sm font-medium text-foreground-secondary hover:text-foreground"
                >
                    Go to Dashboard
                </router-link>
                <router-link
                    v-else
                    :to="{ name: 'login' }"
                    class="text-sm font-medium text-foreground-secondary hover:text-foreground"
                >
                    Log in
                </router-link>
            </div>
        </div>

        <!-- Hero carousel -->
        <!-- Deep-water gradient: near-black at the top corner falling to teal,
             with a warm sun bloom off to one side. Fixed in both themes — it
             reads as the sea, not as a surface. -->
        <div class="tide-line relative overflow-hidden bg-[#061f22] bg-gradient-to-br from-[#04181b] via-[#0a3f42] to-[#10635f]">
            <div
                class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="flex transition-transform duration-700 ease-in-out"
                :style="{ transform: `translateX(-${activeSlide * 100}%)` }"
            >
                <div
                    v-for="slide in slides"
                    :key="slide.title"
                    class="w-full shrink-0 px-6 py-24 text-center"
                >
                    <h1 class="mx-auto max-w-2xl text-4xl font-bold tracking-tight text-white">
                        {{ slide.title }}
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-white/70">
                        {{ slide.subtitle }}
                    </p>
                    <router-link :to="slide.to">
                        <TButton variant="accent" size="lg" as="span" class="mt-8">
                            {{ slide.cta }}
                        </TButton>
                    </router-link>
                </div>
            </div>

            <div class="absolute inset-x-0 bottom-6 flex justify-center gap-2">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.title"
                    @click="goToSlide(index)"
                    class="h-2 w-2 rounded-full transition-all duration-300"
                    :class="activeSlide === index ? 'w-6 bg-white' : 'bg-white/40'"
                    :aria-label="`Go to slide ${index + 1}`"
                />
            </div>
        </div>

        <!-- Popular theme park events -->
        <div class="mx-auto max-w-5xl px-6 py-12">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-foreground">Popular Theme Park Events</h2>
                <router-link :to="{ name: 'themepark.home' }" class="text-sm font-medium text-primary hover:underline">
                    View all &rarr;
                </router-link>
            </div>

            <div v-if="themeParkStore.popularEvents.length === 0" class="text-sm text-foreground-muted">
                No events yet.
            </div>
            <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <router-link
                    v-for="event in themeParkStore.popularEvents"
                    :key="event.id"
                    :to="{ name: 'themepark.home', query: { event: event.id } }"
                    class="group block overflow-hidden rounded-xl border bg-surface transition-all hover:shadow-md"
                >
                    <div class="flex h-32 items-center justify-center bg-surface-hover text-foreground-muted">
                        <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                        <span v-else class="text-sm">No image</span>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-foreground group-hover:text-primary">{{ event.name }}</h3>
                        <p class="mt-1 text-xs text-foreground-muted">{{ event.location }}</p>
                    </div>
                </router-link>
            </div>
        </div>

        <!-- Popular hotels -->
        <div class="border-t bg-surface">
            <div class="mx-auto max-w-5xl px-6 py-12">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-foreground">Popular Hotels</h2>
                    <router-link :to="{ name: 'hotels.index' }" class="text-sm font-medium text-primary hover:underline">
                        View all &rarr;
                    </router-link>
                </div>

                <div v-if="hotelStore.popularHotels.length === 0" class="text-sm text-foreground-muted">
                    No hotels yet.
                </div>
                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <router-link
                        v-for="hotel in hotelStore.popularHotels"
                        :key="hotel.id"
                        :to="{ name: 'hotels.index', query: { hotel: hotel.id } }"
                        class="group block overflow-hidden rounded-xl border bg-surface transition-all hover:shadow-md"
                    >
                        <div class="flex h-32 items-center justify-center bg-surface-hover text-foreground-muted">
                            <img v-if="hotel.image_url" :src="hotel.image_url" :alt="hotel.name" class="h-full w-full object-cover" />
                            <span v-else class="text-sm">No image</span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-foreground group-hover:text-primary">{{ hotel.name }}</h3>
                            <p class="mt-1 text-xs text-foreground-muted">{{ hotel.address }}</p>
                        </div>
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Island map -->
        <div class="mx-auto max-w-5xl border-t px-6 py-12">
            <IslandMap :events="themeParkStore.events" />
        </div>
    </div>
</template>
