<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
let interval = null;

onMounted(() => {
    themeParkStore.fetchCapacity();
    interval = setInterval(() => themeParkStore.fetchCapacity(), 60000);
});
onUnmounted(() => clearInterval(interval));

const byEvent = computed(() => {
    const groups = {};
    for (const slot of themeParkStore.capacityData) {
        if (!groups[slot.event_name]) {
            groups[slot.event_name] = { name: slot.event_name, slots: 0, capacity: 0, booked: 0, available: 0 };
        }
        const g = groups[slot.event_name];
        g.slots += 1;
        g.capacity += slot.capacity;
        g.booked += slot.booked;
        g.available += slot.available;
    }
    return Object.values(groups).map((g) => ({
        ...g,
        fillPercent: g.capacity > 0 ? Math.round((g.booked / g.capacity) * 100) : 0,
    }));
});
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Capacity Monitoring
                </h2>
                <p class="text-sm text-gray-500">{{ new Date().toLocaleDateString() }}</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div v-if="byEvent.length === 0" class="text-gray-500">No slots scheduled today.</div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="event in byEvent" :key="event.name" class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="font-semibold text-gray-900">{{ event.name }}</p>
                        <p class="text-sm text-gray-500">{{ event.slots }} slot(s) today</p>

                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full"
                                :class="event.fillPercent > 90 ? 'bg-red-500' : 'bg-indigo-500'"
                                :style="{ width: event.fillPercent + '%' }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ event.booked }} booked / {{ event.available }} available
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
