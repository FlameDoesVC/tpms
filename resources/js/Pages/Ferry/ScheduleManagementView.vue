<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TTimePicker from '@/Components/ui/TTimePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import MonthCalendar from '@/Components/MonthCalendar.vue';
import CalendarDayPanel from '@/Components/CalendarDayPanel.vue';
import { useFerryStore } from '@/stores/ferry';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDate, formatTime, todayIso } from '@/utils/format';

const ferryStore = useFerryStore();
const confirm = useConfirm();
const showModal = ref(false);
const errors = ref({});
const saving = ref(false);
const scheduleMode = ref('oneoff');
const calendarMonth = ref(todayIso());
// Starts on today, so the page opens already answering "what's running now"
// instead of showing an empty rail waiting to be clicked.
const selectedDay = ref(todayIso());

// Fixed hue order, assigned by ferry id (not list position) so a ferry's
// colour stays stable as other ferries are added or removed.
//
// Held as hues rather than Tailwind class pairs: the old `bg-blue-100` /
// `text-blue-800` chips were fixed light-theme colours, so on the dark theme
// the calendar lit up with pale pastel blocks that belonged to no palette in
// the app. A translucent tint of the hue with the hue itself as the label
// reads correctly on both backgrounds — the same technique the map pins use.
const FERRY_HUES = ['#3b82f6', '#f97316', '#10b981', '#eab308', '#ec4899', '#8b5cf6', '#ef4444', '#14b8a6'];

const ferryHue = (ferryId) => FERRY_HUES[(ferryId ?? 0) % FERRY_HUES.length];
const ferryChipStyle = (ferryId) => {
    const hue = ferryHue(ferryId);
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

// `date` prefills the form, so adding to a day you clicked doesn't mean typing
// that date back in by hand.
const openAddModal = (date = null) => {
    scheduleMode.value = 'oneoff';
    form.value = { ...emptyOneOffForm(), departure_date: date ?? '' };
    recurringForm.value = { ...emptyRecurringForm(), starts_on: date ?? '' };
    editingTemplateId.value = null;
    errors.value = {};
    showModal.value = true;
};

// No toggle-off: the rail is permanent, so deselecting would only empty it.
const selectDay = (iso) => {
    selectedDay.value = iso;
};

// The panel details a day you can see in the grid, so paging the month away
// from it closes it rather than leaving a detail view for a day that is no
// longer on screen.
watch(calendarMonth, (month) => {
    if (selectedDay.value && !selectedDay.value.startsWith(month.slice(0, 7))) {
        selectedDay.value = null;
    }
});

const selectedDaySchedules = computed(() =>
    selectedDay.value
        ? calendarItems.value
            .filter((s) => s.date === selectedDay.value)
            .slice()
            .sort((a, b) => (a.departure_time ?? '').localeCompare(b.departure_time ?? ''))
        : []
);

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
    saving.value = true;
    try {
        if (scheduleMode.value === 'oneoff') {
            await ferryStore.createSchedule(form.value);
            showToast('Departure added.', 'success');
        } else if (editingTemplateId.value) {
            await ferryStore.updateTemplate(editingTemplateId.value, recurringForm.value);
            showToast('Recurring schedule updated.', 'success');
        } else {
            await ferryStore.createTemplate(recurringForm.value);
            showToast('Recurring schedule created.', 'success');
        }
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

// Cancelling a sailing strands whoever is booked on it, so it says which
// sailing it means rather than asking "Cancel this departure?" about a block
// the cursor happens to be over.
const cancelSchedule = async (schedule) => {
    const booked = (schedule.ferry?.capacity ?? 0) - (schedule.available_seats ?? 0);
    const ok = await confirm({
        title: `Cancel the ${formatTime(schedule.departure_time)} sailing?`,
        message: booked > 0
            ? `${schedule.ferry?.name} on ${formatDate(schedule.departure_date)} has ${booked} passenger${booked === 1 ? '' : 's'} booked. They keep their tickets, but the sailing is marked cancelled.`
            : `${schedule.ferry?.name} on ${formatDate(schedule.departure_date)}. Nobody is booked on it yet.`,
        confirmLabel: 'Cancel sailing',
        cancelLabel: 'Keep it',
        danger: true,
    });
    if (!ok) return;

    try {
        await ferryStore.cancelSchedule(schedule.id);
        showToast('Sailing cancelled.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not cancel this sailing.');
    }
};

const uncancelSchedule = async (schedule) => {
    const ok = await confirm({
        title: 'Put this sailing back?',
        message: `${schedule.ferry?.name} at ${formatTime(schedule.departure_time)} on ${formatDate(schedule.departure_date)} becomes bookable again.`,
        confirmLabel: 'Restore sailing',
    });
    if (!ok) return;

    try {
        await ferryStore.uncancelSchedule(schedule.id);
        showToast('Sailing restored.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not restore this sailing.');
    }
};

const stopTemplate = async (template) => {
    const ok = await confirm({
        title: 'Stop this recurring schedule?',
        message: 'No further departures will be generated from it. Departures already on the calendar are kept.',
        confirmLabel: 'Stop generating',
        danger: true,
    });
    if (!ok) return;

    try {
        await ferryStore.stopTemplate(template.id);
        showToast('Recurring schedule stopped.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not stop this schedule.');
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

        <div class="space-y-5">
            <!-- Calendar and the selected day side by side; the recurring table
                 spans the full width underneath, where its six columns fit. -->
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_21rem]">
            <MonthCalendar
                v-model="calendarMonth"
                :items="calendarItems"
                :selected="selectedDay"
                add-label="Add a sailing"
                @select="selectDay"
                @add="openAddModal"
            >
                <template #legend>
                    <div class="flex flex-wrap justify-end gap-x-3 gap-y-1 text-xs text-foreground-secondary">
                        <span v-for="ferry in ferryStore.ferries" :key="ferry.id" class="flex items-center gap-1.5">
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :style="{ backgroundColor: ferryHue(ferry.id) }"
                            />
                            {{ ferry.name }}
                        </span>
                    </div>
                </template>
                <template #day="{ date, items }">
                    <div class="space-y-1">
                        <!-- Selects the day rather than cancelling the sailing.
                             Cancel used to be what a single click on a chip did,
                             which made the most inviting target on the page a
                             destructive one; it now lives in the day panel where
                             it's labelled. -->
                        <button
                            v-for="item in items"
                            :key="item.id"
                            type="button"
                            class="block w-full rounded border px-1.5 py-0.5 text-left text-xs font-medium transition-opacity hover:opacity-80"
                            :style="ferryChipStyle(item.ferry_id)"
                            :class="[
                                item.status !== 'scheduled' && 'opacity-50',
                                item.status === 'cancelled' && 'line-through',
                            ]"
                            :title="`${item.ferry?.name} — ${item.status}. Click to open this day.`"
                            @click.stop="selectDay(date)"
                        >
                            {{ formatTime(item.departure_time) }}
                            <span class="opacity-80">
                                {{ item.ferry?.capacity - item.available_seats }}/{{ item.ferry?.capacity }}
                            </span>
                            <span v-if="item.is_overridden" title="Edited away from its recurring pattern">⚠</span>
                        </button>
                    </div>
                </template>
            </MonthCalendar>

            <div class="xl:sticky xl:top-20 xl:self-start">
                <CalendarDayPanel
                    :date="selectedDay"
                    :count="selectedDaySchedules.length"
                    add-label="Add a sailing"
                    empty-text="No sailings on this day yet."
                    idle-text="Pick a day in the calendar to see its sailings."
                    @add="openAddModal(selectedDay)"
                >
                    <!-- Stacked rather than one wide row: this is a 21rem rail,
                         so the time leads, then the boat, then how full it is. -->
                    <div v-for="item in selectedDaySchedules" :key="item.id" class="px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span
                                class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: ferryHue(item.ferry_id) }"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1.5 font-medium text-foreground">
                                    {{ formatTime(item.departure_time) }}
                                    <TIcon name="arrowRight" :size="13" class="text-foreground-muted" />
                                    {{ formatTime(item.arrival_time) }}
                                </p>
                                <p class="truncate text-xs text-foreground-muted">{{ item.ferry?.name }}</p>
                            </div>
                            <TBadge
                                :variant="item.status === 'scheduled' ? 'success' : item.status === 'cancelled' ? 'neutral' : 'info'"
                                size="sm"
                                dot
                                class="shrink-0 capitalize"
                            >
                                {{ item.status }}
                            </TBadge>
                        </div>

                        <!-- How full it is, at a glance rather than as a fraction
                             you have to do arithmetic on. -->
                        <div class="mt-2">
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-hover">
                                <div
                                    class="h-full rounded-full"
                                    :class="item.available_seats === 0 ? 'bg-danger' : 'bg-primary'"
                                    :style="{ width: `${item.ferry?.capacity ? ((item.ferry.capacity - item.available_seats) / item.ferry.capacity) * 100 : 0}%` }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-foreground-muted">
                                {{ item.ferry?.capacity - item.available_seats }} of {{ item.ferry?.capacity }} booked
                                <span v-if="item.available_seats === 0" class="font-medium text-danger">· full</span>
                            </p>
                        </div>

                        <TBadge v-if="item.is_overridden" variant="warning" size="sm" class="mt-2">
                            Edited off pattern
                        </TBadge>

                        <div class="mt-2 flex items-center gap-2">
                            <TButton
                                v-if="item.status === 'scheduled'"
                                variant="secondary"
                                size="xs"
                                @click="cancelSchedule(item)"
                            >
                                Cancel sailing
                            </TButton>
                            <TButton
                                v-else-if="item.status === 'cancelled'"
                                variant="secondary"
                                size="xs"
                                @click="uncancelSchedule(item)"
                            >
                                Restore
                            </TButton>
                            <router-link
                                :to="{ name: 'ferry.passengers' }"
                                class="ml-auto text-xs font-medium text-primary hover:underline"
                            >
                                Manifest
                            </router-link>
                        </div>
                    </div>
                </CalendarDayPanel>
            </div>
            </div>

            <TCard icon="clock" title="Recurring schedules" :padding="false">
                <div v-if="ferryStore.templates.length === 0" class="p-4">
                    <TEmptyState
                        title="No recurring schedules"
                        description="Add one to generate departures automatically instead of entering each sailing by hand."
                        icon="calendar"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ferry</th>
                                <th>Pattern</th>
                                <th>Runs</th>
                                <th class="num">Seats</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="template in ferryStore.templates" :key="template.id">
                                <td>
                                    <span class="flex items-center gap-2 font-medium text-foreground">
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :style="{ backgroundColor: ferryHue(template.ferry_id) }"
                                            aria-hidden="true"
                                        />
                                        {{ template.ferry?.name }}
                                    </span>
                                </td>
                                <td>{{ patternSummary(template) }}</td>
                                <td class="whitespace-nowrap">
                                    {{ formatDate(template.starts_on) }}
                                    <span class="text-foreground-muted">
                                        → {{ template.ends_on ? formatDate(template.ends_on) : 'ongoing' }}
                                    </span>
                                </td>
                                <td class="num">{{ template.available_seats }}</td>
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
                                                :aria-label="`Edit the ${template.ferry?.name} recurring schedule`"
                                                title="Edit"
                                                @click="openEditTemplateModal(template)"
                                            >
                                                <TIcon name="edit" :size="16" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                                :aria-label="`Stop the ${template.ferry?.name} recurring schedule`"
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
                <TButton type="button" :loading="saving" @click="save">
                    {{ editingTemplateId ? 'Save changes' : scheduleMode === 'recurring' ? 'Create schedule' : 'Add departure' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
