<script setup>
import { onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();

const checkIn = ref('');
const checkOut = ref('');
const guests = ref(1);

onMounted(() => hotelStore.fetchHotels());
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Hotels
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap gap-4 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Check in</label>
                        <input type="date" v-model="checkIn" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Check out</label>
                        <input type="date" v-model="checkOut" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Guests</label>
                        <input type="number" min="1" v-model.number="guests" class="mt-1 w-20 rounded-md border-gray-300 shadow-sm" />
                    </div>
                </div>

                <div v-if="hotelStore.loading.hotels" class="text-gray-500">Loading hotels...</div>
                <div v-else-if="hotelStore.error.hotels" class="text-red-600">{{ hotelStore.error.hotels }}</div>
                <div v-else-if="hotelStore.hotels.length === 0" class="text-gray-500">No hotels available yet.</div>

                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <router-link
                        v-for="hotel in hotelStore.hotels"
                        :key="hotel.id"
                        :to="{
                            name: 'hotels.show',
                            params: { id: hotel.id },
                            query: { check_in: checkIn, check_out: checkOut, guests },
                        }"
                        class="block overflow-hidden rounded-lg bg-white shadow transition hover:shadow-md"
                    >
                        <div class="flex h-40 items-center justify-center bg-gray-100 text-gray-400">
                            <img
                                v-if="hotel.image_url"
                                :src="hotel.image_url"
                                :alt="hotel.name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>No image</span>
                        </div>
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <h3 class="font-semibold text-gray-900">{{ hotel.name }}</h3>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="hotel.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ hotel.is_active ? 'Open' : 'Closed' }}
                                </span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ hotel.description }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ hotel.address }}</p>
                        </div>
                    </router-link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
