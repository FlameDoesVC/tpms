<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import { formatDate, formatDateTime, formatTime, todayIso } from '@/utils/format';
import TIcon from '@/Components/ui/TIcon.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import QrCameraScanner from '@/Components/QrCameraScanner.vue';
import FerrySeatGrid from '@/Components/FerrySeatGrid.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();

// Scanning only starts once a specific departure is chosen - without this, a
// ticket for yesterday's or next week's crossing would validate just as
// happily as one for the boat actually boarding right now.
const today = todayIso();
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

// Rows straight from the ferry's own deck plan, which the seat-map endpoint
// now returns. This used to rebuild a four-plus-aisle-plus-four grid from the
// capacity number alone - the reason a custom deck could never show up at the
// gate, and a second copy of an assumption that belonged in the data.
const computeSeatRows = (map) => {
    const grid = map?.layout?.grid ?? [];
    let number = 0;
    return grid.map((row, rowIndex) => ({
        number: rowIndex + 1,
        cells: [...row].map((cell) => (cell === 'S' ? ++number : null)),
    }));
};

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

const boatSeatRows = computed(() => computeSeatRows(boatSeatMap.value));

const boatSeatClass = (seat) => {
    if (boatSeatMap.value?.boarded_seats?.includes(seat)) return 'bg-foreground text-surface';
    if (boatSeatMap.value?.taken_seats?.includes(seat)) return 'bg-foreground-muted text-surface';
    return 'bg-surface border border-strong text-foreground-muted';
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

// A scanned/typed code identifies either a ferry ticket (VFN-T…) or a hotel
// booking (VFN-B…) - the reference code's own letter says which, so the same
// camera/input handles both without a separate mode switch.
//
// The code is passed through whole. It used to have its trailing digits parsed
// into a primary key, which meant a made-up sequential code was worth exactly as
// much as a real ticket at the gate; codes are now opaque and resolved by the
// server. A bare number is no longer accepted, because it never identified a
// ticket so much as guessed at one.
const parseScan = (raw) => {
    const code = String(raw ?? '').trim().toUpperCase();
    const match = code.match(/^(VFN-([BTE])[A-Z0-9]+)$/);

    if (! match) return null;

    return { type: match[2] === 'B' ? 'booking' : 'ticket', code: match[1] };
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
            party.value = await ferryStore.getPartyStatusByCode(parsed.code, selectedSchedule.value.id);
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
                    party.value = await ferryStore.getPartyStatusByCode(parsed.code, fallbackSchedule.value.id);
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
            ticket.value = await ferryStore.lookupTicketByCode(parsed.code);
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
        ticket.value = await ferryStore.validateTicketOnSite(
            ticket.value.id,
            selectedSchedule.value?.id
        );
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
    if (scheduleMismatch.value) return 'bg-warning-soft ring-2 ring-warning/40';
    if (ticket.value.status === 'used') return 'bg-danger-soft';
    if (ticket.value.status === 'cancelled') return 'bg-surface-hover';
    if (ticket.value.payment_method === 'cash') return 'bg-warning-soft';
    return 'bg-success-soft';
});

const headingClasses = computed(() => {
    if (!ticket.value) return '';
    if (scheduleMismatch.value) return 'text-warning';
    if (ticket.value.status === 'used') return 'text-danger';
    if (ticket.value.status === 'cancelled') return 'text-foreground-secondary';
    if (ticket.value.payment_method === 'cash') return 'text-warning';
    return 'text-success';
});

const seatRows = computed(() => computeSeatRows(seatMap.value));

const seatState = (seat) => {
    if (seat === ticket.value?.seat_number) return 'ticket';
    if (seatMap.value?.taken_seats?.includes(seat)) return 'taken';
    return 'available';
};

const ticketSeatClass = (seat) => ({
    taken: 'bg-foreground-muted text-surface',
    ticket: 'bg-primary text-white',
    available: 'bg-surface border border-strong text-foreground-muted',
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
    'party-issued': 'bg-primary/60 text-white cursor-default',
    'party-used': 'bg-success text-white cursor-default',
    taken: 'bg-foreground-muted text-surface cursor-not-allowed',
    selected: 'bg-primary text-white',
    available: 'bg-surface border border-strong text-foreground-muted hover:border-primary',
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

const bookingStatusVariant = (status) => ({
    confirmed: 'success',
    pending: 'warning',
    cancelled: 'danger',
}[status] ?? 'neutral');

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
        // The departure being boarded, not the ticket's own - a party can hold
        // tickets for several sailings on one date, and only the ones for this
        // boat may be boarded here. The server rejects the rest.
        await ferryStore.validateTicketOnSite(t.id, effectiveSchedule.value?.id);
        party.value = await ferryStore.getPartyStatus(party.value.booking.id, effectiveSchedule.value.id);
    } catch (e) {
        ticketActionError.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ')
            || 'Could not mark that ticket as used.';
    } finally {
        markingUsedId.value = null;
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Ticket Validation" icon="scan" />
        </template>

        <!-- Deliberately a narrow, centred column: this is a one-thing-at-a-time
             screen used at the gangway, not a management table. The wider page
             shell behind it is what makes that read as a choice. -->
        <div class="mx-auto max-w-xl space-y-5">
            <div v-if="!selectedSchedule" class="elevated rounded-xl border bg-surface p-4">
                <TDatePicker v-model="scheduleDate" label="Date" />

                <label class="mt-3 block text-sm font-medium text-foreground-secondary">Departure</label>
                <div v-if="loadingSchedules" class="mt-1 text-sm text-foreground-muted">Loading departures...</div>
                <div v-else-if="dateSchedules.length === 0" class="mt-1 text-sm text-foreground-muted">
                    No departures scheduled for this date.
                </div>
                <div v-else class="mt-2 space-y-2">
                    <label
                        v-for="schedule in dateSchedules"
                        :key="schedule.id"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm transition-colors"
                        :class="Number(selectedScheduleId) === schedule.id ? 'border-primary bg-primary-soft' : 'hover:bg-surface-hover'"
                    >
                        <input type="radio" :value="schedule.id" v-model="selectedScheduleId" class="text-primary focus:ring-primary/30" />
                        <span class="font-medium text-foreground">{{ schedule.ferry?.name }}</span>
                        <span class="text-foreground-muted">· {{ formatTime(schedule.departure_time) }}</span>
                    </label>
                </div>
            </div>

            <template v-else>
                <div class="flex items-center justify-between rounded-xl bg-primary-soft p-3 text-sm">
                    <span class="font-medium text-primary">
                        Checking in: {{ selectedSchedule.ferry?.name }} · {{ formatDateTime(scheduleDate, selectedSchedule.departure_time) }}
                    </span>
                    <button type="button" @click="changeDeparture" class="font-medium text-primary underline hover:no-underline">
                        Change
                    </button>
                </div>

                <div v-show="!ticket && !party" class="elevated rounded-xl border bg-surface p-4">
                    <QrCameraScanner ref="scanner" @decode="onDecode" />
                    <form @submit.prevent="lookupManually" class="mt-3 flex items-start gap-2">
                        <TInput
                            v-model="ticketIdInput"
                            placeholder="Scan a ticket (VFN-T0012) or a hotel booking (VFN-B0007)"
                            class="flex-1"
                        />
                        <TButton type="submit">
                            <TIcon name="search" :size="16" />
                            Look Up
                        </TButton>
                    </form>

                    <div class="mt-4 border-t pt-4">
                        <p class="text-sm font-medium text-foreground-secondary">Seat Map - {{ selectedSchedule.ferry?.name }}</p>
                        <div v-if="loadingBoatSeatMap" class="mt-2 text-sm text-foreground-muted">Loading seat map...</div>
                        <div v-else-if="boatSeatMap" class="mt-2 rounded-xl bg-surface-hover p-3">
                            <FerrySeatGrid :rows="boatSeatRows" :seat-class="boatSeatClass" />
                            <div class="mt-2 flex justify-center gap-3 text-[11px] text-foreground-muted">
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-foreground"></span> Boarded</span>
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-foreground-muted"></span> Taken</span>
                                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-strong bg-surface"></span> Available</span>
                            </div>
                            <p class="mt-2 text-center text-xs text-foreground-muted">
                                {{ boatSeatMap.taken_seats.length }} of {{ boatSeatMap.capacity }} seats taken
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="lookupError" class="rounded-xl bg-danger-soft p-4 text-sm text-danger">
                    {{ lookupError }}
                </div>

                <div v-if="ticket" class="rounded-xl border p-6" :class="panelClasses">
                    <p class="font-semibold" :class="headingClasses">
                        {{ statusLabel }} - {{ ticket.reference_code }}
                    </p>
                    <p v-if="scheduleMismatch" class="mt-1 text-sm text-warning">
                        This ticket is for {{ ticket.schedule?.ferry?.name }} on
                        {{ formatDateTime(ticket.schedule?.departure_date, ticket.schedule?.departure_time) }},
                        not the selected departure.
                    </p>

                    <p v-if="ticket.payment_method === 'cash' && isActionable" class="mt-2 text-4xl font-bold text-warning">
                        ${{ ticket.price }}
                    </p>

                    <dl class="mt-3 space-y-1 text-sm text-foreground">
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Passenger</dt>
                            <dd>{{ ticket.user?.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Hotel booking ref</dt>
                            <dd>{{ ticket.booking?.reference_code }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Departure</dt>
                            <dd>{{ formatDateTime(ticket.schedule?.departure_date, ticket.schedule?.departure_time) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Seat</dt>
                            <dd>{{ ticket.seat_number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Payment</dt>
                            <dd>{{ ticket.payment_method === 'cash' ? 'Cash on board' : `Paid online ($${ticket.price})` }}</dd>
                        </div>
                    </dl>

                    <div v-if="loadingSeatMap" class="mt-4 text-sm text-foreground-muted">Loading seat map...</div>
                    <div v-else-if="seatMap" class="mt-4 rounded-xl bg-surface/60 p-3">
                        <FerrySeatGrid :rows="seatRows" :seat-class="ticketSeatClass" />
                        <div class="mt-2 flex justify-center gap-3 text-[11px] text-foreground-muted">
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-primary"></span> This ticket</span>
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-foreground-muted"></span> Taken</span>
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-strong bg-surface"></span> Available</span>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <TButton v-if="isActionable" @click="confirmUsed">Confirm Used</TButton>
                        <TButton v-if="isActionable" variant="danger" @click="cancelTicket">Cancel Ticket</TButton>
                        <TButton variant="secondary" type="button" @click="backToScanning">Scan Next</TButton>
                    </div>
                </div>

                <div
                    v-if="party"
                    class="rounded-xl border p-6"
                    :class="showingFallback ? 'bg-danger-soft ring-2 ring-danger/40' : 'bg-surface'"
                >
                    <p class="font-semibold text-foreground">Party of {{ party.party_guests_count }}</p>

                    <div class="mt-3 rounded-lg border p-3 text-sm" :class="showingFallback ? 'bg-surface' : ''">
                        <p class="font-medium text-foreground">{{ party.booking.room?.hotel?.name }}</p>
                        <p class="text-foreground-muted">
                            {{ party.booking.room?.type }} room -
                            {{ party.booking.check_in_date?.slice(0, 10) }} to {{ party.booking.check_out_date?.slice(0, 10) }}
                        </p>
                        <p class="text-foreground-muted">
                            {{ party.booking.reference_code }}
                            <TBadge :variant="bookingStatusVariant(party.booking.status)" class="ml-1 capitalize">
                                {{ party.booking.status }}
                            </TBadge>
                        </p>
                    </div>

                    <div v-if="party.booking.status !== 'confirmed'" class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-warning">
                        This booking is {{ party.booking.status }}, not confirmed - walk-up ticket purchases will be
                        rejected until the hotel booking itself is confirmed.
                    </div>

                    <div v-if="showingFallback" class="mt-3 rounded-lg bg-danger-soft p-3 text-sm font-medium text-danger">
                        Hotel check-in is on {{ formatDate(fallbackSchedule.departure_date) }}, showing seat map for
                        {{ fallbackSchedule.ferry?.name }} on that day.
                    </div>

                    <div v-else-if="noFallbackAvailable" class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-warning">
                        This departure ({{ formatDate(selectedSchedule.departure_date) }}) doesn't match this booking's
                        stay — check-in is {{ formatDate(party.booking.check_in_date) }}, check-out is
                        {{ formatDate(party.booking.check_out_date) }} — and no departure is scheduled for the check-in
                        date either. Select the departure for one of those dates instead.
                    </div>

                    <template v-if="partyLegDirection || showingFallback">
                        <div class="mt-3 rounded-lg p-3" :class="party.remaining_seats > 0 ? 'bg-warning-soft' : 'bg-success-soft'">
                            <p class="font-semibold" :class="party.remaining_seats > 0 ? 'text-warning' : 'text-success'">
                                {{
                                    party.remaining_seats > 0
                                        ? `${party.remaining_seats} seat(s) not yet booked for this date`
                                        : 'Fully booked for this date'
                                }}
                            </p>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-medium text-foreground-secondary">Seat Map</p>
                            <div v-if="loadingSeatMap" class="mt-2 text-sm text-foreground-muted">Loading seat map...</div>
                            <div v-else-if="seatMap" class="mt-2 rounded-xl bg-surface-hover p-3">
                                <FerrySeatGrid :rows="seatRows" :seat-class="partySeatClass" :on-seat-click="toggleNewSeat" />
                                <div class="mt-2 flex flex-wrap justify-center gap-3 text-[11px] text-foreground-muted">
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-primary/60"></span> This party (issued)</span>
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-success"></span> This party (boarded)</span>
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-foreground-muted"></span> Other passenger</span>
                                    <span v-if="party.remaining_seats > 0" class="flex items-center gap-1">
                                        <span class="h-2.5 w-2.5 rounded bg-primary"></span> Selected
                                    </span>
                                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-strong bg-surface"></span> Available</span>
                                </div>
                            </div>

                            <p v-if="ticketActionError" class="mt-2 text-sm text-danger">{{ ticketActionError }}</p>
                            <div v-if="pendingBoardingTickets.length" class="mt-3 space-y-1">
                                <div
                                    v-for="t in pendingBoardingTickets"
                                    :key="t.id"
                                    class="flex items-center justify-between rounded-lg bg-surface-hover px-3 py-1.5 text-sm text-foreground"
                                >
                                    <span>{{ t.user?.name }} - seat {{ t.seat_number }}</span>
                                    <TButton
                                        size="xs"
                                        type="button"
                                        :loading="markingUsedId === t.id"
                                        @click="markTicketUsed(t)"
                                    >
                                        Mark Used
                                    </TButton>
                                </div>
                            </div>
                        </div>

                        <div v-if="party.remaining_seats > 0" class="mt-4 border-t pt-4">
                            <p class="text-sm font-medium text-foreground">Book remaining seat(s) - walk-up / cash sale</p>

                            <div class="mt-3 flex items-center gap-4">
                                <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                                    <input type="radio" v-model="newPaymentMethod" value="cash" class="text-primary focus:ring-primary/30" /> Cash
                                </label>
                                <label class="flex items-center gap-2 text-sm text-foreground-secondary">
                                    <input type="radio" v-model="newPaymentMethod" value="online" class="text-primary focus:ring-primary/30" /> Card
                                </label>
                            </div>

                            <p v-if="issueError" class="mt-2 text-sm text-danger">{{ issueError }}</p>

                            <TButton
                                class="mt-3"
                                :disabled="selectedNewSeats.length === 0"
                                :loading="issuing"
                                @click="issueNewTickets"
                            >
                                Issue {{ selectedNewSeats.length }} Ticket{{ selectedNewSeats.length === 1 ? '' : 's' }}
                            </TButton>
                        </div>
                    </template>

                    <TButton variant="secondary" type="button" class="mt-4" @click="backToScanning">Scan Next</TButton>
                </div>
            </template>
        </div>
    </StaffLayout>
</template>
