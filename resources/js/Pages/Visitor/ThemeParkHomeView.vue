<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const themeParkStore = useThemeParkStore();
const cart = useCartStore();
const auth = useAuthStore();

const TYPES = [
    { key: 'ride', label: 'Ride' },
    { key: 'show', label: 'Show' },
    { key: 'beach_event', label: 'Beach Event' },
];

const date = ref(new Date().toISOString().slice(0, 10));
const tickets = ref(1);
const selectedEventIds = ref([]);
const selectedTypes = ref([]);

const slotsByEvent = reactive({});
const loadingSlots = reactive({});
const eventMessages = reactive({});

// Empty selection means "show every event" - the filters only narrow things
// down once the visitor actually picks something.
const visibleEvents = computed(() =>
    themeParkStore.events.filter(
        (e) =>
            (selectedEventIds.value.length === 0 || selectedEventIds.value.includes(e.id)) &&
            (selectedTypes.value.length === 0 || selectedTypes.value.includes(e.type))
    )
);

const today = new Date().toISOString().slice(0, 10);
const upcomingBookings = computed(() =>
    themeParkStore.myBookings
        .filter((b) => b.status === 'confirmed' && (b.slot?.slot_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

const selectedSlotIds = reactive({});
const ticketOverrides = reactive({});

const selectedSlotFor = (event) => (slotsByEvent[event.id] ?? []).find((s) => s.id === selectedSlotIds[event.id]) ?? null;

const defaultTicketCount = (slot) => Math.min(slot.available_capacity, Math.max(1, tickets.value));

// Not clamped here - clamping on every keystroke fights the user mid-edit
// (e.g. snapping back to a smaller number as soon as the field is cleared to
// type a new one). Out-of-range values just aren't bookable, per canBook below.
const ticketCountFor = (event) => {
    if (event.id in ticketOverrides) return ticketOverrides[event.id];
    const slot = selectedSlotFor(event);
    return slot ? defaultTicketCount(slot) : tickets.value;
};

const setTicketCount = (event, value) => {
    ticketOverrides[event.id] = value;
};

const selectSlot = (event, slot) => {
    if (slot.available_capacity < 1) return;
    selectedSlotIds[event.id] = slot.id;
};

const loadSlotsFor = async (eventId) => {
    if (!date.value) return;

    loadingSlots[eventId] = true;
    try {
        slotsByEvent[eventId] = await themeParkStore.fetchSlotsForEvent(eventId, date.value);
    } finally {
        loadingSlots[eventId] = false;
    }
};

const loadAllSlots = () => Promise.all(themeParkStore.events.map((e) => loadSlotsFor(e.id)));

watch(date, () => {
    // A previously-picked slot may no longer be in the new date's list.
    Object.keys(selectedSlotIds).forEach((key) => delete selectedSlotIds[key]);
    loadAllSlots();
});

onMounted(async () => {
    await themeParkStore.fetchEvents();
    if (auth.isAuthenticated) themeParkStore.fetchMyBookings({ silent: true });

    if (route.query.event) {
        selectedEventIds.value = [Number(route.query.event)];
    }

    await loadAllSlots();
});

const canBook = (event) => {
    const slot = selectedSlotFor(event);
    if (!slot) return false;
    const count = ticketCountFor(event);
    return count >= 1 && count <= slot.available_capacity;
};

const addToCart = (event) => {
    eventMessages[event.id] = null;

    const slot = selectedSlotFor(event);
    if (!slot) {
        eventMessages[event.id] = { type: 'error', text: 'Pick a time slot first.' };
        return;
    }
    if (!canBook(event)) {
        eventMessages[event.id] = { type: 'error', text: 'Adjust the ticket count to fit the slot capacity.' };
        return;
    }

    const ticketCount = ticketCountFor(event);
    const subtotal = ticketCount * event.price_per_ticket;

    cart.addItem({
        type: 'themepark',
        eventId: event.id,
        eventName: event.name,
        slotId: slot.id,
        slotDate: slot.slot_date?.slice(0, 10),
        slotTime: slot.slot_time,
        ticketCount,
        pricePerTicket: event.price_per_ticket,
        subtotal,
    });

    eventMessages[event.id] = { type: 'success', text: `Added ${ticketCount} ticket(s) to cart.` };
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Theme Park" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="auth.isAuthenticated" class="elevated rounded-xl border bg-surface p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-foreground">My Bookings</h3>
                        <router-link :to="{ name: 'themepark.my-bookings' }" class="text-sm text-primary hover:underline">
                            View all
                        </router-link>
                    </div>

                    <p v-if="upcomingBookings.length === 0" class="mt-2 text-sm text-foreground-muted">
                        No upcoming bookings.
                    </p>
                    <div v-else class="mt-3 space-y-2">
                        <div
                            v-for="booking in upcomingBookings"
                            :key="booking.id"
                            class="flex items-center justify-between rounded-lg bg-surface-hover px-3 py-2 text-sm text-foreground-secondary"
                        >
                            <span>
                                {{ booking.slot?.event?.name }} -
                                {{ booking.slot?.slot_date?.slice(0, 10) }} at {{ booking.slot?.slot_time }}
                                ({{ booking.ticket_count }} ticket(s))
                            </span>
                        </div>
                    </div>
                </div>

                <div class="elevated sticky top-[4.25rem] z-10 flex flex-wrap gap-4 rounded-xl border bg-surface p-4">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-foreground-secondary">Events</label>
                            <button
                                v-if="selectedEventIds.length"
                                type="button"
                                @click="selectedEventIds = []"
                                class="text-xs text-primary hover:underline"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="mt-1 flex max-h-20 w-48 flex-col gap-1 overflow-y-auto rounded-lg border p-2">
                            <label
                                v-for="event in themeParkStore.events"
                                :key="event.id"
                                class="flex items-center gap-2 text-sm text-foreground-secondary"
                            >
                                <input type="checkbox" :value="event.id" v-model="selectedEventIds" />
                                {{ event.name }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-foreground-secondary">Type</label>
                            <button
                                v-if="selectedTypes.length"
                                type="button"
                                @click="selectedTypes = []"
                                class="text-xs text-primary hover:underline"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="mt-1 flex flex-col gap-1 rounded-lg border p-2">
                            <label v-for="t in TYPES" :key="t.key" class="flex items-center gap-2 text-sm text-foreground-secondary">
                                <input type="checkbox" :value="t.key" v-model="selectedTypes" />
                                {{ t.label }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Date</label>
                        <input
                            type="date"
                            v-model="date"
                            class="mt-1 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Tickets</label>
                        <input
                            type="number"
                            min="1"
                            v-model.number="tickets"
                            class="mt-1 w-20 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                </div>

                <div v-if="themeParkStore.loading.events" class="text-foreground-muted">Loading events...</div>
                <TEmptyState
                    v-else-if="themeParkStore.events.length === 0"
                    title="No events available yet"
                    description="Rides, shows and beach events will be listed here once scheduled."
                    icon="calendar"
                />
                <TEmptyState
                    v-else-if="visibleEvents.length === 0"
                    title="No events match the selected filter"
                    description="Try clearing the event or type filters to see everything on offer."
                    icon="search"
                />

                <div v-else class="space-y-8">
                    <div v-for="event in visibleEvents" :key="event.id" class="elevated rounded-xl border bg-surface p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted">
                                <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                                <span v-else>No image</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-foreground">{{ event.name }}</h3>
                                        <TBadge variant="primary" class="shrink-0 capitalize">
                                            {{ event.type.replace('_', ' ') }}
                                        </TBadge>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                                            Tickets
                                            <input
                                                type="number"
                                                min="1"
                                                :value="ticketCountFor(event)"
                                                @input="setTicketCount(event, Number($event.target.value))"
                                                class="w-16 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                                            />
                                        </label>
                                        <TButton :disabled="!canBook(event)" @click="addToCart(event)">
                                            Add to Cart
                                        </TButton>
                                    </div>
                                </div>
                                <p class="mt-1 line-clamp-2 text-sm text-foreground-secondary">{{ event.description }}</p>
                                <p class="mt-1 text-xs text-foreground-muted">
                                    {{ event.location }} - {{ event.duration_minutes }} min - ${{ event.price_per_ticket }} / ticket
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="eventMessages[event.id]"
                            class="mt-4 text-sm"
                            :class="eventMessages[event.id].type === 'error' ? 'text-danger' : 'text-success'"
                        >
                            {{ eventMessages[event.id].text }}
                            <router-link
                                v-if="eventMessages[event.id].type === 'success'"
                                :to="{ name: 'hotels.index' }"
                                class="font-medium underline"
                            >
                                Book a hotel for the stay?
                            </router-link>
                        </p>

                        <div class="mt-4">
                            <div v-if="loadingSlots[event.id]" class="text-sm text-foreground-muted">Loading time slots...</div>
                            <div
                                v-else-if="(slotsByEvent[event.id]?.length ?? 0) === 0"
                                class="rounded-lg bg-surface-hover p-4 text-sm text-foreground-muted"
                            >
                                No time slots available for this date.
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <button
                                    v-for="slot in slotsByEvent[event.id]"
                                    :key="slot.id"
                                    type="button"
                                    :disabled="slot.available_capacity < 1"
                                    @click="selectSlot(event, slot)"
                                    class="rounded border px-4 py-2 text-sm transition-colors"
                                    :class="[
                                        selectedSlotIds[event.id] === slot.id ? 'border-primary bg-primary-soft text-primary' : 'border-strong text-foreground-secondary',
                                        slot.available_capacity < 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-surface-hover',
                                    ]"
                                >
                                    {{ slot.slot_time }} ({{ slot.available_capacity }} left)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
