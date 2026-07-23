<script setup>
import { onMounted, reactive } from 'vue';
import QRCode from 'qrcode';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const qrCodes = reactive({});
const revealedIds = reactive(new Set());

onMounted(() => {
    ferryStore.fetchMyTickets();
});

// The real QR code isn't rendered until the visitor asks for it - only a
// placeholder shows by default, generated lazily on first reveal (not
// upfront for every ticket) since most tickets are never actually revealed.
const revealQr = async (ticket) => {
    if (!qrCodes[ticket.id]) {
        qrCodes[ticket.id] = await QRCode.toDataURL(ticket.reference_code);
    }
    revealedIds.add(ticket.id);
};

// The downloaded image is a separate composition from the on-screen QR - it
// draws the same QR code onto a canvas with the reference code baked in as
// text underneath, since a plain HTML <p> next to the <img> wouldn't survive
// being saved as a standalone PNG.
const buildDownloadableQr = async (data) => {
    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, data, { margin: 1 });

    const fontSize = 16;
    const padding = 10;
    const canvas = document.createElement('canvas');
    canvas.width = qrCanvas.width;
    canvas.height = qrCanvas.height + fontSize + padding * 2;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(qrCanvas, 0, 0);
    ctx.fillStyle = '#000000';
    ctx.font = `${fontSize}px monospace`;
    ctx.textAlign = 'center';
    ctx.fillText(data, canvas.width / 2, qrCanvas.height + fontSize + padding / 2);

    return canvas.toDataURL('image/png');
};

const downloadTicket = async (ticket) => {
    const link = document.createElement('a');
    link.href = await buildDownloadableQr(ticket.reference_code);
    link.download = `${ticket.reference_code}.png`;
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
                        <div v-if="revealedIds.has(ticket.id) && qrCodes[ticket.id]" class="flex flex-col items-center gap-1">
                            <img :src="qrCodes[ticket.id]" alt="Ticket QR code" class="h-20 w-20" />
                            <p class="font-mono text-xs text-gray-500">{{ ticket.reference_code }}</p>
                        </div>
                        <button
                            v-else
                            type="button"
                            @click="revealQr(ticket)"
                            class="flex h-20 w-20 flex-col items-center justify-center gap-1 rounded-md border border-dashed border-gray-300 bg-gray-50 text-gray-400 hover:border-indigo-400 hover:text-indigo-500"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="3" width="7" height="7" rx="1" />
                                <rect x="3" y="14" width="7" height="7" rx="1" />
                                <path stroke-linecap="round" d="M14 14h3m4 0h.01M14 18h.01M18 18h3M14 21h7" />
                            </svg>
                            <span class="text-xs font-medium">Show QR</span>
                        </button>
                        <div>
                            <p class="font-semibold text-gray-900">{{ ticket.schedule?.ferry?.name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ ticket.schedule?.departure_date?.slice(0, 10) }} at {{ ticket.schedule?.departure_time }}
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
                        <span class="text-xs text-gray-500">
                            ${{ ticket.price }} - {{ ticket.payment_method === 'cash' ? 'pay cash on board' : 'paid online' }}
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
