<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import HotelStaysTab from '@/Pages/Visitor/Trips/HotelStaysTab.vue';
import FerryTicketsTab from '@/Pages/Visitor/Trips/FerryTicketsTab.vue';
import ParkTicketsTab from '@/Pages/Visitor/Trips/ParkTicketsTab.vue';
import { useHotelStore } from '@/stores/hotel';
import { useFerryStore } from '@/stores/ferry';
import { useThemeParkStore } from '@/stores/themepark';

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const ferryStore = useFerryStore();
const themeParkStore = useThemeParkStore();

const TABS = [
    { key: 'hotel', label: 'Stays', icon: 'hotel', component: HotelStaysTab },
    { key: 'ferry', label: 'Ferry', icon: 'ferry', component: FerryTicketsTab },
    { key: 'park', label: 'Park', icon: 'ticket', component: ParkTicketsTab },
];

// Driven by the query so a tab is linkable and survives a refresh - the
// checkout receipt and the dashboard both deep-link straight to one.
const activeKey = computed(() => {
    const requested = String(route.query.tab ?? '');
    return TABS.some((t) => t.key === requested) ? requested : 'hotel';
});

const activeTab = computed(() => TABS.find((t) => t.key === activeKey.value) ?? TABS[0]);

const selectTab = (key) => {
    if (key === activeKey.value) return;
    router.replace({ name: 'trips', query: { ...route.query, tab: key } });
};

// Only counts things still worth acting on, so the badge doesn't advertise a
// pile of cancelled rows.
const counts = computed(() => ({
    hotel: hotelStore.myBookings.filter((b) => b.status !== 'cancelled').length,
    ferry: ferryStore.myTickets.filter((t) => t.status !== 'cancelled').length,
    park: themeParkStore.myBookings.filter((b) => b.status !== 'cancelled').length,
}));

// An unknown ?tab= is normalised away rather than left in the URL pointing at
// something that isn't rendered.
watch(
    () => route.query.tab,
    (tab) => {
        if (tab && !TABS.some((t) => t.key === tab)) {
            router.replace({ name: 'trips', query: { ...route.query, tab: 'hotel' } });
        }
    },
    { immediate: true }
);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="My Trips" subtitle="Stays, crossings and park tickets in one place." icon="ticket" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex gap-1 overflow-x-auto rounded-xl border bg-surface p-1" role="tablist">
                    <button
                        v-for="tab in TABS"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeKey === tab.key"
                        class="inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        :class="activeKey === tab.key
                            ? 'bg-primary-soft text-primary'
                            : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                        @click="selectTab(tab.key)"
                    >
                        <TIcon :name="tab.icon" :size="16" />
                        {{ tab.label }}
                        <span
                            v-if="counts[tab.key]"
                            class="rounded-full bg-surface-hover px-1.5 text-xs font-semibold text-foreground-secondary"
                        >
                            {{ counts[tab.key] }}
                        </span>
                    </button>
                </div>

                <!-- Kept alive so switching tabs doesn't refetch, and a
                     revealed QR or a selection isn't thrown away mid-task. -->
                <KeepAlive>
                    <component :is="activeTab.component" :key="activeTab.key" />
                </KeepAlive>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
