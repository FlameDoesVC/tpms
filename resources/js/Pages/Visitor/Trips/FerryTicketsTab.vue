<script setup>
import { computed, onMounted, ref } from 'vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TicketQr from '@/Components/Trips/TicketQr.vue';
import { useFerryStore } from '@/stores/ferry';
import { formatDate, formatMoney, formatTime, todayIso } from '@/utils/format';
import { statusVariant } from '@/utils/status';

const ferryStore = useFerryStore();
const qrRefs = ref({});

onMounted(() => ferryStore.fetchMyTickets());


// One card per crossing, not per seat. A family of four used to get four
// full-height boarding passes for the same sailing; the dashboard already
// grouped this same data by schedule, so the two views disagreed.
const crossings = computed(() => {
    const bySchedule = new Map();

    for (const ticket of ferryStore.myTickets) {
        const key = ticket.schedule_id ?? ticket.schedule?.id ?? `ticket-${ticket.id}`;
        if (!bySchedule.has(key)) {
            bySchedule.set(key, {
                key,
                schedule: ticket.schedule,
                tickets: [],
            });
        }
        bySchedule.get(key).tickets.push(ticket);
    }

    return [...bySchedule.values()]
        .map((group) => {
            const tickets = [...group.tickets].sort((a, b) => a.seat_number - b.seat_number);
            const date = (group.schedule?.departure_date ?? '').slice(0, 10);
            return {
                ...group,
                tickets,
                date,
                isPast: date !== '' && date < todayIso(),
                total: tickets.reduce((sum, t) => sum + Number(t.price ?? 0), 0),
                cashDue: tickets
                    .filter((t) => t.payment_method === 'cash' && t.status !== 'cancelled')
                    .reduce((sum, t) => sum + Number(t.price ?? 0), 0),
            };
        })
        .sort((a, b) => a.date.localeCompare(b.date));
});

const upcoming = computed(() => crossings.value.filter((c) => !c.isPast));
const past = computed(() => crossings.value.filter((c) => c.isPast));

// A crossing's overall state is the most actionable one across its seats -
// one cancelled seat shouldn't make the whole sailing look cancelled.
const crossingStatus = (crossing) => {
    const statuses = crossing.tickets.map((t) => t.status);
    for (const s of ['pending', 'issued', 'used']) {
        if (statuses.includes(s)) return s;
    }
    return statuses[0] ?? 'cancelled';
};

const downloadAll = (crossing) => {
    for (const ticket of crossing.tickets) {
        qrRefs.value[ticket.id]?.download?.();
    }
};

const setQrRef = (id, el) => {
    if (el) qrRefs.value[id] = el;
    else delete qrRefs.value[id];
};
</script>

<template>
    <div class="space-y-6">
        <p v-if="ferryStore.loading.tickets" class="text-foreground-muted">Loading tickets…</p>

        <div
            v-else-if="ferryStore.error.tickets"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger bg-danger-soft p-4 text-sm text-danger"
        >
            <span>{{ ferryStore.error.tickets }}</span>
            <TButton size="sm" variant="secondary" @click="ferryStore.fetchMyTickets()">Try again</TButton>
        </div>

        <TEmptyState
            v-else-if="ferryStore.myTickets.length === 0"
            title="No ferry tickets yet"
            description="Seats you book for the crossing will appear here as boarding passes."
            icon="ferry"
        >
            <template #action>
                <router-link :to="{ name: 'ferry.book' }">
                    <TButton size="sm">Book a crossing</TButton>
                </router-link>
            </template>
        </TEmptyState>

        <template v-else>
            <section v-for="group in [{ label: 'Upcoming', rows: upcoming }, { label: 'Past', rows: past }]" :key="group.label">
                <template v-if="group.rows.length">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                        {{ group.label }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Boarding-pass form: notched edges and a perforated
                             seam between the stub and the fare details. Kept as
                             bespoke markup because TCard's overflow-hidden
                             would clip the notches. -->
                        <div
                            v-for="crossing in group.rows"
                            :key="crossing.key"
                            class="ticket-edge elevated rounded-xl border bg-surface"
                            :class="{ 'opacity-60': crossing.isPast }"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-foreground">
                                            {{ crossing.schedule?.ferry?.name ?? 'Ferry crossing' }}
                                        </p>
                                        <TBadge :variant="statusVariant(crossingStatus(crossing))" dot>
                                            {{ crossingStatus(crossing) }}
                                        </TBadge>
                                    </div>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-foreground-secondary">
                                        <span class="inline-flex items-center gap-1">
                                            <TIcon name="calendar" :size="13" />
                                            {{ formatDate(crossing.schedule?.departure_date) }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <TIcon name="clock" :size="13" />
                                            {{ formatTime(crossing.schedule?.departure_time) }}
                                            <template v-if="crossing.schedule?.arrival_time">
                                                – {{ formatTime(crossing.schedule.arrival_time) }}
                                            </template>
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <TIcon name="users" :size="13" />
                                            {{ crossing.tickets.length }} seat{{ crossing.tickets.length === 1 ? '' : 's' }}
                                        </span>
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="font-semibold text-foreground">{{ formatMoney(crossing.total) }}</span>
                                    <TButton
                                        v-if="crossing.tickets.length > 1"
                                        size="xs"
                                        variant="secondary"
                                        @click="downloadAll(crossing)"
                                    >
                                        <TIcon name="download" :size="13" />
                                        Save all
                                    </TButton>
                                </div>
                            </div>

                            <p
                                v-if="crossing.cashDue > 0"
                                class="mx-4 mb-3 rounded-lg bg-warning-soft px-3 py-2 text-xs font-medium text-warning"
                            >
                                {{ formatMoney(crossing.cashDue) }} due in cash on board — have it ready before boarding.
                            </p>

                            <span class="perforation" aria-hidden="true" />

                            <!-- Each seat keeps its own QR: the gate validates
                                 individual tickets, not the crossing. -->
                            <ul class="divide-y divide-[rgb(var(--color-border))]">
                                <li
                                    v-for="ticket in crossing.tickets"
                                    :key="ticket.id"
                                    class="flex items-center gap-4 p-4"
                                >
                                    <TicketQr
                                        :ref="(el) => setQrRef(ticket.id, el)"
                                        :code="ticket.reference_code"
                                        size="sm"
                                        downloadable
                                        :show-code="false"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-foreground">Seat {{ ticket.seat_number }}</p>
                                        <p class="font-mono text-xs text-foreground-muted">{{ ticket.reference_code }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        <TBadge :variant="statusVariant(ticket.status)" size="sm" dot>
                                            {{ ticket.status }}
                                        </TBadge>
                                        <span class="text-xs text-foreground-muted">
                                            {{ formatMoney(ticket.price) }} ·
                                            {{ ticket.payment_method === 'cash' ? 'cash on board' : 'paid' }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>
            </section>
        </template>
    </div>
</template>
