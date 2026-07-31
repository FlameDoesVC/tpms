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
import { usePromotionsStore } from '@/stores/promotions';
import { useAuthStore } from '@/stores/auth';

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

const showModal = ref(false);
const editing = ref(null);
const errors = ref({});

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
        showModal.value = false;
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const toggleActive = async (promo) => {
    await store.update(promo.id, { is_active: !promo.is_active });
};

const remove = async (promo) => {
    if (confirm(`Delete "${promo.title}"?`)) {
        await store.remove(promo.id);
    }
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : '—');
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Promotions" icon="sparkle" compact />
        </template>

        <div class="max-w-5xl space-y-6">
            <TCard>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-foreground-muted">
                        {{ store.managed.length }} promotion{{ store.managed.length === 1 ? '' : 's' }}
                        <span v-if="isAdmin"> (all users)</span>
                        <span v-else> (yours)</span>
                    </p>
                    <TButton @click="openAdd">+ New Promotion</TButton>
                </div>
            </TCard>

            <TCard title="Promotions" icon="sparkle" :padding="false">
                <div v-if="store.loading" class="p-8 text-center text-sm text-foreground-muted">Loading…</div>
                <div v-else-if="store.error" class="p-8 text-center text-sm text-danger">{{ store.error }}</div>
                <div v-else-if="store.managed.length === 0" class="p-4">
                    <TEmptyState
                        title="No promotions yet"
                        description="Create a promotion to display it on the homepage."
                        icon="sparkle"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Title</th>
                                <th class="p-4">Category</th>
                                <th v-if="isAdmin" class="p-4">Created by</th>
                                <th class="p-4">Dates</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="promo in store.managed" :key="promo.id">
                                <td class="p-4">
                                    <p class="font-medium text-foreground">{{ promo.title }}</p>
                                    <p v-if="promo.description" class="mt-0.5 max-w-xs truncate text-xs text-foreground-muted">
                                        {{ promo.description }}
                                    </p>
                                </td>
                                <td class="p-4">
                                    <TBadge :variant="CATEGORY_VARIANT[promo.category]">{{ promo.category }}</TBadge>
                                </td>
                                <td v-if="isAdmin" class="p-4 text-xs">{{ promo.creator?.name ?? '—' }}</td>
                                <td class="p-4 text-xs">
                                    <span v-if="promo.starts_at || promo.ends_at">
                                        {{ formatDate(promo.starts_at) }} – {{ formatDate(promo.ends_at) }}
                                    </span>
                                    <span v-else class="text-foreground-muted">Always</span>
                                </td>
                                <td class="p-4">
                                    <button type="button" @click="toggleActive(promo)">
                                        <TBadge :variant="promo.is_active ? 'success' : 'neutral'">
                                            {{ promo.is_active ? 'Active' : 'Inactive' }}
                                        </TBadge>
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button class="text-sm text-primary hover:underline" @click="openEdit(promo)">Edit</button>
                                    <button class="text-sm text-danger hover:underline" @click="remove(promo)">Delete</button>
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
                <TButton type="button" @click="save">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
