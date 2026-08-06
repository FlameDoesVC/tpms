<script setup>
/**
 * An attraction's own page: what it is, who it is for, and when you can go.
 *
 * The park page used to carry all of this inside an accordion card, which left
 * no room for photographs, the highlights or the height and age limits - the
 * things a visitor most wants before committing.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import StickyBookingBar from '@/Components/StickyBookingBar.vue';
import SlotPicker from '@/Pages/Visitor/ThemePark/SlotPicker.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import { formatMoney, todayIso } from '@/utils/format';
import { useCartStore } from '@/stores/cart';
import { useThemeParkStore } from '@/stores/themepark';

const route = useRoute();
const router = useRouter();
const themeParkStore = useThemeParkStore();
const cart = useCartStore();

const TYPE_LABELS = { ride: 'Ride', show: 'Show', beach_event: 'Beach event' };

const today = todayIso();
const eventId = computed(() => Number(route.params.id));

const date = ref(today);
const selectedSlotId = ref(null);
const ticketCount = ref(1);
const message = ref(null);
const refreshingSlots = ref(false);

const event = computed(() => themeParkStore.event);

const galleryImages = computed(() => {
    if (!event.value) return [];
    const gallery = event.value.gallery ?? [];
    return event.value.image_url
        ? [{ id: 'cover', url: event.value.image_url }, ...gallery]
        : gallery;
});

// Filtered here rather than in the store: the staff scheduling pages share this
// endpoint and need cancelled rows. A cancelled slot keeps its capacity, so
// without this it stays selectable. A missing status means the row predates the
// column - treat it as bookable.
const slots = computed(() =>
    themeParkStore.slots.filter((slot) => (slot.status ?? 'scheduled') === 'scheduled')
);

const selectedSlot = computed(() => slots.value.find((slot) => slot.id === selectedSlotId.value) ?? null);

const hasRestrictions = computed(() => !!event.value?.min_age || !!event.value?.min_height_cm);

const subtotal = computed(() => ticketCount.value * Number(event.value?.price_per_ticket ?? 0));

const canBook = computed(() => {
    if (!selectedSlot.value) return false;
    return ticketCount.value >= 1 && ticketCount.value <= selectedSlot.value.available_capacity;
});

const selectSlot = (slot) => {
    selectedSlotId.value = slot.id;
    // Keep the count inside what this slot can actually take.
    if (ticketCount.value > slot.available_capacity) ticketCount.value = slot.available_capacity;
};

const addToCart = () => {
    message.value = null;

    if (!selectedSlot.value) {
        message.value = { type: 'error', text: 'Pick a time first.' };
        return;
    }
    if (!canBook.value) {
        message.value = { type: 'error', text: 'Adjust the ticket count to fit the slot capacity.' };
        return;
    }

    cart.addItem({
        type: 'themepark',
        eventId: event.value.id,
        eventName: event.value.name,
        slotId: selectedSlot.value.id,
        slotDate: selectedSlot.value.slot_date?.slice(0, 10),
        slotTime: selectedSlot.value.slot_time,
        ticketCount: ticketCount.value,
        pricePerTicket: event.value.price_per_ticket,
        subtotal: subtotal.value,
    });

    message.value = {
        type: 'success',
        text: `Added ${ticketCount.value} ticket${ticketCount.value === 1 ? '' : 's'} to your itinerary.`,
    };
};

// Replaced rather than pushed, so stepping through a calendar does not fill the
// back button.
const syncDateToUrl = () => {
    router.replace({ query: { ...route.query, date: date.value } });
};

watch(date, async () => {
    // A slot picked on the previous date is not in the new day's list.
    selectedSlotId.value = null;
    syncDateToUrl();

    refreshingSlots.value = true;
    try {
        // keepEvent: only the schedule changes, so the copy and photographs the
        // visitor is reading stay put.
        await themeParkStore.fetchEventSlots(eventId.value, date.value, { keepEvent: true });
    } finally {
        refreshingSlots.value = false;
    }
});

onMounted(async () => {
    if (!Number.isFinite(eventId.value)) {
        themeParkStore.eventNotFound = true;
        return;
    }

    // A stale link to a past date would show an empty schedule.
    const requested = route.query.date;
    date.value = requested && String(requested) >= today ? String(requested) : today;

    await themeParkStore.fetchEventSlots(eventId.value, date.value);

    if (!requested) syncDateToUrl();
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="shell space-y-5 py-6">
            <router-link
                :to="{ name: 'themepark.home' }"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-foreground-secondary transition-colors hover:text-foreground"
            >
                <TIcon name="arrowLeft" :size="15" />
                All attractions
            </router-link>

            <TEmptyState
                v-if="themeParkStore.eventNotFound"
                icon="search"
                title="This attraction isn't available"
                description="It may have been removed, or the link may be out of date."
            >
                <template #action>
                    <router-link :to="{ name: 'themepark.home' }">
                        <TButton>Browse the park</TButton>
                    </router-link>
                </template>
            </TEmptyState>

            <div v-else-if="themeParkStore.loading.event && !event" class="space-y-5">
                <div class="h-72 animate-pulse rounded-xl border bg-surface-hover" />
                <div class="h-6 w-1/3 animate-pulse rounded bg-surface-hover" />
                <div class="h-20 animate-pulse rounded-xl border bg-surface-hover" />
                <div class="h-40 animate-pulse rounded-xl border bg-surface-hover" />
            </div>

            <p v-else-if="themeParkStore.error.event" class="text-danger">{{ themeParkStore.error.event }}</p>

            <template v-else-if="event">
                <ImageGallery :images="galleryImages" :alt="event.name" variant="hero" icon="sparkle" />

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ event.name }}</h1>
                            <TBadge variant="neutral" size="sm">
                                {{ TYPE_LABELS[event.type] ?? event.type }}
                            </TBadge>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-foreground-secondary">
                            <span v-if="event.location" class="inline-flex items-center gap-1.5">
                                <TIcon name="pin" :size="14" class="text-foreground-muted" /> {{ event.location }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <TIcon name="clock" :size="14" class="text-foreground-muted" />
                                {{ event.duration_minutes }} min
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <TIcon name="users" :size="14" class="text-foreground-muted" />
                                {{ event.capacity_per_slot }} per session
                            </span>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-2xl font-semibold tracking-tight text-foreground">
                            {{ formatMoney(event.price_per_ticket) }}
                        </p>
                        <p class="text-xs text-foreground-muted">per ticket</p>
                        <router-link :to="{ hash: '#slots', query: route.query }" class="mt-2 inline-block">
                            <TButton>Pick a time</TButton>
                        </router-link>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <section v-if="event.description" class="rounded-xl border bg-surface p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">About</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-foreground-secondary">
                            {{ event.description }}
                        </p>
                    </section>

                    <div class="space-y-5">
                        <section v-if="event.highlights?.length" class="rounded-xl border bg-surface p-5">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-foreground-muted">Highlights</h2>
                            <ul class="mt-3 space-y-2">
                                <li
                                    v-for="(highlight, i) in event.highlights"
                                    :key="i"
                                    class="flex items-start gap-2.5 text-sm text-foreground-secondary"
                                >
                                    <TIcon name="check" :size="16" class="mt-0.5 shrink-0 text-primary" />
                                    <span>{{ highlight }}</span>
                                </li>
                            </ul>
                        </section>

                        <!-- Ahead of the booking controls on purpose: a limit
                             discovered at the gate is a wasted ticket. -->
                        <section
                            v-if="hasRestrictions"
                            class="rounded-xl border border-warning/40 bg-warning-soft p-5"
                        >
                            <h2 class="inline-flex items-center gap-2 text-sm font-semibold text-warning">
                                <TIcon name="alert" :size="16" /> Before you book
                            </h2>
                            <ul class="mt-2.5 space-y-1.5 text-sm text-foreground-secondary">
                                <li v-if="event.min_age" class="flex items-center gap-2">
                                    <TIcon name="users" :size="15" class="text-warning" />
                                    Ages {{ event.min_age }} and over
                                </li>
                                <li v-if="event.min_height_cm" class="flex items-center gap-2">
                                    <TIcon name="ruler" :size="15" class="text-warning" />
                                    Minimum height {{ event.min_height_cm }} cm
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>

                <StickyBookingBar
                    title="Your visit"
                    hint="Times and availability below are for this date."
                >
                    <div class="grid w-full gap-3 sm:w-auto sm:min-w-[16rem]">
                        <TDatePicker v-model="date" :min="today" label="Date" />
                    </div>
                </StickyBookingBar>

                <section id="slots" class="scroll-mt-28 space-y-3 rounded-xl border bg-surface p-5">
                    <h2 class="text-lg font-semibold tracking-tight text-foreground">Pick a time</h2>

                    <p v-if="refreshingSlots" class="text-xs text-foreground-muted">Updating times…</p>

                    <TEmptyState
                        v-if="slots.length === 0"
                        icon="calendar"
                        title="Nothing scheduled for this date"
                        description="Try another day - the schedule changes through the week."
                    />

                    <template v-else>
                        <SlotPicker :slots="slots" :selected-id="selectedSlotId" @select="selectSlot" />

                        <div class="mt-4 flex flex-wrap items-center gap-3 border-t pt-4">
                            <p class="text-sm text-foreground-secondary">
                                <template v-if="selectedSlot">
                                    {{ formatMoney(event.price_per_ticket) }} × {{ ticketCount }} =
                                    <span class="font-semibold text-foreground">{{ formatMoney(subtotal) }}</span>
                                </template>
                                <template v-else>Choose a time to see the total.</template>
                            </p>

                            <div class="ml-auto flex items-center gap-3">
                                <TNumberInput
                                    v-model="ticketCount"
                                    label="Tickets"
                                    label-position="left"
                                    :min="1"
                                    :max="selectedSlot?.available_capacity ?? 1"
                                    size="sm"
                                    :disabled="!selectedSlot"
                                />
                                <TButton :disabled="!canBook" @click="addToCart">Add to itinerary</TButton>
                            </div>
                        </div>
                    </template>

                    <p
                        v-if="message"
                        class="text-sm"
                        :class="message.type === 'error' ? 'text-danger' : 'text-success'"
                    >
                        {{ message.text }}
                        <router-link
                            v-if="message.type === 'success'"
                            :to="{ name: 'hotels.index' }"
                            class="font-medium underline"
                        >
                            Book a hotel for the stay?
                        </router-link>
                    </p>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
