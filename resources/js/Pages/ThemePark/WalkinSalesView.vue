<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TCard from '@/Components/ui/TCard.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const eventId = ref(null);
const date = ref(new Date().toISOString().slice(0, 10));
const slotId = ref(null);
const ticketCount = ref(1);
const visitorName = ref('');
const saleError = ref('');
const receipt = ref(null);

onMounted(async () => {
    await themeParkStore.fetchEvents();
    if (themeParkStore.events.length) eventId.value = themeParkStore.events[0].id;
});

watch([eventId, date], () => {
    if (eventId.value) themeParkStore.fetchSlotsForDate(eventId.value, date.value);
});

const sell = async () => {
    saleError.value = '';
    try {
        receipt.value = await themeParkStore.sellTicket({
            event_slot_id: slotId.value,
            ticket_count: ticketCount.value,
            visitor_name: visitorName.value || undefined,
        });
        themeParkStore.fetchSlotsForDate(eventId.value, date.value);
    } catch {
        saleError.value = 'Not enough capacity left for this slot.';
    }
};

const closeReceipt = () => (receipt.value = null);
const print = () => window.print();

const eventOptions = computed(() =>
    themeParkStore.events.map((event) => ({ value: event.id, label: event.name }))
);

// Sold-out slots stay visible but unselectable, so staff can see the time is
// on the schedule rather than wondering why it vanished.
const slotOptions = computed(() =>
    themeParkStore.slots.map((slot) => ({
        value: slot.id,
        label: `${slot.slot_time} (${slot.available_capacity} left)`,
        disabled: slot.available_capacity < 1,
    }))
);

const showReceipt = computed({
    get: () => !!receipt.value,
    set: (value) => {
        if (!value) receipt.value = null;
    },
});
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Walk-in Ticket Sales" icon="ticket" />
        </template>

        <div class="mx-auto max-w-lg">
            <TCard icon="cart" title="New Sale">
                <form @submit.prevent="sell" class="space-y-4">
                    <TSelect v-model="eventId" label="Event" :options="eventOptions" />

                    <TInput v-model="date" label="Date" type="date" />

                    <TSelect v-model="slotId" label="Slot" :options="slotOptions" />

                    <TInput v-model.number="ticketCount" label="Ticket Count" type="number" min="1" />

                    <TInput v-model="visitorName" label="Visitor Name (optional)" />

                    <p v-if="saleError" class="text-sm text-danger">{{ saleError }}</p>

                    <TButton type="submit" :disabled="!slotId">
                        <TIcon name="cart" :size="16" />
                        Sell Ticket
                    </TButton>
                </form>
            </TCard>
        </div>

        <TModal v-model:show="showReceipt" @close="closeReceipt">
            <template #title>Ticket Sold</template>

            <dl v-if="receipt" class="space-y-1 text-sm text-foreground">
                <div class="flex justify-between">
                    <dt class="text-foreground-muted">Reference</dt>
                    <dd class="font-mono">#{{ receipt.id }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-foreground-muted">Visitor</dt>
                    <dd>{{ receipt.visitor_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-foreground-muted">Tickets</dt>
                    <dd>{{ receipt.ticket_count }}</dd>
                </div>
            </dl>

            <template #footer>
                <TButton @click="print">Print</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
