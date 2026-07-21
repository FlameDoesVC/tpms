<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const ticketId = ref('');
const ticket = ref(null);
const lookupError = ref('');

const lookup = async () => {
    lookupError.value = '';
    ticket.value = null;
    try {
        ticket.value = await ferryStore.lookupTicket(ticketId.value);
    } catch {
        lookupError.value = 'Ticket not found.';
    }
};

const confirmUsed = async () => {
    try {
        ticket.value = await ferryStore.validateTicketOnSite(ticket.value.id);
    } catch {
        lookupError.value = 'This ticket has already been used.';
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
                <form @submit.prevent="lookup" class="flex gap-2 rounded-lg bg-white p-4 shadow-sm">
                    <input
                        v-model="ticketId"
                        placeholder="Ticket ID or scan QR"
                        class="flex-1 rounded-md border-gray-300 shadow-sm"
                    />
                    <PrimaryButton type="submit">Look Up</PrimaryButton>
                </form>

                <div v-if="lookupError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    {{ lookupError }}
                </div>

                <div v-if="ticket" class="rounded-lg p-6" :class="ticket.status === 'used' ? 'bg-red-50' : 'bg-green-50'">
                    <p class="font-semibold" :class="ticket.status === 'used' ? 'text-red-800' : 'text-green-800'">
                        {{ ticket.status === 'used' ? 'Already Used' : 'Valid Ticket' }}
                    </p>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Passenger</dt>
                            <dd>{{ ticket.user?.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Hotel booking ref</dt>
                            <dd>#{{ ticket.booking?.id }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Departure</dt>
                            <dd>{{ ticket.schedule?.departure_date }} {{ ticket.schedule?.departure_time }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Seat</dt>
                            <dd>{{ ticket.seat_number }}</dd>
                        </div>
                    </dl>

                    <PrimaryButton
                        v-if="ticket.status !== 'used'"
                        class="mt-4"
                        @click="confirmUsed"
                    >
                        Confirm Used
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
