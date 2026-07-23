<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useHotelStore } from '@/stores/hotel';

const router = useRouter();
const hotelStore = useHotelStore();

const open = ref(false);
const selected = ref([]);

const pendingBookings = computed(() => hotelStore.myBookings.filter((b) => b.status === 'pending'));

const toggle = () => {
    open.value = !open.value;
    if (open.value) selected.value = pendingBookings.value.map((b) => b.id);
};

const selectedTotal = computed(() =>
    pendingBookings.value
        .filter((b) => selected.value.includes(b.id))
        .reduce((sum, b) => sum + Number(b.total_price), 0)
        .toFixed(2)
);

const payForSelected = () => {
    if (selected.value.length === 0) return;
    open.value = false;
    router.push({ name: 'bookings.confirm', query: { ids: selected.value.join(',') } });
};

// Deliberately not closing on clicks inside the panel (unlike Dropdown.vue) -
// this menu needs checkboxes to stay open while the visitor picks what to pay.
const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') open.value = false;
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div v-if="pendingBookings.length" class="relative">
        <button
            type="button"
            @click="toggle"
            class="rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800 hover:bg-yellow-200"
        >
            {{ pendingBookings.length }} payment{{ pendingBookings.length === 1 ? '' : 's' }} due
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <div v-if="open" class="absolute end-0 z-50 mt-2 w-80 rounded-md bg-white p-4 shadow-lg ring-1 ring-black ring-opacity-5">
            <h4 class="text-sm font-semibold text-gray-900">Payments due</h4>
            <div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                <label
                    v-for="booking in pendingBookings"
                    :key="booking.id"
                    class="flex items-center justify-between gap-2 rounded-md bg-gray-50 p-2 text-sm"
                >
                    <span class="flex items-center gap-2">
                        <input type="checkbox" :value="booking.id" v-model="selected" />
                        <span>
                            {{ booking.room?.hotel?.name }}
                            <span class="block text-xs text-gray-500">
                                {{ booking.check_in_date?.slice(0, 10) }} - {{ booking.reference_code }}
                            </span>
                        </span>
                    </span>
                    <span class="font-medium text-gray-900">${{ booking.total_price }}</span>
                </label>
            </div>

            <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
                <span class="text-sm font-semibold text-gray-900">Total: ${{ selectedTotal }}</span>
                <button
                    type="button"
                    :disabled="selected.length === 0"
                    @click="payForSelected"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    Pay Selected
                </button>
            </div>

            <router-link
                :to="{ name: 'bookings.my' }"
                @click="open = false"
                class="mt-2 block text-center text-xs text-indigo-600 hover:underline"
            >
                View all bookings
            </router-link>
        </div>
    </div>
</template>
