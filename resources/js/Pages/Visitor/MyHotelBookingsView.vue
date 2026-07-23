<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHotelStore } from '@/stores/hotel';

const router = useRouter();
const hotelStore = useHotelStore();
const selected = ref([]);

onMounted(() => hotelStore.fetchMyBookings());

// Cancelled bookings sink to the bottom rather than cluttering the top of the list.
const sortedBookings = computed(() =>
    [...hotelStore.myBookings].sort((a, b) => (a.status === 'cancelled') - (b.status === 'cancelled'))
);

const today = new Date().toISOString().slice(0, 10);
const isUpcoming = (booking) => (booking.check_out_date ?? '').slice(0, 10) >= today;
const isCancellable = (booking) => booking.status !== 'cancelled' && isUpcoming(booking);

const payableSelected = computed(() =>
    selected.value.filter((id) => hotelStore.myBookings.find((b) => b.id === id)?.status === 'pending')
);
const cancellableSelected = computed(() =>
    selected.value.filter((id) => isCancellable(hotelStore.myBookings.find((b) => b.id === id) ?? {}))
);
const selectedTotal = computed(() =>
    hotelStore.myBookings
        .filter((b) => payableSelected.value.includes(b.id))
        .reduce((sum, b) => sum + Number(b.total_price), 0)
        .toFixed(2)
);

const cancel = (booking) => {
    if (confirm('Cancel this booking?')) {
        hotelStore.cancelBooking(booking.id);
    }
};

const paySelected = () => {
    if (payableSelected.value.length === 0) return;
    router.push({ name: 'bookings.confirm', query: { ids: payableSelected.value.join(',') } });
};

const cancelSelected = async () => {
    if (cancellableSelected.value.length === 0) return;
    if (!confirm(`Cancel ${cancellableSelected.value.length} booking(s)?`)) return;

    const ids = cancellableSelected.value;
    await Promise.all(ids.map((id) => hotelStore.cancelBooking(id)));
    selected.value = selected.value.filter((id) => !ids.includes(id));
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                My Hotel Bookings
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="hotelStore.loading.bookings" class="text-gray-500">Loading bookings...</div>
                <div v-else-if="hotelStore.myBookings.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500">
                    You have no hotel bookings yet.
                </div>

                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-4 shadow-sm">
                        <span class="text-sm text-gray-500">
                            {{ selected.length }} selected
                            <span v-if="payableSelected.length">- ${{ selectedTotal }} due</span>
                        </span>
                        <div class="flex gap-2">
                            <button
                                :disabled="payableSelected.length === 0"
                                @click="paySelected"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                            >
                                Pay Selected ({{ payableSelected.length }})
                            </button>
                            <button
                                :disabled="cancellableSelected.length === 0"
                                @click="cancelSelected"
                                class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                            >
                                Cancel Selected ({{ cancellableSelected.length }})
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="booking in sortedBookings"
                        :key="booking.id"
                        class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm"
                        :class="{ 'opacity-50 grayscale': booking.status === 'cancelled' }"
                    >
                        <div class="flex items-center gap-3">
                            <input v-if="booking.status !== 'cancelled'" type="checkbox" :value="booking.id" v-model="selected" />
                            <div>
                                <p class="font-semibold text-gray-900">{{ booking.room?.hotel?.name }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}
                                    - ${{ booking.total_price }} - {{ booking.reference_code }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-yellow-100 text-yellow-800': booking.status === 'pending',
                                    'bg-green-100 text-green-800': booking.status === 'confirmed',
                                    'bg-gray-100 text-gray-600': booking.status === 'cancelled',
                                }"
                            >
                                {{ booking.status }}
                            </span>
                            <router-link
                                v-if="booking.status === 'pending'"
                                :to="{ name: 'bookings.confirm', query: { ids: String(booking.id) } }"
                                class="text-sm text-indigo-600 hover:underline"
                            >
                                Complete Payment
                            </router-link>
                            <button
                                v-if="isCancellable(booking)"
                                @click="cancel(booking)"
                                class="text-sm text-red-600 hover:underline"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
