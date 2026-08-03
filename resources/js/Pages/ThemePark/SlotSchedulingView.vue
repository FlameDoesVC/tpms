<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TTimePicker from '@/Components/ui/TTimePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TCard from '@/Components/ui/TCard.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import MonthCalendar from '@/Components/MonthCalendar.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDate, formatTime } from '@/utils/format';

const themeParkStore = useThemeParkStore();
const confirm = useConfirm();
const showModal = ref(false);
const errors = ref({});
const saving = ref(false);
const scheduleMode = ref('oneoff');
const calendarMonth = ref(new Date().toISOString().slice(0, 10));

// Fixed hue order, assigned by event id (not list position) so an event's
// colour stays stable as other events are added or removed.
//
// Hues rather than Tailwind class pairs: the old `bg-blue-100` / `text-blue-800`
// chips were fixed light-theme colours, so on the dark theme the calendar filled
// with pale pastel blocks belonging to no palette in the app. A translucent tint
// of the hue, labelled in the hue itself, reads correctly on both backgrounds —
// the same technique the map pins and the ferry calendar use.
const EVENT_HUES = ['#3b82f6', '#f97316', '#10b981', '#eab308', '#ec4899', '#8b5cf6', '#ef4444', '#14b8a6'];

const eventHue = (eventId) => EVENT_HUES[(eventId ?? 0) % EVENT_HUES.length];
const eventChipStyle = (eventId) => {
    const hue = eventHue(eventId);
    return { backgroundColor: `${hue}26`, color: hue, borderColor: `${hue}59` };
};

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
    saving.value = true;
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
        showToast(
            scheduleMode.value === 'oneoff'
                ? 'Slot added.'
                : editingTemplateId.value ? 'Recurring schedule updated.' : 'Recurring schedule created.',
            'success'
        );
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this schedule.');
        }
    } finally {
        saving.value = false;
    }
};

// Names the slot being cancelled: the trigger is a small block in a calendar
// cell, so "Cancel this slot?" gave no way to tell you had the right one.
const cancelSlot = async (slot) => {
    const booked = (slot.event?.capacity_per_slot ?? 0) - (slot.available_capacity ?? 0);
    const ok = await confirm({
        title: `Cancel the ${formatTime(slot.slot_time)} slot?`,
        message: booked > 0
            ? `${slot.event?.name} on ${formatDate(slot.slot_date)} has ${booked} ticket${booked === 1 ? '' : 's'} sold. Holders keep them, but the slot is marked cancelled.`
            : `${slot.event?.name} on ${formatDate(slot.slot_date)}. No tickets sold for it yet.`,
        confirmLabel: 'Cancel slot',
        cancelLabel: 'Keep it',
        danger: true,
    });
    if (!ok) return;

    try {
        await themeParkStore.cancelSlot(slot.id);
        showToast('Slot cancelled.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not cancel this slot.');
    }
};

const uncancelSlot = async (slot) => {
    const ok = await confirm({
        title: 'Put this slot back?',
        message: `${slot.event?.name} at ${formatTime(slot.slot_time)} on ${formatDate(slot.slot_date)} becomes bookable again.`,
        confirmLabel: 'Restore slot',
    });
    if (!ok) return;

    try {
        await themeParkStore.uncancelSlot(slot.id);
        showToast('Slot restored.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not restore this slot.');
    }
};

const toggleSlot = (slot) => {
    if (slot.status === 'scheduled') cancelSlot(slot);
    else if (slot.status === 'cancelled') uncancelSlot(slot);
};

const stopTemplate = async (template) => {
    const ok = await confirm({
        title: 'Stop this recurring schedule?',
        message: 'No further slots will be generated from it. Slots already on the calendar are kept.',
        confirmLabel: 'Stop generating',
        danger: true,
    });
    if (!ok) return;

    try {
        await themeParkStore.stopTemplate(template.id);
        showToast('Recurring schedule stopped.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not stop this schedule.');
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

const eventOptions = computed(() =>
    themeParkStore.events.map((e) => ({ value: e.id, label: e.name }))
);

const frequencyOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
];
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Slot Scheduling" icon="calendar">
                <template #actions>
                    <TButton size="sm" @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add Slots
                    </TButton>
                </template>
            </TPageHeader>
        </template>

        <div class="space-y-5">
            <MonthCalendar v-model="calendarMonth" :items="calendarItems">
                <template #legend>
                    <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs text-foreground-secondary">
                        <span v-for="event in themeParkStore.events" :key="event.id" class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: eventHue(event.id) }" />
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
                            class="block w-full rounded border px-1.5 py-0.5 text-left text-xs font-medium transition-opacity hover:opacity-80"
                            :style="eventChipStyle(item.event_id)"
                            :class="item.status === 'cancelled' && 'opacity-50 line-through'"
                            :title="item.status === 'cancelled'
                                ? `${item.event?.name} — cancelled (click to restore)`
                                : `${item.event?.name} — ${item.status} (click to cancel)`"
                            @click="toggleSlot(item)"
                        >
                            {{ formatTime(item.slot_time) }}
                            <span class="opacity-80">
                                {{ item.event?.capacity_per_slot - item.available_capacity }}/{{ item.event?.capacity_per_slot }}
                            </span>
                            <span v-if="item.is_overridden" title="Edited away from its recurring pattern">⚠</span>
                        </button>
                    </div>
                </template>
            </MonthCalendar>

            <TCard icon="clock" title="Recurring schedules" :padding="false">
                <!-- Had no empty state at all: with no templates the card showed a
                     bare header row over nothing. -->
                <div v-if="themeParkStore.templates.length === 0" class="p-4">
                    <TEmptyState
                        title="No recurring schedules"
                        description="Add one to generate slots automatically instead of entering each day by hand."
                        icon="calendar"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Pattern</th>
                                <th>Runs</th>
                                <th class="num">Capacity</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="template in themeParkStore.templates" :key="template.id">
                                <td>
                                    <span class="flex items-center gap-2 font-medium text-foreground">
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :style="{ backgroundColor: eventHue(template.event_id) }"
                                            aria-hidden="true"
                                        />
                                        {{ template.event?.name }}
                                    </span>
                                </td>
                                <td>{{ patternSummary(template) }}</td>
                                <td class="whitespace-nowrap">
                                    {{ formatDate(template.starts_on) }}
                                    <span class="text-foreground-muted">
                                        → {{ template.ends_on ? formatDate(template.ends_on) : 'ongoing' }}
                                    </span>
                                </td>
                                <td class="num">{{ template.capacity ?? template.event?.capacity_per_slot ?? '—' }}</td>
                                <td>
                                    <TBadge :variant="template.is_active ? 'success' : 'neutral'" dot>
                                        {{ template.is_active ? 'Generating' : 'Stopped' }}
                                    </TBadge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <template v-if="template.is_active">
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                                :aria-label="`Edit the ${template.event?.name} recurring schedule`"
                                                title="Edit"
                                                @click="openEditTemplateModal(template)"
                                            >
                                                <TIcon name="edit" :size="16" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                                :aria-label="`Stop the ${template.event?.name} recurring schedule`"
                                                title="Stop generating"
                                                @click="stopTemplate(template)"
                                            >
                                                <TIcon name="x" :size="16" />
                                            </button>
                                        </template>
                                        <span v-else class="text-xs text-foreground-muted">No actions</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal">
            <template #title>{{ editingTemplateId ? 'Edit Recurring Schedule' : 'Add Slots' }}</template>

            <form id="slot-form" @submit.prevent="save" class="space-y-4">
                <div v-if="!editingTemplateId" class="flex gap-2 rounded-lg bg-surface-hover p-1 text-sm">
                    <button
                        type="button"
                        class="flex-1 rounded px-3 py-1"
                        :class="scheduleMode === 'oneoff' ? 'bg-surface shadow-sm' : 'text-foreground-muted'"
                        @click="scheduleMode = 'oneoff'"
                    >
                        One-off
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded px-3 py-1"
                        :class="scheduleMode === 'recurring' ? 'bg-surface shadow-sm' : 'text-foreground-muted'"
                        @click="scheduleMode = 'recurring'"
                    >
                        Recurring
                    </button>
                </div>

                <template v-if="scheduleMode === 'oneoff'">
                    <TSelect v-model="form.event_id" label="Event" :error="errors.event_id?.[0]" :options="eventOptions" />
                    <TDatePicker v-model="form.slot_date" label="Date" :error="errors.slot_date?.[0]" />
                    <TTimePicker v-model="form.slot_time" label="Time" :error="errors.slot_time?.[0]" />
                    <TNumberInput v-model="form.capacity" label="Capacity Override (optional)" :min="1" />
                </template>

                <template v-else>
                    <TSelect v-model="recurringForm.event_id" label="Event" :error="errors.event_id?.[0]" :options="eventOptions" />
                    <TSelect v-model="recurringForm.frequency" label="Frequency" :error="errors.frequency?.[0]" :options="frequencyOptions" />

                    <div v-if="recurringForm.frequency === 'weekly'">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">Weekdays</label>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="option in WEEKDAY_OPTIONS" :key="option.value" class="flex items-center gap-1 text-sm">
                                <input type="checkbox" :value="option.value" v-model="recurringForm.weekdays" />
                                {{ option.label }}
                            </label>
                        </div>
                        <p v-if="errors.weekdays?.[0]" class="mt-1.5 text-sm text-danger">{{ errors.weekdays[0] }}</p>
                    </div>

                    <TNumberInput v-if="recurringForm.frequency === 'monthly'" v-model="recurringForm.day_of_month" label="Day of month" :min="1" :max="31" :error="errors.day_of_month?.[0]" />
                    <TTimePicker v-model="recurringForm.slot_time" label="Time" :error="errors.slot_time?.[0]" />
                    <TNumberInput v-model="recurringForm.available_capacity" label="Capacity" :min="1" :error="errors.available_capacity?.[0]" />

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <TDatePicker v-model="recurringForm.starts_on" label="Starts on" :error="errors.starts_on?.[0]" />
                        </div>
                        <div class="flex-1">
                            <TDatePicker v-model="recurringForm.ends_on" label="Ends on (optional)" :clearable="true" :error="errors.ends_on?.[0]" />
                        </div>
                    </div>
                </template>
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="submit" form="slot-form" :loading="saving">
                    {{ editingTemplateId ? 'Save changes' : scheduleMode === 'recurring' ? 'Create schedule' : 'Add slot' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
