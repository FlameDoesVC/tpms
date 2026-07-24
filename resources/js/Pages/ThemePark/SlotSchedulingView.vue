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
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const showModal = ref(false);
const errors = ref({});
const scheduleMode = ref('oneoff');
const calendarMonth = ref(new Date().toISOString().slice(0, 10));

// Fixed hue order, assigned by event id (not list position) so an event's
// color stays stable as other events are added/removed.
const EVENT_COLORS = [
    { bg: 'bg-blue-100', text: 'text-blue-800', dot: 'bg-blue-500' },
    { bg: 'bg-orange-100', text: 'text-orange-800', dot: 'bg-orange-500' },
    { bg: 'bg-emerald-100', text: 'text-emerald-800', dot: 'bg-emerald-500' },
    { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-500' },
    { bg: 'bg-pink-100', text: 'text-pink-800', dot: 'bg-pink-500' },
    { bg: 'bg-violet-100', text: 'text-violet-800', dot: 'bg-violet-500' },
    { bg: 'bg-red-100', text: 'text-red-800', dot: 'bg-red-500' },
    { bg: 'bg-teal-100', text: 'text-teal-800', dot: 'bg-teal-500' },
];

const eventColor = (eventId) => EVENT_COLORS[eventId % EVENT_COLORS.length];

const WEEKDAY_OPTIONS = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

const emptyOneOffForm = () => ({ event_id: '', slot_date: '', slot_time: '', capacity: '' });
const emptyRecurringForm = () => ({
    event_id: '',
    frequency: 'daily',
    weekdays: [],
    day_of_month: '',
    slot_time: '',
    available_capacity: '',
    starts_on: '',
    ends_on: '',
});

const form = ref(emptyOneOffForm());
const recurringForm = ref(emptyRecurringForm());
const editingTemplateId = ref(null);

onMounted(() => {
    themeParkStore.fetchEvents();
    themeParkStore.fetchAllSlots();
    themeParkStore.fetchTemplates();
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
        event_id: template.event_id,
        frequency: template.frequency,
        weekdays: template.weekdays ?? [],
        day_of_month: template.day_of_month ?? '',
        slot_time: template.slot_time?.slice(0, 5) ?? '',
        available_capacity: template.available_capacity,
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
            await themeParkStore.createSlot(form.value.event_id, {
                slot_date: form.value.slot_date,
                slot_time: form.value.slot_time,
                capacity: form.value.capacity || undefined,
            });
            await themeParkStore.fetchAllSlots();
        } else if (editingTemplateId.value) {
            await themeParkStore.updateTemplate(editingTemplateId.value, recurringForm.value);
            await themeParkStore.fetchAllSlots();
        } else {
            await themeParkStore.createTemplate(recurringForm.value);
            await themeParkStore.fetchAllSlots();
        }
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const cancelSlot = (slot) => {
    if (confirm('Cancel this slot?')) {
        themeParkStore.cancelSlot(slot.id);
    }
};

const uncancelSlot = (slot) => {
    if (confirm('Restore this slot to scheduled?')) {
        themeParkStore.uncancelSlot(slot.id);
    }
};

const toggleSlot = (slot) => {
    if (slot.status === 'scheduled') cancelSlot(slot);
    else if (slot.status === 'cancelled') uncancelSlot(slot);
};

const stopTemplate = (template) => {
    if (confirm('Stop this recurring schedule? Already-generated slots are kept.')) {
        themeParkStore.stopTemplate(template.id);
    }
};

const patternSummary = (template) => {
    if (template.frequency === 'daily') return `Daily, ${template.slot_time?.slice(0, 5)}`;
    if (template.frequency === 'weekly') {
        const labels = (template.weekdays ?? [])
            .map((v) => WEEKDAY_OPTIONS.find((o) => o.value === v)?.label)
            .join('/');
        return `Weekly: ${labels}, ${template.slot_time?.slice(0, 5)}`;
    }
    return `Monthly: day ${template.day_of_month}, ${template.slot_time?.slice(0, 5)}`;
};

const calendarItems = computed(() => themeParkStore.slots.map((s) => ({
    ...s,
    date: s.slot_date?.slice(0, 10),
})));
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Slot Scheduling
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-end">
                    <PrimaryButton @click="openAddModal">Add Slots</PrimaryButton>
                </div>

                <MonthCalendar v-model="calendarMonth" :items="calendarItems">
                    <template #legend>
                        <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs text-gray-600">
                            <span v-for="event in themeParkStore.events" :key="event.id" class="flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full" :class="eventColor(event.id).dot" />
                                {{ event.name }}
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
                                :class="[eventColor(item.event_id).bg, eventColor(item.event_id).text, item.status === 'cancelled' && 'opacity-50 line-through']"
                                :title="item.status === 'cancelled' ? `${item.event?.name} - cancelled (click to restore)` : `${item.event?.name} - ${item.status}`"
                                @click="toggleSlot(item)"
                            >
                                {{ item.slot_time?.slice(0, 5) }} ({{ item.event?.capacity_per_slot - item.available_capacity }}/{{ item.event?.capacity_per_slot }})
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
                                <th class="p-4">Event</th>
                                <th class="p-4">Pattern</th>
                                <th class="p-4">Range</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="template in themeParkStore.templates" :key="template.id">
                                <td class="p-4">{{ template.event?.name }}</td>
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
                    {{ editingTemplateId ? 'Edit Recurring Schedule' : 'Add Slots' }}
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
                        <InputLabel for="event_id" value="Event" />
                        <select id="event_id" v-model="form.event_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option v-for="event in themeParkStore.events" :key="event.id" :value="event.id">
                                {{ event.name }}
                            </option>
                        </select>
                        <InputError :message="errors.event_id?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="slot_date" value="Date" />
                        <TextInput id="slot_date" type="date" v-model="form.slot_date" class="mt-1 block w-full" />
                        <InputError :message="errors.slot_date?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="slot_time" value="Time" />
                        <TextInput id="slot_time" type="time" v-model="form.slot_time" class="mt-1 block w-full" />
                        <InputError :message="errors.slot_time?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="capacity" value="Capacity Override (optional)" />
                        <TextInput id="capacity" type="number" min="1" v-model="form.capacity" class="mt-1 block w-full" />
                    </div>
                </template>

                <template v-else>
                    <div class="mt-4">
                        <InputLabel for="r_event_id" value="Event" />
                        <select id="r_event_id" v-model="recurringForm.event_id" :disabled="!!editingTemplateId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option v-for="event in themeParkStore.events" :key="event.id" :value="event.id">
                                {{ event.name }}
                            </option>
                        </select>
                        <InputError :message="errors.event_id?.[0]" class="mt-2" />
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

                    <div class="mt-4">
                        <InputLabel for="r_slot_time" value="Time" />
                        <TextInput id="r_slot_time" type="time" v-model="recurringForm.slot_time" class="mt-1 block w-full" />
                        <InputError :message="errors.slot_time?.[0]" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="available_capacity" value="Capacity" />
                        <TextInput id="available_capacity" type="number" min="1" v-model="recurringForm.available_capacity" class="mt-1 block w-full" />
                        <InputError :message="errors.available_capacity?.[0]" class="mt-2" />
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
