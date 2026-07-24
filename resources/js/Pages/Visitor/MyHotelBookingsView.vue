<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import QRCode from 'qrcode';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHotelStore } from '@/stores/hotel';

const router = useRouter();
const hotelStore = useHotelStore();
const selected = ref([]);
const qrCodes = reactive({});
const revealedIds = reactive(new Set());

onMounted(() => hotelStore.fetchMyBookings());

// Lets a ferry operator scan this booking at the gate to see the whole
// party's ferry status - not rendered until asked for, same as ferry
// tickets' QR codes, since most bookings are never actually scanned.
const revealQr = async (booking) => {
    if (!qrCodes[booking.id]) {
        qrCodes[booking.id] = await QRCode.toDataURL(booking.reference_code);
    }
    revealedIds.add(booking.id);
};

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

                            <div v-if="booking.status === 'confirmed'">
                                <div v-if="revealedIds.has(booking.id) && qrCodes[booking.id]" class="flex flex-col items-center gap-1">
                                    <img :src="qrCodes[booking.id]" alt="Booking QR code" class="h-14 w-14" />
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    @click="revealQr(booking)"
                                    class="flex h-14 w-14 flex-col items-center justify-center gap-0.5 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400 hover:border-indigo-400 hover:text-indigo-500"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                                        <rect x="3" y="3" width="7" height="7" rx="1" />
                                        <rect x="14" y="3" width="7" height="7" rx="1" />
                                        <rect x="3" y="14" width="7" height="7" rx="1" />
                                        <path stroke-linecap="round" d="M14 14h3m4 0h.01M14 18h.01M18 18h3M14 21h7" />
                                    </svg>
                                    <span class="text-[10px] font-medium">QR</span>
                                </button>
                            </div>

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
