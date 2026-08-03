<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useHotelStore } from '@/stores/hotel';
import TButton from '@/Components/ui/TButton.vue';
import TCheckbox from '@/Components/ui/TCheckbox.vue';
import TDropdown from '@/Components/ui/TDropdown.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { formatDate, formatMoney } from '@/utils/format';

const router = useRouter();
const hotelStore = useHotelStore();
const dropdownRef = ref(null);
const selected = ref([]);

// Hotel-only by design: ferry cash fares are settled with the operator on
// board, and park bookings are already paid at checkout. Labelled explicitly
// so the count doesn't read as everything the visitor owes.
const pendingBookings = computed(() => hotelStore.myBookings.filter((b) => b.status === 'pending'));

// Everything starts ticked - paying the lot is the common case - and stale
// ids are pruned when a booking is paid or cancelled elsewhere, so Pay can
// never submit one that no longer exists.
watch(
    pendingBookings,
    (rows) => {
        const ids = rows.map((b) => b.id);
        const kept = selected.value.filter((id) => ids.includes(id));
        selected.value = kept.length ? kept : ids;
    },
    { immediate: true }
);

const selectedTotal = computed(() =>
    pendingBookings.value
        .filter((b) => selected.value.includes(b.id))
        .reduce((sum, b) => sum + Number(b.total_price), 0)
);

const allSelected = computed(() =>
    pendingBookings.value.length > 0 && selected.value.length === pendingBookings.value.length
);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : pendingBookings.value.map((b) => b.id);
};

const toggleOne = (id, checked) => {
    selected.value = checked ? [...selected.value, id] : selected.value.filter((x) => x !== id);
};

const payForSelected = () => {
    if (selected.value.length === 0) return;
    dropdownRef.value?.close();
    router.push({ name: 'bookings.confirm', query: { ids: selected.value.join(',') } });
};
</script>

<template>
    <TDropdown
        v-if="pendingBookings.length"
        ref="dropdownRef"
        align="right"
        width="80"
        :close-on-content-click="false"
    >
        <template #trigger>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-warning-soft px-3 py-1.5 text-xs font-medium text-warning transition-opacity hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-warning/40"
                aria-haspopup="true"
            >
                <TIcon name="alert" :size="14" />
                {{ pendingBookings.length }} hotel payment{{ pendingBookings.length === 1 ? '' : 's' }} due
            </button>
        </template>

        <template #content>
            <div class="p-4">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-foreground">Hotel payments due</h4>
                    <button
                        v-if="pendingBookings.length > 1"
                        type="button"
                        class="text-xs font-medium text-primary hover:underline"
                        @click="toggleAll"
                    >
                        {{ allSelected ? 'Clear' : 'Select all' }}
                    </button>
                </div>

                <p class="mt-1 text-xs text-foreground-muted">
                    Rooms stay reserved only until payment clears.
                </p>

                <!-- TCheckbox renders its own <label>, so the row is a div -
                     nesting labels is invalid and makes the click target
                     ambiguous. Its label/description props give the two-line
                     layout with the association handled properly. -->
                <div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                    <div
                        v-for="booking in pendingBookings"
                        :key="booking.id"
                        class="flex items-center justify-between gap-2 rounded-lg bg-surface-hover p-2.5 text-sm"
                    >
                        <TCheckbox
                            class="min-w-0"
                            :model-value="selected.includes(booking.id)"
                            :label="booking.room?.hotel?.name"
                            :description="`${formatDate(booking.check_in_date, { weekday: false })} · ${booking.reference_code}`"
                            @update:model-value="(v) => toggleOne(booking.id, v)"
                        />
                        <span class="shrink-0 font-medium text-foreground">{{ formatMoney(booking.total_price) }}</span>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2 border-t pt-3">
                    <span class="text-sm font-semibold text-foreground">{{ formatMoney(selectedTotal) }}</span>
                    <TButton size="sm" :disabled="selected.length === 0" @click="payForSelected">
                        Pay {{ selected.length }}
                    </TButton>
                </div>

                <router-link
                    :to="{ name: 'trips', query: { tab: 'hotel' } }"
                    class="mt-2 block text-center text-xs text-primary hover:underline"
                    @click="dropdownRef?.close()"
                >
                    View all stays
                </router-link>
            </div>
        </template>
    </TDropdown>
</template>
