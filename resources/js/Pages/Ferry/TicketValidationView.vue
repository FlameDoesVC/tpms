<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import QrCameraScanner from '@/Components/QrCameraScanner.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const ticketIdInput = ref('');
const ticket = ref(null);
const lookupError = ref('');
const scanner = ref(null);
let scanLocked = false;

const parseTicketId = (raw) => {
    const match = raw.trim().match(/(\d+)\s*$/);
    return match ? parseInt(match[1], 10) : null;
};

// The camera and the result panel never show at once - a ticket found by
// either the scanner or the manual field freezes the camera (not stopping
// it, so the stream is still warm and `start()` on the way back is instant)
// until the operator explicitly returns to scanning.
const lookupById = async (id) => {
    lookupError.value = '';
    ticket.value = null;
    try {
        ticket.value = await ferryStore.lookupTicket(id);
        scanner.value?.pause();
    } catch {
        lookupError.value = 'Ticket not found.';
    }
};

const lookupManually = () => {
    const id = parseTicketId(ticketIdInput.value);
    if (!id) {
        lookupError.value = 'Ticket not found.';
        return;
    }
    lookupById(id);
};

const onDecode = async (data) => {
    if (scanLocked) return;
    const id = parseTicketId(data);
    if (!id) return;
    scanLocked = true;
    await lookupById(id);
    scanLocked = false;
};

const backToScanning = () => {
    ticket.value = null;
    lookupError.value = '';
    ticketIdInput.value = '';
    scanner.value?.resume();
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
    } catch {
        lookupError.value = 'This ticket could not be cancelled.';
    }
};

const isActionable = computed(() => ticket.value && ticket.value.status !== 'used' && ticket.value.status !== 'cancelled');

const statusLabel = computed(() => {
    if (!ticket.value) return '';
    if (ticket.value.status === 'used') return 'Already Used';
    if (ticket.value.status === 'cancelled') return 'Cancelled';
    if (ticket.value.payment_method === 'cash') return 'Valid Ticket - Collect Cash';
    return 'Valid Ticket';
});

const panelClasses = computed(() => {
    if (!ticket.value) return '';
    if (ticket.value.status === 'used') return 'bg-red-50';
    if (ticket.value.status === 'cancelled') return 'bg-gray-100';
    if (ticket.value.payment_method === 'cash') return 'bg-yellow-50';
    return 'bg-green-50';
});

const headingClasses = computed(() => {
    if (!ticket.value) return '';
    if (ticket.value.status === 'used') return 'text-red-800';
    if (ticket.value.status === 'cancelled') return 'text-gray-600';
    if (ticket.value.payment_method === 'cash') return 'text-yellow-900';
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
                <div v-show="!ticket" class="rounded-lg bg-white p-4 shadow-sm">
                    <QrCameraScanner ref="scanner" @decode="onDecode" />
                    <form @submit.prevent="lookupManually" class="mt-3 flex gap-2">
                        <input
                            v-model="ticketIdInput"
                            placeholder="Or enter ticket ID / LSJ-T0012"
                            class="flex-1 rounded-md border-gray-300 text-sm shadow-sm"
                        />
                        <PrimaryButton type="submit">Look Up</PrimaryButton>
                    </form>
                </div>

                <div v-if="lookupError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    {{ lookupError }}
                </div>

                <div v-if="ticket" class="rounded-lg p-6" :class="panelClasses">
                    <p class="font-semibold" :class="headingClasses">
                        {{ statusLabel }} - {{ ticket.reference_code }}
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
