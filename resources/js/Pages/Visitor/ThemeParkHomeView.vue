<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TMultiSelect from '@/Components/ui/TMultiSelect.vue';
import TIcon from '@/Components/ui/TIcon.vue';
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
const typeOptions = TYPES.map((t) => ({ value: t.key, label: t.label }));

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
const expandedEvents = reactive({});

const toggleExpanded = (eventId) => { expandedEvents[eventId] = !expandedEvents[eventId]; };

const selectedSlotFor = (event) => (slotsByEvent[event.id] ?? []).find((s) => s.id === selectedSlotIds[event.id]) ?? null;

// Summarizes the booking block while it's collapsed, so collapsing doesn't
// hide whether there's anything worth expanding for.
const slotsTeaser = (event) => {
    if (loadingSlots[event.id]) return 'Loading times…';
    const slots = slotsByEvent[event.id] ?? [];
    if (slots.length === 0) return 'No time slots for this date';
    const selected = selectedSlotFor(event);
    if (selected) return `${selected.slot_time} selected`;
    const available = slots.filter((s) => s.available_capacity > 0);
    if (available.length === 0) return 'Fully booked for this date';
    return `${available.length} time${available.length === 1 ? '' : 's'} available, from ${available[0].slot_time}`;
};

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

const eventOptions = computed(() => themeParkStore.events.map((e) => ({ value: e.id, label: e.name })));

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

                <div class="elevated sticky top-[4.25rem] z-10 rounded-xl border bg-surface p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">Find your slot</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <TMultiSelect v-model="selectedEventIds" label="Events" placeholder="All events" :options="eventOptions" width="12rem" />
                        <TMultiSelect v-model="selectedTypes" label="Type" placeholder="All types" :options="typeOptions" width="10rem" />
                        <div class="hidden h-10 w-px self-stretch bg-[rgb(var(--color-border))] sm:block" aria-hidden="true" />
                        <TDatePicker v-model="date" label="Date" class="w-44" />
                        <TNumberInput v-model="tickets" label="Tickets" :min="1" />
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

                <div v-else class="space-y-6">
                    <div v-for="event in visibleEvents" :key="event.id" class="elevated overflow-hidden rounded-xl border bg-surface">
                        <!-- Header + teaser together are the whole click-to-expand surface.
                             The expanded panel below is a separate sibling on purpose: it's
                             full of its own interactive controls (slot buttons, stepper,
                             Add to Cart), and this way a click there simply never reaches the
                             toggle handler, rather than needing `.stop` on every one of them. -->
                        <div
                            role="button"
                            tabindex="0"
                            class="cursor-pointer transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40"
                            :aria-expanded="!!expandedEvents[event.id]"
                            :aria-controls="`event-panel-${event.id}`"
                            @click="toggleExpanded(event.id)"
                            @keydown.enter="toggleExpanded(event.id)"
                            @keydown.space.prevent="toggleExpanded(event.id)"
                        >
                            <div class="flex items-start gap-4 p-5">
                                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted">
                                    <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                                    <span v-else>No image</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-foreground">{{ event.name }}</h3>
                                        <TBadge variant="primary" class="shrink-0 capitalize">
                                            {{ event.type.replace('_', ' ') }}
                                        </TBadge>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-sm text-foreground-secondary">{{ event.description }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-foreground-muted">
                                        <span class="inline-flex items-center gap-1"><TIcon name="pin" :size="13" /> {{ event.location }}</span>
                                        <span class="inline-flex items-center gap-1"><TIcon name="clock" :size="13" /> {{ event.duration_minutes }} min</span>
                                        <span class="font-semibold text-foreground">
                                            ${{ event.price_per_ticket }} <span class="font-normal text-foreground-muted">/ ticket</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking block is collapsed by default - with many events on the
                                 page, showing every slot picker eagerly was most of each card's
                                 height. The teaser keeps it from being a dead end when closed. -->
                            <div class="flex items-center justify-between gap-3 border-t px-5 py-3">
                                <span class="text-sm text-foreground-secondary">{{ slotsTeaser(event) }}</span>
                                <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary">
                                    {{ expandedEvents[event.id] ? 'Hide times' : 'Select a time' }}
                                    <TIcon name="chevronDown" :size="15" class="transition-transform" :class="expandedEvents[event.id] ? 'rotate-180' : ''" />
                                </span>
                            </div>
                        </div>

                        <div :id="`event-panel-${event.id}`" class="grid transition-[grid-template-rows] duration-200 ease-out" :style="{ gridTemplateRows: expandedEvents[event.id] ? '1fr' : '0fr' }">
                            <div class="overflow-hidden">
                                <div class="bg-surface-sunken/40 p-5 pt-4">
                                    <div v-if="loadingSlots[event.id]" class="text-sm text-foreground-muted">Loading time slots...</div>
                                    <div
                                        v-else-if="(slotsByEvent[event.id]?.length ?? 0) === 0"
                                        class="rounded-lg bg-surface-hover p-3 text-sm text-foreground-muted"
                                    >
                                        No time slots available for this date.
                                    </div>
                                    <!-- Same row-list shape as the hotel card's room types: a
                                         bordered, divided list with the key stat as a trailing
                                         badge, not a wrapped chip grid. Times are single-select
                                         (unlike room types), so each row is a real radio input -
                                         the same native-radio treatment TRadio and every other
                                         selector in the app uses, not a hand-rolled circle. -->
                                    <div v-else class="max-h-64 divide-y divide-[rgb(var(--color-border))] overflow-y-auto overflow-x-hidden rounded-lg border bg-surface">
                                        <label
                                            v-for="slot in slotsByEvent[event.id]"
                                            :key="slot.id"
                                            class="flex w-full items-center gap-3 p-3 transition-colors"
                                            :class="[
                                                selectedSlotIds[event.id] === slot.id ? 'bg-primary-soft' : '',
                                                slot.available_capacity < 1 ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:bg-surface-hover',
                                            ]"
                                        >
                                            <input
                                                type="radio"
                                                :name="`event-${event.id}-slot`"
                                                :checked="selectedSlotIds[event.id] === slot.id"
                                                :disabled="slot.available_capacity < 1"
                                                class="h-4 w-4 shrink-0 border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                                @change="selectSlot(event, slot)"
                                            />
                                            <span
                                                class="font-semibold"
                                                :class="selectedSlotIds[event.id] === slot.id ? 'text-primary' : 'text-foreground'"
                                            >
                                                {{ slot.slot_time }}
                                            </span>
                                            <TBadge
                                                :variant="slot.available_capacity < 1 ? 'neutral' : slot.available_capacity > 2 ? 'success' : 'warning'"
                                                size="sm"
                                                dot
                                                class="ml-auto shrink-0"
                                            >
                                                {{ slot.available_capacity < 1 ? 'Full' : `${slot.available_capacity} left` }}
                                            </TBadge>
                                        </label>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t pt-4">
                                        <p
                                            v-if="eventMessages[event.id]"
                                            class="text-sm"
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
                                        <p v-else-if="!selectedSlotFor(event)" class="text-xs text-foreground-muted">
                                            Pick a time above to add tickets
                                        </p>

                                        <div class="ml-auto flex items-center gap-3">
                                            <TNumberInput
                                                label="Tickets"
                                                label-position="left"
                                                :model-value="ticketCountFor(event)"
                                                @update:model-value="v => setTicketCount(event, v)"
                                                :min="1"
                                                :max="selectedSlotFor(event)?.available_capacity"
                                                size="sm"
                                            />
                                            <TButton :disabled="!canBook(event)" @click="addToCart(event)">
                                                Add to Cart
                                            </TButton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
