<script setup>
import { onMounted, reactive } from 'vue';
import QRCode from 'qrcode';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const qrCodes = reactive({});

onMounted(async () => {
    await ferryStore.fetchMyTickets();
    for (const ticket of ferryStore.myTickets) {
        qrCodes[ticket.id] = await QRCode.toDataURL(String(ticket.id));
    }
});

const downloadTicket = (ticket) => {
    const link = document.createElement('a');
    link.href = qrCodes[ticket.id];
    link.download = `ferry-ticket-${ticket.id}.png`;
    link.click();
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                My Ferry Tickets
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="ferryStore.loading.tickets" class="text-gray-500">Loading tickets...</div>
                <div v-else-if="ferryStore.myTickets.length === 0" class="text-gray-500">
                    You have no ferry tickets yet.
                </div>

                <div
                    v-for="ticket in ferryStore.myTickets"
                    :key="ticket.id"
                    class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-4">
                        <img v-if="qrCodes[ticket.id]" :src="qrCodes[ticket.id]" alt="Ticket QR code" class="h-20 w-20" />
                        <div>
                            <p class="font-semibold text-gray-900">{{ ticket.schedule?.ferry?.name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ ticket.schedule?.departure_date }} at {{ ticket.schedule?.departure_time }}
                            </p>
                            <p class="text-sm text-gray-500">Seat {{ ticket.seat_number }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-yellow-100 text-yellow-800': ticket.status === 'pending',
                                'bg-green-100 text-green-800': ticket.status === 'issued',
                                'bg-gray-100 text-gray-600': ticket.status === 'used',
                            }"
                        >
                            {{ ticket.status }}
                        </span>
                        <button
                            @click="downloadTicket(ticket)"
                            class="text-sm text-indigo-600 hover:underline"
                        >
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
