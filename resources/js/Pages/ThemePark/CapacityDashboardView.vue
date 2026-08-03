<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TStat from '@/Components/ui/TStat.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { formatDate } from '@/utils/format';

const themeParkStore = useThemeParkStore();
// Only the first load shows a skeleton. The minute-by-minute refresh must not
// blank the board someone is watching.
const loadingFirst = ref(true);
let interval = null;

onMounted(async () => {
    try {
        await themeParkStore.fetchCapacity();
    } finally {
        loadingFirst.value = false;
    }
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
    return Object.values(groups)
        .map((g) => ({
            ...g,
            fillPercent: g.capacity > 0 ? Math.round((g.booked / g.capacity) * 100) : 0,
        }))
        // Fullest first: this board exists to show what is about to run out.
        .sort((a, b) => b.fillPercent - a.fillPercent);
});

const totals = computed(() =>
    byEvent.value.reduce(
        (acc, e) => ({
            capacity: acc.capacity + e.capacity,
            booked: acc.booked + e.booked,
            available: acc.available + e.available,
        }),
        { capacity: 0, booked: 0, available: 0 }
    )
);

const totalFill = computed(() =>
    totals.value.capacity > 0 ? Math.round((totals.value.booked / totals.value.capacity) * 100) : 0
);

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Capacity Monitoring" icon="capacity">
                <template #actions>
                    <span class="flex items-center gap-1.5 text-sm text-foreground-muted">
                        <TIcon name="calendar" :size="15" />
                        {{ formatDate(today) }}
                    </span>
                </template>
            </TPageHeader>
        </template>

        <div class="space-y-5">
            <div v-if="loadingFirst" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="n in 4" :key="n" class="h-28 animate-pulse rounded-xl border bg-surface-hover" />
            </div>

            <TEmptyState
                v-else-if="byEvent.length === 0"
                title="No slots scheduled today"
                description="Once today's slots are on the calendar, their fill rate appears here."
                icon="calendar"
            />

            <template v-else>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <TStat label="Slots today" :value="byEvent.reduce((n, e) => n + e.slots, 0)" icon="calendar" tone="info" />
                    <TStat label="Tickets sold" :value="totals.booked" icon="ticket" tone="primary" />
                    <TStat label="Places left" :value="totals.available" icon="users" tone="success" />
                    <TStat
                        label="Park fill"
                        :value="`${totalFill}%`"
                        :change="`${totals.booked} of ${totals.capacity}`"
                        icon="capacity"
                        :tone="totalFill > 90 ? 'danger' : 'primary'"
                        :progress="totalFill / 100"
                    />
                </div>

                <!-- The refresh is silent and easy to mistake for a stale page,
                     so the board says it is keeping itself current. -->
                <p class="flex items-center gap-1.5 text-xs text-foreground-muted">
                    <TIcon name="clock" :size="13" />
                    Updates every minute
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    <div v-for="event in byEvent" :key="event.name" class="elevated rounded-xl border bg-surface p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-foreground">{{ event.name }}</p>
                                <p class="text-sm text-foreground-muted">
                                    {{ event.slots }} slot{{ event.slots === 1 ? '' : 's' }} today
                                </p>
                            </div>
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded"
                                :class="event.fillPercent > 90 ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary'"
                            >
                                <TIcon name="capacity" :size="17" />
                            </span>
                        </div>

                        <p
                            class="mt-3 text-2xl font-semibold tracking-tight [font-variant-numeric:tabular-nums]"
                            :class="event.fillPercent > 90 ? 'text-danger' : 'text-foreground'"
                        >
                            {{ event.fillPercent }}%
                        </p>

                        <div class="mt-2 h-2 w-full overflow-hidden rounded-sm bg-surface-hover">
                            <div
                                class="h-full rounded-sm transition-[width] duration-500"
                                :class="event.fillPercent > 90 ? 'bg-danger' : 'bg-primary'"
                                :style="{ width: event.fillPercent + '%' }"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-foreground-muted">
                            {{ event.booked }} sold · {{ event.available }} left of {{ event.capacity }}
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </StaffLayout>
</template>
