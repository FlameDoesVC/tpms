<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useHotelStore } from '@/stores/hotel';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const auth = useAuthStore();

const hotelId = route.params.id;
const checkIn = ref(route.query.check_in || '');
const checkOut = ref(route.query.check_out || '');
const guests = ref(Number(route.query.guests) || 1);
const bookingError = ref('');

const load = () => {
    hotelStore.fetchHotel(hotelId);
    hotelStore.fetchRooms(hotelId, { checkIn: checkIn.value, checkOut: checkOut.value });
};

onMounted(load);
watch([checkIn, checkOut], () => {
    if (checkIn.value && checkOut.value) {
        hotelStore.fetchRooms(hotelId, { checkIn: checkIn.value, checkOut: checkOut.value });
    }
});

const bookRoom = async (room) => {
    bookingError.value = '';
    if (!checkIn.value || !checkOut.value) {
        bookingError.value = 'Choose check-in and check-out dates first.';
        return;
    }

    try {
        const booking = await hotelStore.createBooking({
            room_id: room.id,
            check_in_date: checkIn.value,
            check_out_date: checkOut.value,
            guests_count: guests.value,
        });
        // Guest checkout logs a placeholder account in server-side; refresh
        // the client's auth state so the navbar and role guards see it too.
        if (!auth.isAuthenticated) await auth.fetchUser();
        router.push({ name: 'bookings.confirm', params: { id: booking.id } });
    } catch (e) {
        bookingError.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ')
            || 'Could not create booking.';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ hotelStore.hotel?.name ?? 'Hotel' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="hotelStore.loading.hotel" class="text-gray-500">Loading hotel...</div>
                <div v-else-if="hotelStore.error.hotel" class="text-red-600">{{ hotelStore.error.hotel }}</div>

                <div v-else-if="hotelStore.hotel" class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-gray-700">{{ hotelStore.hotel.description }}</p>
                    <p class="mt-2 text-sm text-gray-500">{{ hotelStore.hotel.address }}</p>
                </div>

                <div class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-sm">
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

                <p v-if="bookingError" class="text-sm text-red-600">{{ bookingError }}</p>

                <div v-if="hotelStore.loading.rooms" class="text-gray-500">Loading rooms...</div>
                <div v-else-if="hotelStore.rooms.length === 0" class="text-gray-500">
                    No rooms available for the selected dates.
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        v-for="room in hotelStore.rooms"
                        :key="room.id"
                        class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm"
                    >
                        <div>
                            <p class="font-semibold capitalize text-gray-900">
                                {{ room.type }} - Room {{ room.room_number }}
                            </p>
                            <p class="text-sm text-gray-500">Up to {{ room.max_guests }} guests</p>
                            <p class="mt-1 font-medium text-gray-900">${{ room.price_per_night }} / night</p>
                        </div>
                        <PrimaryButton
                            :disabled="hotelStore.loading.creatingBooking"
                            @click="bookRoom(room)"
                        >
                            Book Now
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
