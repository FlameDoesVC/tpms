<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import QrCameraScanner from '@/Components/QrCameraScanner.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const bookingIdInput = ref('');
const booking = ref(null);
const lookupError = ref('');
const scanner = ref(null);
let scanLocked = false;

const parseBookingId = (raw) => {
    const match = raw.trim().match(/(\d+)\s*$/);
    return match ? parseInt(match[1], 10) : null;
};

// The camera and the result panel never show at once - a booking found by
// either the scanner or the manual field freezes the camera (not stopping
// it, so the stream is still warm and `start()` on the way back is instant)
// until the operator explicitly returns to scanning.
const lookupById = async (id) => {
    lookupError.value = '';
    booking.value = null;
    try {
        booking.value = await themeParkStore.lookupTicket(id);
        scanner.value?.pause();
    } catch {
        lookupError.value = 'Booking not found.';
    }
};

const lookupManually = () => {
    const id = parseBookingId(bookingIdInput.value);
    if (!id) {
        lookupError.value = 'Booking not found.';
        return;
    }
    lookupById(id);
};

const onDecode = async (data) => {
    if (scanLocked) return;
    const id = parseBookingId(data);
    if (!id) return;
    scanLocked = true;
    await lookupById(id);
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
    if (booking.value.status === 'used') return 'bg-red-50';
    if (booking.value.status === 'cancelled') return 'bg-gray-100';
    return 'bg-green-50';
});

const headingClasses = computed(() => {
    if (!booking.value) return '';
    if (booking.value.status === 'used') return 'text-red-800';
    if (booking.value.status === 'cancelled') return 'text-gray-600';
    return 'text-green-800';
});
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
                <div v-show="!booking" class="rounded-lg bg-white p-4 shadow-sm">
                    <QrCameraScanner ref="scanner" @decode="onDecode" />
                    <form @submit.prevent="lookupManually" class="mt-3 flex gap-2">
                        <input
                            v-model="bookingIdInput"
                            placeholder="Or enter booking ID"
                            class="flex-1 rounded-md border-gray-300 text-sm shadow-sm"
                        />
                        <PrimaryButton type="submit">Look Up</PrimaryButton>
                    </form>
                </div>

                <div v-if="lookupError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    {{ lookupError }}
                </div>

                <div v-if="booking" class="rounded-lg p-6" :class="panelClasses">
                    <p class="font-semibold" :class="headingClasses">
                        {{ statusLabel }}
                    </p>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Visitor</dt>
                            <dd>{{ booking.user?.name ?? booking.visitor_name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Event</dt>
                            <dd>{{ booking.slot?.event?.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Slot Time</dt>
                            <dd>{{ booking.slot?.slot_date?.slice(0, 10) }} {{ booking.slot?.slot_time }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Tickets</dt>
                            <dd>{{ booking.ticket_count }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <PrimaryButton v-if="isActionable" @click="confirmUsed">Confirm Used</PrimaryButton>
                        <DangerButton v-if="isActionable" @click="cancelBooking">Cancel Ticket</DangerButton>
                        <button
                            type="button"
                            @click="backToScanning"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Scan Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
