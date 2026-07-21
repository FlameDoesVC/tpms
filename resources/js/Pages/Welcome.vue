<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const hotelStore = useHotelStore();
const themeParkStore = useThemeParkStore();

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
});
onUnmounted(() => clearInterval(interval));
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Minimal top bar -->
        <div class="flex items-center justify-between px-6 py-4">
            <ApplicationLogo class="h-8 w-8 fill-current text-gray-700" />
            <router-link
                v-if="auth.isAuthenticated"
                :to="{ name: 'dashboard' }"
                class="text-sm font-medium text-gray-700 hover:text-gray-900"
            >
                Go to Dashboard
            </router-link>
            <router-link
                v-else
                :to="{ name: 'login' }"
                class="text-sm font-medium text-gray-700 hover:text-gray-900"
            >
                Log in
            </router-link>
        </div>

        <!-- Hero carousel -->
        <div class="relative overflow-hidden bg-gray-900">
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
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-300">
                        {{ slide.subtitle }}
                    </p>
                    <router-link
                        :to="slide.to"
                        class="mt-8 inline-block rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        {{ slide.cta }}
                    </router-link>
                </div>
            </div>

            <!-- Dots -->
            <div class="absolute inset-x-0 bottom-6 flex justify-center gap-2">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.title"
                    @click="goToSlide(index)"
                    class="h-2 w-2 rounded-full transition"
                    :class="activeSlide === index ? 'bg-white' : 'bg-white/40'"
                    :aria-label="`Go to slide ${index + 1}`"
                />
            </div>
        </div>

        <!-- Popular hotels -->
        <div class="mx-auto max-w-5xl px-6 py-12">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Popular Hotels</h2>
                <router-link :to="{ name: 'hotels.index' }" class="text-sm font-medium text-indigo-600 hover:underline">
                    View all &rarr;
                </router-link>
            </div>

            <div v-if="hotelStore.popularHotels.length === 0" class="text-sm text-gray-500">
                No hotels yet.
            </div>
            <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <router-link
                    v-for="hotel in hotelStore.popularHotels"
                    :key="hotel.id"
                    :to="{ name: 'hotels.show', params: { id: hotel.id } }"
                    class="block overflow-hidden rounded-lg bg-white shadow transition hover:shadow-md"
                >
                    <div class="flex h-32 items-center justify-center bg-gray-100 text-gray-400">
                        <img v-if="hotel.image_url" :src="hotel.image_url" :alt="hotel.name" class="h-full w-full object-cover" />
                        <span v-else>No image</span>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900">{{ hotel.name }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ hotel.address }}</p>
                        <p class="mt-2 text-xs font-medium text-indigo-600">
                            {{ hotel.bookings_count }} booking{{ hotel.bookings_count === 1 ? '' : 's' }}
                        </p>
                    </div>
                </router-link>
            </div>
        </div>

        <!-- Popular theme park events -->
        <div class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-6 py-12">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Popular Theme Park Events</h2>
                    <router-link :to="{ name: 'themepark.home' }" class="text-sm font-medium text-indigo-600 hover:underline">
                        View all &rarr;
                    </router-link>
                </div>

                <div v-if="themeParkStore.popularEvents.length === 0" class="text-sm text-gray-500">
                    No events yet.
                </div>
                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <router-link
                        v-for="event in themeParkStore.popularEvents"
                        :key="event.id"
                        :to="{ name: 'themepark.event', params: { id: event.id } }"
                        class="block overflow-hidden rounded-lg bg-gray-50 shadow transition hover:shadow-md"
                    >
                        <div class="flex h-32 items-center justify-center bg-gray-100 text-gray-400">
                            <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                            <span v-else>No image</span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900">{{ event.name }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ event.location }}</p>
                            <p class="mt-2 text-xs font-medium text-indigo-600">
                                {{ event.bookings_count }} booking{{ event.bookings_count === 1 ? '' : 's' }}
                            </p>
                        </div>
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>
