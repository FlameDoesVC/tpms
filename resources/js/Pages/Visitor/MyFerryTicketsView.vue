<script setup>
import { onMounted, reactive } from 'vue';
import QRCode from 'qrcode';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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
            <TPageHeader title="My Ferry Tickets" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="ferryStore.loading.tickets" class="text-foreground-muted">Loading tickets...</div>
                <TEmptyState
                    v-else-if="ferryStore.myTickets.length === 0"
                    title="No ferry tickets yet"
                    description="Tickets you book for the crossing will appear here."
                    icon="ship"
                />

                <!-- Boarding-pass form: notched edges and a perforated seam
                     between the stub (QR + route) and the fare details. -->
                <div
                    v-for="ticket in ferryStore.myTickets"
                    :key="ticket.id"
                    class="ticket-edge elevated relative flex flex-col gap-4 rounded-xl border bg-surface p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div v-if="revealedIds.has(ticket.id) && qrCodes[ticket.id]" class="flex flex-col items-center gap-1">
                            <img :src="qrCodes[ticket.id]" alt="Ticket QR code" class="h-20 w-20" />
                            <p class="font-mono text-xs text-foreground-muted">{{ ticket.reference_code }}</p>
                        </div>
                        <button
                            v-else
                            type="button"
                            @click="revealQr(ticket)"
                            class="flex h-20 w-20 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-strong bg-surface-hover text-foreground-muted transition-colors hover:border-primary hover:text-primary"
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
                            <p class="font-semibold text-foreground">{{ ticket.schedule?.ferry?.name }}</p>
                            <p class="text-sm text-foreground-muted">
                                {{ ticket.schedule?.departure_date?.slice(0, 10) }} at {{ ticket.schedule?.departure_time }}
                            </p>
                            <p class="text-sm text-foreground-muted">Seat {{ ticket.seat_number }}</p>
                        </div>
                    </div>
                    <span class="perforation sm:hidden" aria-hidden="true" />
                    <span class="perforation-y hidden sm:block" aria-hidden="true" />

                    <div class="flex flex-col items-end gap-2 sm:min-w-[13rem]">
                        <TBadge
                            :variant="ticket.status === 'pending' ? 'warning' : ticket.status === 'issued' ? 'success' : 'neutral'"
                        >
                            {{ ticket.status }}
                        </TBadge>
                        <span class="text-xs text-foreground-muted">
                            ${{ ticket.price }} - {{ ticket.payment_method === 'cash' ? 'pay cash on board' : 'paid online' }}
                        </span>
                        <button
                            @click="downloadTicket(ticket)"
                            class="text-sm text-primary hover:underline"
                        >
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
