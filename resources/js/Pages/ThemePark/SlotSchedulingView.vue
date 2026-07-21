<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const selectedEventId = ref(null);
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const showModal = ref(false);

const selectedEvent = computed(() =>
    themeParkStore.events.find((e) => e.id === selectedEventId.value)
);

const emptyForm = () => ({ startDate: selectedDate.value, endDate: selectedDate.value, times: ['10:00'], capacity: '' });
const form = ref(emptyForm());

onMounted(async () => {
    await themeParkStore.fetchEvents();
    if (themeParkStore.events.length) selectedEventId.value = themeParkStore.events[0].id;
});

const loadSlots = () => {
    if (selectedEventId.value) themeParkStore.fetchSlotsForDate(selectedEventId.value, selectedDate.value);
};

watch([selectedEventId, selectedDate], loadSlots);

const openModal = () => {
    form.value = emptyForm();
    showModal.value = true;
};
const closeModal = () => (showModal.value = false);

const addTimeRow = () => form.value.times.push('10:00');
const removeTimeRow = (index) => form.value.times.splice(index, 1);

const dateRange = (start, end) => {
    const dates = [];
    let cursor = new Date(start);
    const last = new Date(end);
    while (cursor <= last) {
        dates.push(cursor.toISOString().slice(0, 10));
        cursor.setDate(cursor.getDate() + 1);
    }
    return dates;
};

const save = async () => {
    const dates = dateRange(form.value.startDate, form.value.endDate);
    for (const date of dates) {
        for (const time of form.value.times) {
            await themeParkStore.createSlot(selectedEventId.value, {
                slot_date: date,
                slot_time: time,
                capacity: form.value.capacity || undefined,
            });
        }
    }
    closeModal();
    loadSlots();
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Slot Scheduling
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Event</label>
                        <select v-model="selectedEventId" class="mt-1 rounded-md border-gray-300 shadow-sm">
                            <option v-for="event in themeParkStore.events" :key="event.id" :value="event.id">
                                {{ event.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Day</label>
                        <input type="date" v-model="selectedDate" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <PrimaryButton :disabled="!selectedEventId" @click="openModal">Add Slots</PrimaryButton>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Time</th>
                                <th class="p-4">Booked</th>
                                <th class="p-4">Capacity</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="slot in themeParkStore.slots" :key="slot.id">
                                <td class="p-4">{{ slot.slot_time }}</td>
                                <td class="p-4">
                                    {{ (selectedEvent?.capacity_per_slot ?? 0) - slot.available_capacity }}
                                </td>
                                <td class="p-4">{{ selectedEvent?.capacity_per_slot }}</td>
                                <td class="p-4 capitalize">{{ slot.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="themeParkStore.slots.length === 0" class="p-4 text-sm text-gray-500">
                        No slots scheduled for this day.
                    </p>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <form @submit.prevent="save" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add Slots</h2>

                <div class="mt-4 flex gap-4">
                    <div class="flex-1">
                        <InputLabel for="start_date" value="From" />
                        <TextInput id="start_date" type="date" v-model="form.startDate" class="mt-1 block w-full" />
                    </div>
                    <div class="flex-1">
                        <InputLabel for="end_date" value="To" />
                        <TextInput id="end_date" type="date" v-model="form.endDate" class="mt-1 block w-full" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel value="Times" />
                    <div v-for="(time, index) in form.times" :key="index" class="mt-2 flex gap-2">
                        <TextInput type="time" v-model="form.times[index]" class="block w-full" />
                        <SecondaryButton type="button" @click="removeTimeRow(index)">Remove</SecondaryButton>
                    </div>
                    <button type="button" @click="addTimeRow" class="mt-2 text-sm text-indigo-600 hover:underline">
                        + Add time
                    </button>
                </div>

                <div class="mt-4">
                    <InputLabel for="capacity" value="Capacity Override (optional)" />
                    <TextInput id="capacity" type="number" min="1" v-model="form.capacity" class="mt-1 block w-full" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
