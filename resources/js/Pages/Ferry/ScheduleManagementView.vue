<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TTimePicker from '@/Components/ui/TTimePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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

const FREQUENCY_OPTIONS = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
];

const ferryOptions = computed(() =>
    ferryStore.ferries.map((ferry) => ({ value: ferry.id, label: ferry.name }))
);

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
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Ferry Schedule Management" icon="calendar">
                <template #actions>
                    <TButton size="sm" @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add Schedule
                    </TButton>
                </template>
            </TPageHeader>
        </template>

        <div class="max-w-5xl space-y-6">
            <MonthCalendar v-model="calendarMonth" :items="calendarItems">
                <template #legend>
                    <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs text-foreground-secondary">
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

            <TCard icon="clock" title="Recurring Schedules" :padding="false">
                <div v-if="ferryStore.templates.length === 0" class="p-4">
                    <TEmptyState
                        title="No recurring schedules"
                        description="Add a recurring schedule to generate departures automatically."
                        icon="calendar"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Ferry</th>
                                <th class="p-4">Pattern</th>
                                <th class="p-4">Range</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="template in ferryStore.templates" :key="template.id">
                                <td class="p-4 text-foreground">{{ template.ferry?.name }}</td>
                                <td class="p-4">{{ patternSummary(template) }}</td>
                                <td class="p-4">{{ template.starts_on?.slice(0, 10) }} - {{ template.ends_on?.slice(0, 10) ?? 'ongoing' }}</td>
                                <td class="p-4">
                                    <TBadge :variant="template.is_active ? 'success' : 'neutral'">
                                        {{ template.is_active ? 'Yes' : 'Stopped' }}
                                    </TBadge>
                                </td>
                                <td class="p-4 space-x-3">
                                    <button v-if="template.is_active" class="text-sm text-primary hover:underline" @click="openEditTemplateModal(template)">Edit</button>
                                    <button v-if="template.is_active" class="text-sm text-danger hover:underline" @click="stopTemplate(template)">Stop</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal" max-width="lg" @close="closeModal">
            <template #title>{{ editingTemplateId ? 'Edit Recurring Schedule' : 'Add Schedule' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <div v-if="!editingTemplateId" class="flex gap-2 rounded-lg bg-surface-hover p-1 text-sm">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-1 transition-colors"
                        :class="scheduleMode === 'oneoff' ? 'bg-surface font-medium text-foreground shadow-sm' : 'text-foreground-muted'"
                        @click="scheduleMode = 'oneoff'"
                    >
                        One-off
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-1 transition-colors"
                        :class="scheduleMode === 'recurring' ? 'bg-surface font-medium text-foreground shadow-sm' : 'text-foreground-muted'"
                        @click="scheduleMode = 'recurring'"
                    >
                        Recurring
                    </button>
                </div>

                <template v-if="scheduleMode === 'oneoff'">
                    <TSelect
                        v-model="form.ferry_id"
                        label="Ferry"
                        :options="ferryOptions"
                        :error="errors.ferry_id?.[0]"
                    />

                    <TDatePicker
                        id="departure_date"
                        v-model="form.departure_date"
                        label="Date"
                        :error="errors.departure_date?.[0]"
                    />

                    <div class="flex gap-4">
                        <TTimePicker
                            id="departure_time"
                            v-model="form.departure_time"
                            label="Departure"
                            class="flex-1"
                            :error="errors.departure_time?.[0]"
                        />
                        <TTimePicker
                            id="arrival_time"
                            v-model="form.arrival_time"
                            label="Arrival"
                            class="flex-1"
                            :error="errors.arrival_time?.[0]"
                        />
                    </div>
                </template>

                <template v-else>
                    <TSelect
                        v-model="recurringForm.ferry_id"
                        label="Ferry"
                        :options="ferryOptions"
                        :error="errors.ferry_id?.[0]"
                        :class="editingTemplateId ? 'pointer-events-none opacity-60' : ''"
                    />

                    <TSelect
                        v-model="recurringForm.frequency"
                        label="Frequency"
                        :options="FREQUENCY_OPTIONS"
                        :error="errors.frequency?.[0]"
                    />

                    <div v-if="recurringForm.frequency === 'weekly'">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">Weekdays</label>
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="option in WEEKDAY_OPTIONS"
                                :key="option.value"
                                class="flex items-center gap-1 text-sm text-foreground-secondary"
                            >
                                <input
                                    type="checkbox"
                                    :value="option.value"
                                    v-model="recurringForm.weekdays"
                                    class="rounded border-strong text-primary focus:ring-primary/30"
                                />
                                {{ option.label }}
                            </label>
                        </div>
                        <p v-if="errors.weekdays?.[0]" class="mt-1.5 text-sm text-danger">{{ errors.weekdays[0] }}</p>
                    </div>

                    <TNumberInput
                        v-if="recurringForm.frequency === 'monthly'"
                        id="day_of_month"
                        v-model="recurringForm.day_of_month"
                        label="Day of month"
                        :min="1"
                        :max="31"
                        :error="errors.day_of_month?.[0]"
                    />

                    <div class="flex gap-4">
                        <TTimePicker
                            id="r_departure_time"
                            v-model="recurringForm.departure_time"
                            label="Departure"
                            class="flex-1"
                            :error="errors.departure_time?.[0]"
                        />
                        <TTimePicker
                            id="r_arrival_time"
                            v-model="recurringForm.arrival_time"
                            label="Arrival"
                            class="flex-1"
                            :error="errors.arrival_time?.[0]"
                        />
                    </div>

                    <TNumberInput
                        id="available_seats"
                        v-model="recurringForm.available_seats"
                        label="Seats"
                        :min="1"
                        :error="errors.available_seats?.[0]"
                    />

                    <div class="flex gap-4">
                        <TDatePicker
                            id="starts_on"
                            v-model="recurringForm.starts_on"
                            label="Starts on"
                            class="flex-1"
                            :error="errors.starts_on?.[0]"
                        />
                        <TDatePicker
                            id="ends_on"
                            v-model="recurringForm.ends_on"
                            label="Ends on (optional)"
                            class="flex-1"
                            :clearable="true"
                            :error="errors.ends_on?.[0]"
                        />
                    </div>
                </template>

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" @click="save">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
