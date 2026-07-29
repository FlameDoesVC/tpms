<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Capacity Monitoring" icon="capacity">
                <template #actions>
                    <span class="flex items-center gap-1.5 text-sm text-foreground-muted">
                        <TIcon name="calendar" :size="15" />
                        {{ new Date().toLocaleDateString() }}
                    </span>
                </template>
            </TPageHeader>
        </template>

        <div class="max-w-5xl">
            <TEmptyState v-if="byEvent.length === 0" title="No slots scheduled today" icon="calendar" />

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="event in byEvent" :key="event.name" class="elevated rounded-xl border bg-surface p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-foreground">{{ event.name }}</p>
                            <p class="text-sm text-foreground-muted">{{ event.slots }} slot(s) today</p>
                        </div>
                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center rounded"
                            :class="event.fillPercent > 90 ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary'"
                        >
                            <TIcon name="capacity" :size="17" />
                        </span>
                    </div>

                    <div class="mt-3 h-2 w-full overflow-hidden rounded-sm bg-surface-hover">
                        <div
                            class="h-full rounded-sm transition-[width] duration-500"
                            :class="event.fillPercent > 90 ? 'bg-danger' : 'bg-primary'"
                            :style="{ width: event.fillPercent + '%' }"
                        />
                    </div>
                    <p class="mt-1 text-xs text-foreground-muted">
                        {{ event.booked }} booked / {{ event.available }} available
                    </p>
                </div>
            </div>
        </div>
    </StaffLayout>
</template>
