<script setup>
import { onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const showModal = ref(false);
const errors = ref({});

const emptyForm = () => ({ ferry_id: '', departure_date: '', departure_time: '', arrival_time: '' });
const form = ref(emptyForm());

onMounted(() => {
    ferryStore.fetchFerries();
    ferryStore.fetchSchedules();
});

const openAddModal = () => {
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const save = async () => {
    errors.value = {};
    try {
        await ferryStore.createSchedule(form.value);
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const cancelSchedule = (schedule) => {
    if (confirm('Cancel this departure?')) {
        ferryStore.cancelSchedule(schedule.id);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ferry Schedule Management
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <PrimaryButton @click="openAddModal">Add Schedule</PrimaryButton>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Ferry</th>
                                <th class="p-4">Date</th>
                                <th class="p-4">Departure</th>
                                <th class="p-4">Arrival</th>
                                <th class="p-4">Capacity</th>
                                <th class="p-4">Booked</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="schedule in ferryStore.schedules" :key="schedule.id">
                                <td class="p-4">{{ schedule.ferry?.name }}</td>
                                <td class="p-4">{{ schedule.departure_date?.slice(0, 10) }}</td>
                                <td class="p-4">{{ schedule.departure_time }}</td>
                                <td class="p-4">{{ schedule.arrival_time }}</td>
                                <td class="p-4">{{ schedule.ferry?.capacity }}</td>
                                <td class="p-4">{{ schedule.ferry?.capacity - schedule.available_seats }}</td>
                                <td class="p-4 capitalize">{{ schedule.status }}</td>
                                <td class="p-4">
                                    <button
                                        v-if="schedule.status === 'scheduled'"
                                        @click="cancelSchedule(schedule)"
                                        class="text-sm text-red-600 hover:underline"
                                    >
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <form @submit.prevent="save" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Add Schedule</h2>

                <div class="mt-4">
                    <InputLabel for="ferry_id" value="Ferry" />
                    <select id="ferry_id" v-model="form.ferry_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option v-for="ferry in ferryStore.ferries" :key="ferry.id" :value="ferry.id">
                            {{ ferry.name }}
                        </option>
                    </select>
                    <InputError :message="errors.ferry_id?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="departure_date" value="Date" />
                    <TextInput id="departure_date" type="date" v-model="form.departure_date" class="mt-1 block w-full" />
                    <InputError :message="errors.departure_date?.[0]" class="mt-2" />
                </div>

                <div class="mt-4 flex gap-4">
                    <div class="flex-1">
                        <InputLabel for="departure_time" value="Departure" />
                        <TextInput id="departure_time" type="time" v-model="form.departure_time" class="mt-1 block w-full" />
                        <InputError :message="errors.departure_time?.[0]" class="mt-2" />
                    </div>
                    <div class="flex-1">
                        <InputLabel for="arrival_time" value="Arrival" />
                        <TextInput id="arrival_time" type="time" v-model="form.arrival_time" class="mt-1 block w-full" />
                        <InputError :message="errors.arrival_time?.[0]" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
