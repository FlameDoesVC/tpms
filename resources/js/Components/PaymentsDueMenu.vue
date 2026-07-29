<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useHotelStore } from '@/stores/hotel';
import TButton from '@/Components/ui/TButton.vue';

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
            class="rounded bg-warning-soft px-3 py-1 text-xs font-medium text-warning hover:opacity-80"
        >
            {{ pendingBookings.length }} payment{{ pendingBookings.length === 1 ? '' : 's' }} due
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <div v-if="open" class="absolute end-0 z-50 mt-2 w-80 rounded-xl border bg-surface p-4 shadow-lg">
            <h4 class="text-sm font-semibold text-foreground">Payments due</h4>
            <div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                <label
                    v-for="booking in pendingBookings"
                    :key="booking.id"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-lg bg-surface-hover p-2.5 text-sm"
                >
                    <span class="flex items-center gap-2">
                        <input type="checkbox" :value="booking.id" v-model="selected" class="rounded text-primary focus:ring-primary/20" />
                        <span>
                            {{ booking.room?.hotel?.name }}
                            <span class="block text-xs text-foreground-muted">
                                {{ booking.check_in_date?.slice(0, 10) }} - {{ booking.reference_code }}
                            </span>
                        </span>
                    </span>
                    <span class="font-medium text-foreground">${{ booking.total_price }}</span>
                </label>
            </div>

            <div class="mt-3 flex items-center justify-between border-t pt-3">
                <span class="text-sm font-semibold text-foreground">Total: ${{ selectedTotal }}</span>
                <TButton
                    size="sm"
                    :disabled="selected.length === 0"
                    @click="payForSelected"
                >
                    Pay Selected
                </TButton>
            </div>

            <router-link
                :to="{ name: 'bookings.my' }"
                @click="open = false"
                class="mt-2 block text-center text-xs text-primary hover:underline"
            >
                View all bookings
            </router-link>
        </div>
    </div>
</template>
