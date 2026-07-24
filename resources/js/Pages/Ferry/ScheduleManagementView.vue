<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import MonthCalendar from '@/Components/MonthCalendar.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const showModal = ref(false);
const errors = ref({});
const scheduleMode = ref('oneoff');
const calendarMonth = ref(new Date().toISOString().slice(0, 10));

// Fixed hue order, assigned by ferry id (not list position) so a ferry's
// color stays stable as other ferries are added/removed.
const FERRY_COLORS = [
    { bg: 'bg-blue-100', text: 'text-blue-800', dot: 'bg-blue-500' },
    { bg: 'bg-orange-100', text: 'text-orange-800', dot: 'bg-orange-500' },
    { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500' },
    { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500' },
    { bg: 'bg-pink-100', text: 'text-pink-800', dot: 'bg-pink-500' },
    { bg: 'bg-violet-100', text: 'text-violet-800', dot: 'bg-violet-500' },
    { bg: 'bg-red-100', text: 'text-red-800', dot: 'bg-red-500' },
    { bg: 'bg-teal-100', text: 'text-teal-800', dot: 'bg-teal-500' },
];

const ferryColor = (ferryId) => FERRY_COLORS[ferryId % FERRY_COLORS.length];

const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const emptyOneOffForm = () => ({ ferry_id: '', departure_date: '', departure_time: '', arrival_time: '' });
const emptyRecurringForm = () => ({
    ferry_id: '',
    frequency: 'daily',
    weekdays: [],
    day_of_month: '',
    departure_time: '',
    arrival_time: '',
    available_seats: '',
    starts_on: '',
    ends_on: '',
});

const form = ref(emptyOneOffForm());
const recurringForm = ref(emptyRecurringForm());
const editingTemplateId = ref(null);

onMounted(() => {
    ferryStore.fetchFerries();
    ferryStore.fetchSchedules();
    ferryStore.fetchTemplates();
});

const openAddModal = () => {
    scheduleMode.value = 'oneoff';
    form.value = emptyOneOffForm();
    recurringForm.value = emptyRecurringForm();
    editingTemplateId.value = null;
    errors.value = {};
    showModal.value = true;
};

const openEditTemplateModal = (template) => {
    scheduleMode.value = 'recurring';
    editingTemplateId.value = template.id;
    recurringForm.value = {
        ferry_id: template.ferry_id,
        frequency: template.frequency,
        weekdays: template.weekdays ?? [],
        day_of_month: template.day_of_month ?? '',
        departure_time: template.departure_time?.slice(0, 5) ?? '',
        arrival_time: template.arrival_time?.slice(0, 5) ?? '',
        available_seats: template.available_seats,
        starts_on: template.starts_on?.slice(0, 10) ?? '',
        ends_on: template.ends_on?.slice(0, 10) ?? '',
    };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const save = async () => {
    errors.value = {};
    try {
        if (scheduleMode.value === 'oneoff') {
            await ferryStore.createSchedule(form.value);
        } else if (editingTemplateId.value) {
            await ferryStore.updateTemplate(editingTemplateId.value, recurringForm.value);
        } else {
            await ferryStore.createTemplate(recurringForm.value);
        }
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

const uncancelSchedule = (schedule) => {
    if (confirm('Restore this departure to scheduled?')) {
        ferryStore.uncancelSchedule(schedule.id);
    }
};

const toggleSchedule = (schedule) => {
    if (schedule.status === 'scheduled') cancelSchedule(schedule);
    else if (schedule.status === 'cancelled') uncancelSchedule(schedule);
};

const stopTemplate = (template) => {
    if (confirm('Stop this recurring schedule? Already-generated departures are kept.')) {
        ferryStore.stopTemplate(template.id);
    }
};

const patternSummary = (template) => {
    if (template.frequency === 'daily') return `Daily, ${template.departure_time?.slice(0, 5)}`;
    if (template.frequency === 'weekly') {
        const labels = (template.weekdays ?? [])
            .map((v) => WEEKDAY_OPTIONS.find((o) => o.value === v)?.label)
            .join('/');
        return `Weekly: ${labels}, ${template.departure_time?.slice(0, 5)}`;
    }
    return `Monthly: day ${template.day_of_month}, ${template.departure_time?.slice(0, 5)}`;
};

const calendarItems = computed(() => ferryStore.schedules.map((s) => ({
    ...s,
    date: s.departure_date?.slice(0, 10),
})));
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
                <div class="flex items-center justify-end">
                    <PrimaryButton @click="openAddModal">Add Schedule</PrimaryButton>
                </div>

                <MonthCalendar v-model="calendarMonth" :items="calendarItems">
                    <template #legend>
                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs text-gray-600">
                            <span v-for="ferry in ferryStore.ferries" :key="ferry.id" class="flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full" :class="ferryColor(ferry.id).dot" />
                                {{ ferry.name }}
                            </span>
                        </div>
                    </template>
                    <template #day="{ items }">
                        <div class="space-y-1">
                            <button
                                v-for="item in items"
                                :key="item.id"
                                type="button"
                                class="block w-full rounded px-1.5 py-0.5 text-left text-xs"
                                :class="[
                                    ferryColor(item.ferry_id).bg,
                                    ferryColor(item.ferry_id).text,
                                    item.status !== 'scheduled' && 'opacity-50',
                                    item.status === 'cancelled' && 'line-through',
                                ]"
                                :title="item.status === 'cancelled' ? `${item.ferry?.name} - cancelled (click to restore)` : `${item.ferry?.name} - ${item.status}`"
                                @click="toggleSchedule(item)"
                            >
                                {{ item.departure_time?.slice(0, 5) }} ({{ item.ferry?.capacity - item.available_seats }}/{{ item.ferry?.capacity }})
                                <span v-if="item.is_overridden">⚠</span>
                            </button>
                        </div>
                    </template>
                </MonthCalendar>

                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700">Recurring Schedules</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Ferry</th>
                                <th class="p-4">Pattern</th>
                                <th class="p-4">Range</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="template in ferryStore.templates" :key="template.id">
                                <td class="p-4">{{ template.ferry?.name }}</td>
                                <td class="p-4">{{ patternSummary(template) }}</td>
                                <td class="p-4">{{ template.starts_on?.slice(0, 10) }} - {{ template.ends_on?.slice(0, 10) ?? 'ongoing' }}</td>
                                <td class="p-4">{{ template.is_active ? 'Yes' : 'Stopped' }}</td>
                                <td class="p-4 space-x-3">
                                    <button v-if="template.is_active" class="text-sm text-indigo-600 hover:underline" @click="openEditTemplateModal(template)">Edit</button>
                                    <button v-if="template.is_active" class="text-sm text-red-600 hover:underline" @click="stopTemplate(template)">Stop</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <form @submit.prevent="save" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingTemplateId ? 'Edit Recurring Schedule' : 'Add Schedule' }}
                </h2>

                <div v-if="!editingTemplateId" class="mt-4 flex gap-2 rounded-md bg-gray-100 p-1 text-sm">
                    <button
                        type="button"
                        class="flex-1 rounded px-3 py-1"
                        :class="scheduleMode === 'oneoff' ? 'bg-white shadow-sm' : 'text-gray-500'"
                        @click="scheduleMode = 'oneoff'"
                    >
                        One-off
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded px-3 py-1"
                        :class="scheduleMode === 'recurring' ? 'bg-white shadow-sm' : 'text-gray-500'"
                        @click="scheduleMode = 'recurring'"
                    >
                        Recurring
                    </button>
                </div>

                <template v-if="scheduleMode === 'oneoff'">
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
                </template>

                <template v-else>
                    <div class="mt-4">
                        <InputLabel for="r_ferry_id" value="Ferry" />
                        <select id="r_ferry_id" v-model="recurringForm.ferry_id" :disabled="!!editingTemplateId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option v-for="ferry in ferryStore.ferries" :key="ferry.id" :value="ferry.id">
                                {{ ferry.name }}
                            </option>
                        </select>
                        <InputError :message="errors.ferry_id?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="frequency" value="Frequency" />
                        <select id="frequency" v-model="recurringForm.frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        <InputError :message="errors.frequency?.[0]" class="mt-2" />
                    </div>

                    <div v-if="recurringForm.frequency === 'weekly'" class="mt-4">
                        <InputLabel value="Weekdays" />
                        <div class="mt-1 flex flex-wrap gap-3">
                            <label v-for="option in WEEKDAY_OPTIONS" :key="option.value" class="flex items-center gap-1 text-sm">
                                <input type="checkbox" :value="option.value" v-model="recurringForm.weekdays" />
                                {{ option.label }}
                            </label>
                        </div>
                        <InputError :message="errors.weekdays?.[0]" class="mt-2" />
                    </div>

                    <div v-if="recurringForm.frequency === 'monthly'" class="mt-4">
                        <InputLabel for="day_of_month" value="Day of month" />
                        <TextInput id="day_of_month" type="number" min="1" max="31" v-model="recurringForm.day_of_month" class="mt-1 block w-full" />
                        <InputError :message="errors.day_of_month?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4 flex gap-4">
                        <div class="flex-1">
                            <InputLabel for="r_departure_time" value="Departure" />
                            <TextInput id="r_departure_time" type="time" v-model="recurringForm.departure_time" class="mt-1 block w-full" />
                            <InputError :message="errors.departure_time?.[0]" class="mt-2" />
                        </div>
                        <div class="flex-1">
                            <InputLabel for="r_arrival_time" value="Arrival" />
                            <TextInput id="r_arrival_time" type="time" v-model="recurringForm.arrival_time" class="mt-1 block w-full" />
                            <InputError :message="errors.arrival_time?.[0]" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <InputLabel for="available_seats" value="Seats" />
                        <TextInput id="available_seats" type="number" min="1" v-model="recurringForm.available_seats" class="mt-1 block w-full" />
                        <InputError :message="errors.available_seats?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4 flex gap-4">
                        <div class="flex-1">
                            <InputLabel for="starts_on" value="Starts on" />
                            <TextInput id="starts_on" type="date" v-model="recurringForm.starts_on" class="mt-1 block w-full" />
                            <InputError :message="errors.starts_on?.[0]" class="mt-2" />
                        </div>
                        <div class="flex-1">
                            <InputLabel for="ends_on" value="Ends on (optional)" />
                            <TextInput id="ends_on" type="date" v-model="recurringForm.ends_on" class="mt-1 block w-full" />
                            <InputError :message="errors.ends_on?.[0]" class="mt-2" />
                        </div>
                    </div>
                </template>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
