<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import FerryLayoutEditor from '@/Components/Ferry/FerryLayoutEditor.vue';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatMoney } from '@/utils/format';

const confirm = useConfirm();
const ferries = ref([]);
const loading = ref(true);
const loadError = ref(null);
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const errors = ref({});
const search = ref('');

const DEFAULT_GRID = ['SSSS.SSSS', 'SSSS.SSSS', 'SSSS.SSSS', 'SSSS.SSSS', 'SSSS.SSSS'];

const emptyForm = () => ({
    name: '',
    price_per_seat: 15,
    is_active: true,
    layout: { grid: [...DEFAULT_GRID], entrances: [] },
});
const form = ref(emptyForm());

const load = async () => {
    loading.value = true;
    loadError.value = null;
    try {
        // `all=1` includes retired boats, which the public list hides.
        const { data } = await axios.get('/api/ferries', { params: { all: 1 } });
        ferries.value = data;
    } catch (e) {
        loadError.value = e.response?.data?.message ?? 'Could not load the fleet.';
    } finally {
        loading.value = false;
    }
};

onMounted(load);

const visibleFerries = computed(() => {
    const query = search.value.trim().toLowerCase();
    return query ? ferries.value.filter((f) => (f.name ?? '').toLowerCase().includes(query)) : ferries.value;
});

const summary = computed(() => {
    const total = ferries.value.length;
    if (!total) return null;
    const active = ferries.value.filter((f) => f.is_active).length;
    const seats = ferries.value.filter((f) => f.is_active).reduce((n, f) => n + f.capacity, 0);
    const shown = visibleFerries.value.length;
    return shown === total
        ? `${total} ferr${total === 1 ? 'y' : 'ies'} · ${active} in service · ${seats} seats`
        : `${shown} of ${total} ferries`;
});

// Seats the editor currently describes, so the dialog can show capacity before
// anything is saved.
const draftCapacity = computed(() =>
    (form.value.layout.grid ?? []).reduce((sum, row) => sum + [...row].filter((c) => c === 'S').length, 0)
);

const openAdd = () => {
    editing.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEdit = (ferry) => {
    editing.value = ferry;
    form.value = {
        name: ferry.name,
        price_per_seat: Number(ferry.price_per_seat),
        is_active: ferry.is_active,
        // Cloned, so abandoning the dialog doesn't mutate the row behind it.
        layout: {
            grid: [...(ferry.layout?.grid ?? DEFAULT_GRID)],
            entrances: (ferry.layout?.entrances ?? []).map((e) => ({ ...e })),
        },
    };
    errors.value = {};
    showModal.value = true;
};

const save = async () => {
    errors.value = {};
    saving.value = true;
    try {
        const payload = {
            name: form.value.name,
            price_per_seat: form.value.price_per_seat,
            is_active: form.value.is_active,
            layout: form.value.layout,
        };
        if (editing.value) {
            await axios.patch(`/api/ferries/${editing.value.id}`, payload);
            showToast(`${form.value.name} updated.`, 'success');
        } else {
            await axios.post('/api/ferries', payload);
            showToast(`${form.value.name} added to the fleet.`, 'success');
        }
        showModal.value = false;
        await load();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        const first = Object.values(errors.value)[0]?.[0];
        if (first) showToast(first);
        else showToast(e.response?.data?.message ?? 'Could not save this ferry.');
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (ferry) => {
    try {
        await axios.patch(`/api/ferries/${ferry.id}`, {
            name: ferry.name,
            price_per_seat: ferry.price_per_seat,
            is_active: !ferry.is_active,
            layout: ferry.layout,
        });
        await load();
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change this ferry.');
    }
};

const remove = async (ferry) => {
    const ok = await confirm({
        title: `Delete ${ferry.name}?`,
        message: ferry.schedules_count
            ? `This ferry has ${ferry.schedules_count} sailing${ferry.schedules_count === 1 ? '' : 's'} on the schedule, so it can't be deleted. Switch it inactive instead.`
            : 'It has no sailings, so nothing else is affected.',
        confirmLabel: 'Delete ferry',
        danger: true,
    });
    if (!ok) return;

    try {
        await axios.delete(`/api/ferries/${ferry.id}`);
        showToast(`${ferry.name} deleted.`, 'success');
        await load();
    } catch (e) {
        showToast(e.response?.data?.errors?.ferry?.[0] ?? e.response?.data?.message ?? 'Could not delete this ferry.');
    }
};

/** A one-line shape summary, e.g. "5 rows × 9 · 2 doors". */
const shapeOf = (ferry) => {
    const doors = ferry.layout?.entrances?.length ?? 0;
    const parts = [`${ferry.rows} rows × ${ferry.columns}`];
    if (doors) parts.push(`${doors} door${doors === 1 ? '' : 's'}`);
    return parts.join(' · ');
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Fleet" icon="ferry" />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Ferries" :summary="summary">
                <template #actions>
                    <TButton @click="openAdd">
                        <TIcon name="plus" :size="16" />
                        Add ferry
                    </TButton>
                </template>

                <TInput
                    v-model="search"
                    type="search"
                    label="Search"
                    placeholder="Ferry name"
                    class="min-w-[16rem] flex-1"
                >
                    <template #prefix><TIcon name="search" :size="16" /></template>
                </TInput>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="loading" class="p-8 text-center text-sm text-foreground-muted">Loading the fleet…</div>
                <div v-else-if="loadError" class="p-8 text-center text-sm text-danger">{{ loadError }}</div>
                <div v-else-if="ferries.length === 0" class="p-4">
                    <TEmptyState
                        title="No ferries yet"
                        description="Add a boat and lay out its deck before scheduling sailings for it."
                        icon="ferry"
                    >
                        <template #action>
                            <TButton @click="openAdd">Add ferry</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else-if="visibleFerries.length === 0" class="p-4">
                    <TEmptyState title="No ferries match" description="Try a different name." icon="search" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ferry</th>
                                <th>Deck</th>
                                <th class="num">Seats</th>
                                <th class="num">Fare</th>
                                <th class="num">Sailings</th>
                                <th>In service</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ferry in visibleFerries" :key="ferry.id">
                                <td>
                                    <p class="font-medium text-foreground">{{ ferry.name }}</p>
                                    <p v-if="!ferry.has_custom_layout" class="mt-0.5 text-xs text-foreground-muted">
                                        Default deck — never laid out
                                    </p>
                                </td>
                                <td class="whitespace-nowrap">{{ shapeOf(ferry) }}</td>
                                <td class="num font-semibold text-foreground">{{ ferry.capacity }}</td>
                                <td class="num">{{ formatMoney(ferry.price_per_seat) }}</td>
                                <td class="num">{{ ferry.schedules_count }}</td>
                                <td>
                                    <TSwitch :model-value="ferry.is_active" @update:model-value="toggleActive(ferry)" />
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit ${ferry.name}`"
                                            title="Edit deck and details"
                                            @click="openEdit(ferry)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete ${ferry.name}`"
                                            title="Delete"
                                            @click="remove(ferry)"
                                        >
                                            <TIcon name="trash" :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <!-- Wide: the deck grid is the substance of this dialog, so the details
             sit beside it rather than pushing it below the fold. -->
        <TModal v-model:show="showModal" max-width="5xl" @close="showModal = false">
            <template #title>{{ editing ? `Edit ${editing.name}` : 'Add a ferry' }}</template>

            <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-8">
                <div class="space-y-4">
                    <TInput v-model="form.name" label="Name" :error="errors.name?.[0]" />
                    <TNumberInput
                        v-model="form.price_per_seat"
                        label="Fare per seat"
                        :min="0"
                        :step="0.01"
                        width="full"
                        :error="errors.price_per_seat?.[0]"
                    />
                    <TSwitch v-model="form.is_active" label="In service" />

                    <div class="rounded-lg border bg-surface-sunken p-3">
                        <p class="text-xs text-foreground-muted">Capacity from this deck</p>
                        <p class="text-2xl font-semibold tabular-nums text-foreground">{{ draftCapacity }}</p>
                        <p class="mt-1 text-xs text-foreground-muted">
                            Set by the seats you place — it isn't entered separately.
                        </p>
                    </div>

                    <p v-if="errors.layout?.[0]" class="text-sm text-danger">{{ errors.layout[0] }}</p>
                </div>

                <FerryLayoutEditor
                    v-model="form.layout"
                    :highest-sold-seat="editing?.highest_sold_seat ?? 0"
                />
            </div>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showModal = false">Cancel</TButton>
                <TButton type="button" :loading="saving" :disabled="draftCapacity < 1" @click="save">
                    {{ editing ? 'Save changes' : 'Add ferry' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
