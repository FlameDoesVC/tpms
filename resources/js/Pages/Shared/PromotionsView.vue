<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { usePromotionsStore } from '@/stores/promotions';
import { useAuthStore } from '@/stores/auth';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDate } from '@/utils/format';

const store = usePromotionsStore();
const auth = useAuthStore();

const isAdmin = computed(() => auth.userRole === 'admin');

const CATEGORY_OPTIONS = [
    { value: 'hotel', label: 'Hotel' },
    { value: 'themepark', label: 'Theme Park' },
    { value: 'ferry', label: 'Ferry' },
    { value: 'general', label: 'General' },
];

const CATEGORY_VARIANT = {
    hotel: 'success',
    themepark: 'info',
    ferry: 'warning',
    general: 'neutral',
};

const confirm = useConfirm();
const showModal = ref(false);
const editing = ref(null);
const errors = ref({});
const saving = ref(false);
const categoryFilter = ref('');

const CATEGORY_LABELS = Object.fromEntries(CATEGORY_OPTIONS.map((o) => [o.value, o.label]));

const today = new Date().toISOString().slice(0, 10);
const dayOf = (value) => (value ?? '').slice(0, 10);

// `is_active` alone doesn't say whether a promotion is actually on the site:
// one can be switched on but not started yet, or long finished. The visitor
// side already applies the window, so the manager screen should show the same
// answer rather than an on/off flag that can quietly disagree with it.
const liveState = (promo) => {
    if (!promo.is_active) return { label: 'Off', variant: 'neutral' };
    if (promo.starts_at && dayOf(promo.starts_at) > today) return { label: 'Scheduled', variant: 'info' };
    if (promo.ends_at && dayOf(promo.ends_at) < today) return { label: 'Ended', variant: 'warning' };
    return { label: 'Live', variant: 'success' };
};

const visiblePromos = computed(() =>
    categoryFilter.value
        ? store.managed.filter((p) => p.category === categoryFilter.value)
        : store.managed
);

const categoryFilterOptions = computed(() => {
    const present = new Set(store.managed.map((p) => p.category));
    return [{ value: '', label: 'All categories' }, ...CATEGORY_OPTIONS.filter((o) => present.has(o.value))];
});

const summary = computed(() => {
    const total = store.managed.length;
    if (!total) return null;
    const live = store.managed.filter((p) => liveState(p).label === 'Live').length;
    const scope = isAdmin.value ? 'all users' : 'yours';
    const shown = visiblePromos.value.length;
    return shown === total
        ? `${total} promotion${total === 1 ? '' : 's'} (${scope}) · ${live} live`
        : `${shown} of ${total} promotions (${scope})`;
});

const emptyForm = () => ({
    title: '',
    description: '',
    image_url: '',
    category: 'general',
    starts_at: '',
    ends_at: '',
    is_active: true,
});
const form = ref(emptyForm());

onMounted(() => store.fetchManaged());

const openAdd = () => {
    editing.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEdit = (promo) => {
    editing.value = promo;
    form.value = {
        title: promo.title,
        description: promo.description ?? '',
        image_url: promo.image_url ?? '',
        category: promo.category,
        starts_at: promo.starts_at ?? '',
        ends_at: promo.ends_at ?? '',
        is_active: promo.is_active,
    };
    errors.value = {};
    showModal.value = true;
};

const save = async () => {
    errors.value = {};
    saving.value = true;
    const payload = { ...form.value };
    if (!payload.starts_at) delete payload.starts_at;
    if (!payload.ends_at) delete payload.ends_at;
    if (!payload.image_url) delete payload.image_url;
    if (!payload.description) delete payload.description;

    try {
        if (editing.value) {
            await store.update(editing.value.id, payload);
        } else {
            await store.create(payload);
        }
        showToast(editing.value ? 'Promotion updated.' : 'Promotion created.', 'success');
        showModal.value = false;
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this promotion.');
        }
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (promo) => {
    try {
        await store.update(promo.id, { is_active: !promo.is_active });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change this promotion.');
    }
};

const remove = async (promo) => {
    const ok = await confirm({
        title: `Delete "${promo.title}"?`,
        message: 'It is removed from the homepage and the booking pages for good. Switch it off instead if you may want it back.',
        confirmLabel: 'Delete promotion',
        danger: true,
    });
    if (!ok) return;

    try {
        await store.remove(promo.id);
        showToast('Promotion deleted.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this promotion.');
    }
};

// The local formatter this replaces ran date-only strings through
// `new Date(...)`, which parses them as UTC midnight and renders the day before
// anywhere west of Greenwich. The shared helper parses them as local dates.
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Promotions" icon="sparkle" compact />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Promotions" :summary="summary">
                <template #actions>
                    <TButton @click="openAdd">
                        <TIcon name="plus" :size="16" />
                        New promotion
                    </TButton>
                </template>

                <div class="w-56">
                    <TSelect v-model="categoryFilter" label="Category" :options="categoryFilterOptions" />
                </div>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="store.loading" class="p-8 text-center text-sm text-foreground-muted">Loading promotions…</div>
                <div v-else-if="store.error" class="p-8 text-center text-sm text-danger">{{ store.error }}</div>
                <div v-else-if="store.managed.length === 0" class="p-4">
                    <TEmptyState
                        title="No promotions yet"
                        description="Create one to show it on the homepage and the matching booking page."
                        icon="sparkle"
                    >
                        <template #action>
                            <TButton @click="openAdd">New promotion</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else-if="visiblePromos.length === 0" class="p-4">
                    <TEmptyState title="Nothing in this category" description="Clear the category filter to see them all." icon="search" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Promotion</th>
                                <th>Category</th>
                                <th v-if="isAdmin">Created by</th>
                                <th>Runs</th>
                                <th>Showing</th>
                                <th>On</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="promo in visiblePromos" :key="promo.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-12 shrink-0 place-items-center overflow-hidden rounded bg-surface-hover text-foreground-muted">
                                            <img
                                                v-if="promo.image_url"
                                                :src="promo.image_url"
                                                :alt="promo.title"
                                                class="h-full w-full object-cover"
                                            />
                                            <TIcon v-else name="sparkle" :size="14" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-foreground">{{ promo.title }}</p>
                                            <p v-if="promo.description" class="mt-0.5 max-w-xs truncate text-xs text-foreground-muted">
                                                {{ promo.description }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <TBadge :variant="CATEGORY_VARIANT[promo.category]">
                                        {{ CATEGORY_LABELS[promo.category] ?? promo.category }}
                                    </TBadge>
                                </td>
                                <td v-if="isAdmin" class="text-xs">{{ promo.creator?.name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">
                                    <span v-if="promo.starts_at || promo.ends_at">
                                        {{ promo.starts_at ? formatDate(promo.starts_at) : 'any time' }}
                                        → {{ promo.ends_at ? formatDate(promo.ends_at) : 'ongoing' }}
                                    </span>
                                    <span v-else class="text-foreground-muted">Always</span>
                                </td>
                                <td>
                                    <TBadge :variant="liveState(promo).variant" dot>{{ liveState(promo).label }}</TBadge>
                                </td>
                                <td>
                                    <TSwitch
                                        :model-value="promo.is_active"
                                        @update:model-value="toggleActive(promo)"
                                    />
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit ${promo.title}`"
                                            title="Edit"
                                            @click="openEdit(promo)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete ${promo.title}`"
                                            title="Delete"
                                            @click="remove(promo)"
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

        <TModal v-model:show="showModal" @close="showModal = false">
            <template #title>{{ editing ? 'Edit Promotion' : 'New Promotion' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput id="title" v-model="form.title" label="Title" :error="errors.title?.[0]" />

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border border-[rgb(var(--color-border))] bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary/40"
                        placeholder="Optional description shown under the promotion…"
                    />
                    <p v-if="errors.description?.[0]" class="mt-1 text-xs text-danger">{{ errors.description[0] }}</p>
                </div>

                <TInput id="image_url" v-model="form.image_url" label="Image URL (optional)" :error="errors.image_url?.[0]" />

                <TSelect v-model="form.category" label="Category" :options="CATEGORY_OPTIONS" :error="errors.category?.[0]" />

                <div class="grid grid-cols-2 gap-4">
                    <TInput id="starts_at" v-model="form.starts_at" label="Start date (optional)" type="date" :error="errors.starts_at?.[0]" />
                    <TInput id="ends_at" v-model="form.ends_at" label="End date (optional)" type="date" :error="errors.ends_at?.[0]" />
                </div>

                <div class="flex items-center gap-3">
                    <TSwitch v-model="form.is_active" />
                    <span class="text-sm text-foreground">Active (visible on homepage)</span>
                </div>

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showModal = false">Cancel</TButton>
                <TButton type="button" :loading="saving" @click="save">
                    {{ editing ? 'Save changes' : 'Create promotion' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
