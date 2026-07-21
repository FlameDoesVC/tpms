<script setup>
import { onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Walk-in Ticket Sales
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg space-y-4 sm:px-6 lg:px-8">
                <form @submit.prevent="sell" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <InputLabel for="event" value="Event" />
                        <select id="event" v-model="eventId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option v-for="event in themeParkStore.events" :key="event.id" :value="event.id">
                                {{ event.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="date" value="Date" />
                        <TextInput id="date" type="date" v-model="date" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <InputLabel for="slot" value="Slot" />
                        <select id="slot" v-model="slotId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option v-for="slot in themeParkStore.slots" :key="slot.id" :value="slot.id" :disabled="slot.available_capacity < 1">
                                {{ slot.slot_time }} ({{ slot.available_capacity }} left)
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="ticket_count" value="Ticket Count" />
                        <TextInput id="ticket_count" type="number" min="1" v-model.number="ticketCount" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <InputLabel for="visitor_name" value="Visitor Name (optional)" />
                        <TextInput id="visitor_name" v-model="visitorName" class="mt-1 block w-full" />
                    </div>

                    <p v-if="saleError" class="text-sm text-red-600">{{ saleError }}</p>

                    <PrimaryButton type="submit" :disabled="!slotId">Sell Ticket</PrimaryButton>
                </form>
            </div>
        </div>

        <Modal :show="!!receipt" @close="closeReceipt">
            <div v-if="receipt" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Ticket Sold</h2>
                <dl class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Reference</dt>
                        <dd class="font-mono">#{{ receipt.id }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Visitor</dt>
                        <dd>{{ receipt.visitor_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Tickets</dt>
                        <dd>{{ receipt.ticket_count }}</dd>
                    </div>
                </dl>
                <div class="mt-6 flex justify-end gap-3">
                    <PrimaryButton @click="print">Print</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
