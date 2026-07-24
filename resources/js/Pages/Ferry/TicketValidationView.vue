<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import QrCameraScanner from '@/Components/QrCameraScanner.vue';
import FerrySeatGrid from '@/Components/FerrySeatGrid.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();

// Scanning only starts once a specific departure is chosen - without this, a
// ticket for yesterday's or next week's crossing would validate just as
// happily as one for the boat actually boarding right now.
const today = new Date().toISOString().slice(0, 10);
const scheduleDate = ref(today);
const dateSchedules = ref([]);
const selectedScheduleId = ref('');
const loadingSchedules = ref(false);

const selectedSchedule = computed(() =>
    dateSchedules.value.find((s) => s.id === Number(selectedScheduleId.value)) ?? null
);

const refreshSchedulesForDate = async () => {
    loadingSchedules.value = true;
    try {
        dateSchedules.value = await ferryStore.getSchedulesForDate(scheduleDate.value);
    } finally {
        loadingSchedules.value = false;
    }
};

watch(scheduleDate, () => {
    selectedScheduleId.value = '';
    refreshSchedulesForDate();
});

onMounted(refreshSchedulesForDate);

const computeSeatRows = (capacity) =>
    Array.from({ length: Math.ceil(capacity / 8) }, (_, r) => {
        const left = [1, 2, 3, 4].map((n) => r * 8 + n).filter((n) => n <= capacity);
        const right = [5, 6, 7, 8].map((n) => r * 8 + n).filter((n) => n <= capacity);
        return { left, right };
    });

// A live, whole-boat view shown on the scanning screen itself - not tied to
// any particular scan, so the operator can see how full the boat is while
// queuing up scans, not just after finding a specific ticket or party.
const boatSeatMap = ref(null);
const loadingBoatSeatMap = ref(false);

const refreshBoatSeatMap = async () => {
    if (!selectedSchedule.value) {
        boatSeatMap.value = null;
        return;
    }
    loadingBoatSeatMap.value = true;
    try {
        boatSeatMap.value = await ferryStore.getSeatMap(selectedSchedule.value.id);
    } finally {
        loadingBoatSeatMap.value = false;
    }
};

watch(selectedScheduleId, refreshBoatSeatMap);

const boatSeatRows = computed(() => (boatSeatMap.value ? computeSeatRows(boatSeatMap.value.capacity) : []));

const boatSeatClass = (seat) => {
    if (boatSeatMap.value?.boarded_seats?.includes(seat)) return 'bg-black text-white';
    if (boatSeatMap.value?.taken_seats?.includes(seat)) return 'bg-gray-300 text-gray-500';
    return 'bg-white border border-gray-300 text-gray-500';
};

const ticketIdInput = ref('');
const ticket = ref(null);
const party = ref(null);
const fallbackSchedule = ref(null);
const lookupError = ref('');
const scanner = ref(null);
const seatMap = ref(null);
const loadingSeatMap = ref(false);
let scanLocked = false;

// A booking only ever needs two legs: TO the island on check-in day, and
// BACK on check-out day - a schedule departing on neither day isn't a
// mismatched seat count problem, it's the wrong ferry entirely for this stay.
const resolveLegDirection = (booking, schedule) => {
    if (!booking || !schedule) return null;
    const scheduleDate = (schedule.departure_date ?? '').slice(0, 10);
    const checkIn = (booking.check_in_date ?? '').slice(0, 10);
    const checkOut = (booking.check_out_date ?? '').slice(0, 10);
    if (scheduleDate === checkIn) return 'arrival';
    if (scheduleDate === checkOut) return 'departure';
    return null;
};

// A scanned/typed code identifies either a ferry ticket (LSJ-T####) or a
// hotel booking (LSJ-B####) - the reference code's own letter says which,
// so the same camera/input handles both without a separate mode switch.
// A bare number (no prefix) falls back to the old behavior of being a ticket id.
const parseScan = (raw) => {
    const text = raw.trim();
    const prefixed = text.match(/LSJ-([A-Z])(\d+)/i);
    if (prefixed) {
        return { type: prefixed[1].toUpperCase() === 'B' ? 'booking' : 'ticket', id: parseInt(prefixed[2], 10) };
    }
    const digits = text.match(/(\d+)\s*$/);
    return digits ? { type: 'ticket', id: parseInt(digits[1], 10) } : null;
};

// The camera and the result panel never show at once - a result found by
// either the scanner or the manual field freezes the camera (not stopping
// it, so the stream is still warm and `start()` on the way back is instant)
// until the operator explicitly returns to scanning.
const lookupParsed = async (parsed) => {
    lookupError.value = '';
    ticket.value = null;
    party.value = null;
    fallbackSchedule.value = null;
    seatMap.value = null;
    selectedNewSeats.value = [];
    issueError.value = '';

    try {
        if (parsed.type === 'booking') {
            party.value = await ferryStore.getPartyStatus(parsed.id, selectedSchedule.value.id);
            scanner.value?.pause();

            // The selected departure doesn't fit this stay at all - rather
            // than a dead end, look up the boat actually scheduled for this
            // booking's check-in day so the party can still be ticketed
            // ahead of time for the trip they'll actually take.
            if (!resolveLegDirection(party.value.booking, selectedSchedule.value)) {
                const checkInDate = (party.value.booking.check_in_date ?? '').slice(0, 10);
                const schedulesForCheckIn = await ferryStore.getSchedulesForDate(checkInDate);
                fallbackSchedule.value = schedulesForCheckIn[0] ?? null;

                if (fallbackSchedule.value) {
                    party.value = await ferryStore.getPartyStatus(parsed.id, fallbackSchedule.value.id);
                }
            }

            const scheduleForSeatMap = fallbackSchedule.value ?? selectedSchedule.value;
            if (resolveLegDirection(party.value.booking, scheduleForSeatMap)) {
                loadingSeatMap.value = true;
                try {
                    seatMap.value = await ferryStore.getSeatMap(scheduleForSeatMap.id);
                } finally {
                    loadingSeatMap.value = false;
                }
            }
        } else {
            ticket.value = await ferryStore.lookupTicket(parsed.id);
            scanner.value?.pause();
            loadingSeatMap.value = true;
            try {
                seatMap.value = await ferryStore.getSeatMap(ticket.value.schedule_id);
            } finally {
                loadingSeatMap.value = false;
            }
        }
    } catch {
        lookupError.value = parsed.type === 'booking' ? 'Booking not found.' : 'Ticket not found.';
    }
};

const lookupManually = () => {
    const parsed = parseScan(ticketIdInput.value);
    if (!parsed) {
        lookupError.value = 'Not found.';
        return;
    }
    lookupParsed(parsed);
};

const onDecode = async (data) => {
    if (scanLocked) return;
    const parsed = parseScan(data);
    if (!parsed) return;
    scanLocked = true;
    await lookupParsed(parsed);
    scanLocked = false;
};

const backToScanning = () => {
    ticket.value = null;
    party.value = null;
    fallbackSchedule.value = null;
    lookupError.value = '';
    issueError.value = '';
    ticketIdInput.value = '';
    seatMap.value = null;
    selectedNewSeats.value = [];
    scanner.value?.resume();
    refreshBoatSeatMap();
};

const changeDeparture = () => {
    selectedScheduleId.value = '';
    backToScanning();
};

const confirmUsed = async () => {
    try {
        ticket.value = await ferryStore.validateTicketOnSite(ticket.value.id);
    } catch {
        lookupError.value = 'This ticket has already been used.';
    }
};

const cancelTicket = async () => {
    try {
        ticket.value = await ferryStore.cancelTicket(ticket.value.id);
        refreshBoatSeatMap();
    } catch {
        lookupError.value = 'This ticket could not be cancelled.';
    }
};

// A ticket can be looked up regardless of which departure is selected (the
// same ticket ID space isn't scoped to one schedule) - this is what actually
// stops it from being confirmed against the wrong one.
const scheduleMismatch = computed(() =>
    !!(ticket.value && selectedSchedule.value && ticket.value.schedule_id !== selectedSchedule.value.id)
);

const isActionable = computed(
    () => ticket.value && !scheduleMismatch.value && ticket.value.status !== 'used' && ticket.value.status !== 'cancelled'
);

const statusLabel = computed(() => {
    if (!ticket.value) return '';
    if (scheduleMismatch.value) return 'Wrong Departure';
    if (ticket.value.status === 'used') return 'Already Used';
    if (ticket.value.status === 'cancelled') return 'Cancelled';
    if (ticket.value.payment_method === 'cash') return 'Valid Ticket - Collect Cash';
    return 'Valid Ticket';
});

const panelClasses = computed(() => {
    if (!ticket.value) return '';
    if (scheduleMismatch.value) return 'bg-orange-50';
    if (ticket.value.status === 'used') return 'bg-red-50';
    if (ticket.value.status === 'cancelled') return 'bg-gray-100';
    if (ticket.value.payment_method === 'cash') return 'bg-yellow-50';
    return 'bg-green-50';
});

const headingClasses = computed(() => {
    if (!ticket.value) return '';
    if (scheduleMismatch.value) return 'text-orange-800';
    if (ticket.value.status === 'used') return 'text-red-800';
    if (ticket.value.status === 'cancelled') return 'text-gray-600';
    if (ticket.value.payment_method === 'cash') return 'text-yellow-900';
    return 'text-green-800';
});

const seatRows = computed(() => (seatMap.value ? computeSeatRows(seatMap.value.capacity) : []));

const seatState = (seat) => {
    if (seat === ticket.value?.seat_number) return 'ticket';
    if (seatMap.value?.taken_seats?.includes(seat)) return 'taken';
    return 'available';
};

const ticketSeatClass = (seat) => ({
    taken: 'bg-gray-300 text-gray-500',
    ticket: 'bg-indigo-600 text-white',
    available: 'bg-white border border-gray-300 text-gray-500',
}[seatState(seat)]);

// Booking party walk-up sale - picking seats to fill the gap between
// party_guests_count and what's already been issued for this date.
const selectedNewSeats = ref([]);
const newPaymentMethod = ref('cash');
const issuing = ref(false);
const issueError = ref('');

const partyTicketsOnCurrentSchedule = computed(() =>
    (party.value?.tickets ?? []).filter((t) => t.schedule_id === effectiveSchedule.value?.id)
);

// The party's own seats show their real status (issued vs already boarded)
// rather than one flat "taken" color, so the whole map doubles as the
// occupancy display the list used to be - no separate list needed to see
// who's on board.
const partySeatState = (seat) => {
    const ownTicket = partyTicketsOnCurrentSchedule.value.find((t) => t.seat_number === seat);
    if (ownTicket) return ownTicket.status === 'used' ? 'party-used' : 'party-issued';
    if (seatMap.value?.taken_seats?.includes(seat)) return 'taken';
    if (selectedNewSeats.value.includes(seat)) return 'selected';
    return 'available';
};

const partySeatClass = (seat) => ({
    'party-issued': 'bg-indigo-400 text-white cursor-default',
    'party-used': 'bg-green-500 text-white cursor-default',
    taken: 'bg-gray-300 text-gray-500 cursor-not-allowed',
    selected: 'bg-indigo-600 text-white',
    available: 'bg-white border border-gray-300 text-gray-500 hover:border-indigo-400',
}[partySeatState(seat)]);

const toggleNewSeat = (seat) => {
    if (!canBookNewSeats.value || !party.value.remaining_seats) return;
    const state = partySeatState(seat);
    if (state !== 'available' && state !== 'selected') return;
    if (state === 'selected') {
        selectedNewSeats.value = selectedNewSeats.value.filter((s) => s !== seat);
        return;
    }
    if (selectedNewSeats.value.length >= party.value.remaining_seats) return;
    selectedNewSeats.value = [...selectedNewSeats.value, seat];
};

// Only tickets still needing to be boarded get a "Mark Used" button - the
// map above already shows everyone else's state, this is just the action.
const pendingBoardingTickets = computed(() => (party.value?.tickets ?? []).filter((t) => t.status === 'issued'));

const partyLegDirection = computed(() => resolveLegDirection(party.value?.booking, selectedSchedule.value));

// Once a fallback (the check-in day's boat) has been found, IT is what the
// seat map/walk-up sale actually operate against, not the originally
// selected departure.
const effectiveSchedule = computed(() => fallbackSchedule.value ?? selectedSchedule.value);
const showingFallback = computed(() => !partyLegDirection.value && !!fallbackSchedule.value);
const noFallbackAvailable = computed(() => !!party.value && !partyLegDirection.value && !fallbackSchedule.value);
// Either the selected departure directly fits this stay, or a fallback (the
// check-in day's boat) was found to fit it instead - anything else means
// there's no legitimate schedule to book this party's tickets against.
const canBookNewSeats = computed(() => !!partyLegDirection.value || showingFallback.value);

const issueNewTickets = async () => {
    if (!canBookNewSeats.value) return;
    issuing.value = true;
    issueError.value = '';
    try {
        await ferryStore.issueWalkupTicket(effectiveSchedule.value.id, party.value.booking.id, {
            seatNumbers: selectedNewSeats.value,
            paymentMethod: newPaymentMethod.value,
        });
        party.value = await ferryStore.getPartyStatus(party.value.booking.id, effectiveSchedule.value.id);
        seatMap.value = await ferryStore.getSeatMap(effectiveSchedule.value.id);
        selectedNewSeats.value = [];
        refreshBoatSeatMap();
    } catch (e) {
        // The backend can reject this under several different field keys
        // (seat_numbers, schedule_id, booking_id depending on which check
        // failed) - grab whichever one actually came back instead of only
        // ever checking seat_numbers, or the real reason gets swallowed.
        const errors = e.response?.data?.errors ?? {};
        issueError.value = Object.values(errors)[0]?.[0] ?? 'Could not issue ticket(s).';
    } finally {
        issuing.value = false;
    }
};

// Lets the operator confirm a ticket as used straight from the party list -
// most importantly, a ticket they JUST walked-up-sold above, without having
// to separately re-scan its own QR code to board that passenger.
const markingUsedId = ref(null);
const ticketActionError = ref('');

const markTicketUsed = async (t) => {
    markingUsedId.value = t.id;
    ticketActionError.value = '';
    try {
        await ferryStore.validateTicketOnSite(t.id);
        party.value = await ferryStore.getPartyStatus(party.value.booking.id, effectiveSchedule.value.id);
    } catch {
        ticketActionError.value = 'Could not mark that ticket as used.';
    } finally {
        markingUsedId.value = null;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ticket Validation
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg space-y-6 sm:px-6 lg:px-8">
                <div v-if="!selectedSchedule" class="rounded-lg bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" v-model="scheduleDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />

                    <label class="mt-3 block text-sm font-medium text-gray-700">Departure</label>
                    <div v-if="loadingSchedules" class="mt-1 text-sm text-gray-500">Loading departures...</div>
                    <div v-else-if="dateSchedules.length === 0" class="mt-1 text-sm text-gray-500">
                        No departures scheduled for this date.
                    </div>
                    <div v-else class="mt-2 space-y-2">
                        <label
                            v-for="schedule in dateSchedules"
                            :key="schedule.id"
                            class="flex items-center gap-2 rounded-md border p-3 text-sm"
                            :class="Number(selectedScheduleId) === schedule.id ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                        >
                            <input type="radio" :value="schedule.id" v-model="selectedScheduleId" />
                            <span class="font-medium text-gray-900">{{ schedule.ferry?.name }}</span>
                            <span class="text-gray-500">- {{ schedule.departure_time }}</span>
                        </label>
                    </div>
                </div>

                <template v-else>
                    <div class="flex items-center justify-between rounded-lg bg-indigo-50 p-3 text-sm">
                        <span class="font-medium text-indigo-900">
                            Checking in: {{ selectedSchedule.ferry?.name }} - {{ scheduleDate }} at {{ selectedSchedule.departure_time }}
                        </span>
                        <button type="button" @click="changeDeparture" class="font-medium text-indigo-600 underline hover:text-indigo-800">
                            Change
                        </button>
                    </div>

                    <div v-show="!ticket && !party" class="rounded-lg bg-white p-4 shadow-sm">
                        <QrCameraScanner ref="scanner" @decode="onDecode" />
                        <form @submit.prevent="lookupManually" class="mt-3 flex gap-2">
                            <input
                                v-model="ticketIdInput"
                                placeholder="Scan a ticket (LSJ-T0012) or a hotel booking (LSJ-B0007)"
                                class="flex-1 rounded-md border-gray-300 text-sm shadow-sm"
                            />
                            <PrimaryButton type="submit">Look Up</PrimaryButton>
                        </form>

                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <p class="text-sm font-medium text-gray-700">Seat Map - {{ selectedSchedule.ferry?.name }}</p>
                            <div v-if="loadingBoatSeatMap" class="mt-2 text-sm text-gray-500">Loading seat map...</div>
                            <div v-else-if="boatSeatMap" class="mt-2 rounded-lg bg-gray-50 p-3">
                                <FerrySeatGrid :rows="boatSeatRows" :seat-class="boatSeatClass" />
                                <div class="mt-2 flex justify-center gap-3 text-[11px] text-gray-500">
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-black"></span> Boarded</span>
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-gray-300"></span> Taken</span>
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-gray-300 bg-white"></span> Available</span>
                                </div>
                                <p class="mt-2 text-center text-xs text-gray-500">
                                    {{ boatSeatMap.taken_seats.length }} of {{ boatSeatMap.capacity }} seats taken
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="lookupError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                        {{ lookupError }}
                    </div>

                    <div v-if="ticket" class="rounded-lg p-6" :class="panelClasses">
                        <p class="font-semibold" :class="headingClasses">
                            {{ statusLabel }} - {{ ticket.reference_code }}
                        </p>
                        <p v-if="scheduleMismatch" class="mt-1 text-sm text-orange-700">
                            This ticket is for {{ ticket.schedule?.ferry?.name }} on
                            {{ ticket.schedule?.departure_date?.slice(0, 10) }} at {{ ticket.schedule?.departure_time }},
                            not the selected departure.
                        </p>

                        <p v-if="ticket.payment_method === 'cash' && isActionable" class="mt-2 text-4xl font-bold text-yellow-900">
                            ${{ ticket.price }}
                        </p>

                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Passenger</dt>
                                <dd>{{ ticket.user?.name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Hotel booking ref</dt>
                                <dd>{{ ticket.booking?.reference_code }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Departure</dt>
                                <dd>{{ ticket.schedule?.departure_date?.slice(0, 10) }} {{ ticket.schedule?.departure_time }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Seat</dt>
                                <dd>{{ ticket.seat_number }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Payment</dt>
                                <dd>{{ ticket.payment_method === 'cash' ? 'Cash on board' : `Paid online ($${ticket.price})` }}</dd>
                            </div>
                        </dl>

                        <div v-if="loadingSeatMap" class="mt-4 text-sm text-gray-500">Loading seat map...</div>
                        <div v-else-if="seatMap" class="mt-4 rounded-lg bg-white/60 p-3">
                            <FerrySeatGrid :rows="seatRows" :seat-class="ticketSeatClass" />
                            <div class="mt-2 flex justify-center gap-3 text-[11px] text-gray-500">
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-indigo-600"></span> This ticket</span>
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-gray-300"></span> Taken</span>
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-gray-300 bg-white"></span> Available</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <PrimaryButton v-if="isActionable" @click="confirmUsed">Confirm Used</PrimaryButton>
                            <DangerButton v-if="isActionable" @click="cancelTicket">Cancel Ticket</DangerButton>
                            <button
                                type="button"
                                @click="backToScanning"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Scan Next
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="party"
                        class="rounded-lg p-6 shadow-sm"
                        :class="showingFallback ? 'bg-red-50 ring-2 ring-red-400' : 'bg-white'"
                    >
                        <p class="font-semibold text-gray-900">Party of {{ party.party_guests_count }}</p>

                        <div class="mt-3 rounded-md border p-3 text-sm" :class="showingFallback ? 'border-red-200 bg-white' : 'border-gray-200'">
                            <p class="font-medium text-gray-900">{{ party.booking.room?.hotel?.name }}</p>
                            <p class="text-gray-500">
                                {{ party.booking.room?.type }} room -
                                {{ party.booking.check_in_date?.slice(0, 10) }} to {{ party.booking.check_out_date?.slice(0, 10) }}
                            </p>
                            <p class="text-gray-500">
                                {{ party.booking.reference_code }}
                                <span
                                    class="ml-1 rounded px-1.5 py-0.5 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800': party.booking.status === 'confirmed',
                                        'bg-amber-100 text-amber-800': party.booking.status === 'pending',
                                        'bg-red-100 text-red-700': party.booking.status === 'cancelled',
                                    }"
                                >
                                    {{ party.booking.status }}
                                </span>
                            </p>
                        </div>

                        <div v-if="party.booking.status !== 'confirmed'" class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                            This booking is {{ party.booking.status }}, not confirmed - walk-up ticket purchases will be
                            rejected until the hotel booking itself is confirmed.
                        </div>

                        <div v-if="showingFallback" class="mt-3 rounded-md bg-red-100 p-3 text-sm font-medium text-red-800">
                            Hotel check-in is on {{ fallbackSchedule.departure_date?.slice(0, 10) }}, showing seat map for
                            {{ fallbackSchedule.ferry?.name }} on that day.
                        </div>

                        <div v-else-if="noFallbackAvailable" class="mt-3 rounded-md bg-orange-50 p-3 text-sm text-orange-800">
                            This departure ({{ selectedSchedule.departure_date?.slice(0, 10) }}) doesn't match this booking's
                            stay - check-in is {{ party.booking.check_in_date?.slice(0, 10) }}, check-out is
                            {{ party.booking.check_out_date?.slice(0, 10) }} - and no departure is scheduled for the check-in
                            date either. Select the departure for one of those dates instead.
                        </div>

                        <template v-if="partyLegDirection || showingFallback">
                            <div class="mt-3 rounded-md p-3" :class="party.remaining_seats > 0 ? 'bg-yellow-50' : 'bg-green-50'">
                                <p class="font-semibold" :class="party.remaining_seats > 0 ? 'text-yellow-800' : 'text-green-800'">
                                    {{
                                        party.remaining_seats > 0
                                            ? `${party.remaining_seats} seat(s) not yet booked for this date`
                                            : 'Fully booked for this date'
                                    }}
                                </p>
                            </div>

                            <div class="mt-4">
                                <p class="text-sm font-medium text-gray-700">Seat Map</p>
                                <div v-if="loadingSeatMap" class="mt-2 text-sm text-gray-500">Loading seat map...</div>
                                <div v-else-if="seatMap" class="mt-2 rounded-lg bg-gray-50 p-3">
                                    <FerrySeatGrid :rows="seatRows" :seat-class="partySeatClass" :on-seat-click="toggleNewSeat" />
                                    <div class="mt-2 flex flex-wrap justify-center gap-3 text-[11px] text-gray-500">
                                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-indigo-400"></span> This party (issued)</span>
                                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-green-500"></span> This party (boarded)</span>
                                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-gray-300"></span> Other passenger</span>
                                        <span v-if="party.remaining_seats > 0" class="flex items-center gap-1">
                                            <span class="h-2.5 w-2.5 rounded bg-indigo-600"></span> Selected
                                        </span>
                                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-gray-300 bg-white"></span> Available</span>
                                    </div>
                                </div>

                                <p v-if="ticketActionError" class="mt-2 text-sm text-red-600">{{ ticketActionError }}</p>
                                <div v-if="pendingBoardingTickets.length" class="mt-3 space-y-1">
                                    <div
                                        v-for="t in pendingBoardingTickets"
                                        :key="t.id"
                                        class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-1.5 text-sm"
                                    >
                                        <span>{{ t.user?.name }} - seat {{ t.seat_number }}</span>
                                        <button
                                            type="button"
                                            :disabled="markingUsedId === t.id"
                                            @click="markTicketUsed(t)"
                                            class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                                        >
                                            Mark Used
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="party.remaining_seats > 0" class="mt-4 border-t border-gray-200 pt-4">
                                <p class="text-sm font-medium text-gray-900">Book remaining seat(s) - walk-up / cash sale</p>

                                <div class="mt-3 flex items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" v-model="newPaymentMethod" value="cash" /> Cash
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" v-model="newPaymentMethod" value="online" /> Card
                                    </label>
                                </div>

                                <p v-if="issueError" class="mt-2 text-sm text-red-600">{{ issueError }}</p>

                                <PrimaryButton class="mt-3" :disabled="selectedNewSeats.length === 0 || issuing" @click="issueNewTickets">
                                    Issue {{ selectedNewSeats.length }} Ticket{{ selectedNewSeats.length === 1 ? '' : 's' }}
                                </PrimaryButton>
                            </div>
                        </template>

                        <button
                            type="button"
                            @click="backToScanning"
                            class="mt-4 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Scan Next
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
