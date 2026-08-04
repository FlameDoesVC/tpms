<script setup>
import { computed, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import { formatDateTime } from '@/utils/format';
import QrCameraScanner from '@/Components/QrCameraScanner.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const bookingIdInput = ref('');
const booking = ref(null);
const lookupError = ref('');
const scanner = ref(null);
let scanLocked = false;

// The scanned code is passed through whole. It used to have its trailing digits
// parsed into a booking id, so a typed-in sequential guess admitted its holder on
// someone else's ticket; codes are opaque now and resolved by the server.
const parseTicketCode = (raw) => {
    const code = String(raw ?? '').trim().toUpperCase();
    return /^VFN-[BTE][A-Z0-9]+$/.test(code) ? code : null;
};

// The camera and the result panel never show at once - a booking found by
// either the scanner or the manual field freezes the camera (not stopping
// it, so the stream is still warm and `start()` on the way back is instant)
// until the operator explicitly returns to scanning.
const lookupByCode = async (code) => {
    lookupError.value = '';
    booking.value = null;
    try {
        booking.value = await themeParkStore.lookupTicketByCode(code);
        scanner.value?.pause();
    } catch {
        lookupError.value = 'Booking not found.';
    }
};

const lookupManually = () => {
    const code = parseTicketCode(bookingIdInput.value);
    if (!code) {
        lookupError.value = 'Booking not found.';
        return;
    }
    lookupByCode(code);
};

const onDecode = async (data) => {
    if (scanLocked) return;
    const code = parseTicketCode(data);
    if (!code) return;
    scanLocked = true;
    await lookupByCode(code);
    scanLocked = false;
};

const backToScanning = () => {
    booking.value = null;
    lookupError.value = '';
    bookingIdInput.value = '';
    scanner.value?.resume();
};

const confirmUsed = async () => {
    try {
        booking.value = await themeParkStore.validateTicket(booking.value.id);
    } catch {
        lookupError.value = 'This ticket has already been used.';
    }
};

const cancelBooking = async () => {
    try {
        booking.value = await themeParkStore.cancelBooking(booking.value.id);
    } catch {
        lookupError.value = 'This booking could not be cancelled.';
    }
};

const isActionable = computed(() => booking.value?.status === 'confirmed');

const statusLabel = computed(() => {
    if (!booking.value) return '';
    if (booking.value.status === 'used') return 'Already Used';
    if (booking.value.status === 'cancelled') return 'Cancelled';
    return 'Valid Ticket';
});

const panelClasses = computed(() => {
    if (!booking.value) return '';
    if (booking.value.status === 'used') return 'bg-danger-soft';
    if (booking.value.status === 'cancelled') return 'bg-surface-hover';
    return 'bg-success-soft';
});

const headingClasses = computed(() => {
    if (!booking.value) return '';
    if (booking.value.status === 'used') return 'text-danger';
    if (booking.value.status === 'cancelled') return 'text-foreground-secondary';
    return 'text-success';
});
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Ticket Validation" icon="scan" />
        </template>

        <!-- Deliberately a narrow, centred column: this is a one-thing-at-a-time
             screen used at the gate, not a management table. -->
        <div class="mx-auto max-w-xl space-y-5">
            <div v-show="!booking" class="elevated rounded-xl border bg-surface p-4">
                <QrCameraScanner ref="scanner" @decode="onDecode" />
                <form @submit.prevent="lookupManually" class="mt-3 flex items-start gap-2">
                    <div class="flex-1">
                        <TInput v-model="bookingIdInput" placeholder="Or enter booking ID" />
                    </div>
                    <TButton type="submit">
                        <TIcon name="search" :size="16" />
                        Look Up
                    </TButton>
                </form>
            </div>

            <div v-if="lookupError" class="rounded-xl border bg-danger-soft p-4 text-sm text-danger">
                {{ lookupError }}
            </div>

            <div v-if="booking" class="elevated rounded-xl border p-6" :class="panelClasses">
                <p class="font-semibold" :class="headingClasses">
                    {{ statusLabel }}
                </p>
                <dl class="mt-3 space-y-1 text-sm text-foreground">
                    <div class="flex justify-between">
                        <dt class="text-foreground-muted">Visitor</dt>
                        <dd>{{ booking.user?.name ?? booking.visitor_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-foreground-muted">Event</dt>
                        <dd>{{ booking.slot?.event?.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-foreground-muted">Slot Time</dt>
                        <dd>{{ formatDateTime(booking.slot?.slot_date, booking.slot?.slot_time) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-foreground-muted">Tickets</dt>
                        <dd>{{ booking.ticket_count }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <TButton v-if="isActionable" @click="confirmUsed">Confirm Used</TButton>
                    <TButton v-if="isActionable" variant="danger" @click="cancelBooking">Cancel Ticket</TButton>
                    <TButton variant="secondary" type="button" @click="backToScanning">Scan Next</TButton>
                </div>
            </div>
        </div>
    </StaffLayout>
</template>
