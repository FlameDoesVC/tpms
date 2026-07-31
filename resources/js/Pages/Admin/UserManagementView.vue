<script setup>
import { onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import { useAdminStore } from '@/stores/admin';

const adminStore = useAdminStore();
const showModal = ref(false);
const editingUser = ref(null);
const errors = ref({});

const ROLE_OPTIONS = [
    { value: 'visitor', label: 'Visitor' },
    { value: 'hotel_manager', label: 'Hotel Manager' },
    { value: 'ferry_operator', label: 'Ferry Operator' },
    { value: 'themepark_staff', label: 'Theme Park Staff' },
    { value: 'admin', label: 'Admin' },
];

const ROLE_VARIANT = {
    visitor: 'neutral',
    hotel_manager: 'info',
    ferry_operator: 'warning',
    themepark_staff: 'success',
    admin: 'danger',
};

const emptyForm = () => ({ name: '', email: '', password: '', role: 'visitor' });
const form = ref(emptyForm());

onMounted(() => adminStore.fetchUsers());

const openAddModal = () => {
    editingUser.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    form.value = { name: user.name, email: user.email, password: '', role: user.role ?? 'visitor' };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const save = async () => {
    errors.value = {};
    try {
        const payload = { ...form.value };
        if (editingUser.value) {
            if (!payload.password) delete payload.password;
            await adminStore.updateUser(editingUser.value.id, payload);
        } else {
            await adminStore.createUser(payload);
        }
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const remove = async (user) => {
    if (confirm(`Delete ${user.name}? This cannot be undone.`)) {
        try {
            await adminStore.deleteUser(user.id);
        } catch (e) {
            alert(e.response?.data?.message ?? 'Failed to delete user.');
        }
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="User Management" icon="users" compact />
        </template>

        <div class="max-w-5xl space-y-6">
            <TCard>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-foreground-muted">
                        {{ adminStore.users.length }} user{{ adminStore.users.length === 1 ? '' : 's' }}
                    </p>
                    <TButton @click="openAddModal">+ Add User</TButton>
                </div>
            </TCard>

            <TCard title="All Users" icon="users" :padding="false">
                <div v-if="adminStore.loading" class="p-8 text-center text-sm text-foreground-muted">
                    Loading...
                </div>
                <div v-else-if="adminStore.error" class="p-8 text-center text-sm text-danger">
                    {{ adminStore.error }}
                </div>
                <div v-else-if="adminStore.users.length === 0" class="p-4">
                    <TEmptyState title="No users yet" description="Create the first user account." icon="users" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4">Role</th>
                                <th class="p-4">Joined</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="user in adminStore.users" :key="user.id">
                                <td class="p-4 text-foreground">
                                    {{ user.name }}
                                    <span v-if="user.is_guest" class="ml-1 text-xs text-foreground-muted">(guest)</span>
                                </td>
                                <td class="p-4">{{ user.email }}</td>
                                <td class="p-4">
                                    <TBadge :variant="ROLE_VARIANT[user.role] ?? 'neutral'">
                                        {{ user.role ?? '—' }}
                                    </TBadge>
                                </td>
                                <td class="p-4">{{ user.created_at }}</td>
                                <td class="p-4 space-x-2">
                                    <button class="text-sm text-primary hover:underline" @click="openEditModal(user)">
                                        Edit
                                    </button>
                                    <button class="text-sm text-danger hover:underline" @click="remove(user)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal" @close="closeModal">
            <template #title>{{ editingUser ? 'Edit User' : 'Add User' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput
                    id="name"
                    v-model="form.name"
                    label="Name"
                    :error="errors.name?.[0]"
                />
                <TInput
                    id="email"
                    v-model="form.email"
                    label="Email"
                    type="email"
                    :error="errors.email?.[0]"
                />
                <TInput
                    id="password"
                    v-model="form.password"
                    label="Password"
                    type="password"
                    :placeholder="editingUser ? 'Leave blank to keep current' : ''"
                    :error="errors.password?.[0]"
                />
                <TSelect
                    v-model="form.role"
                    label="Role"
                    :options="ROLE_OPTIONS"
                    :error="errors.role?.[0]"
                />
                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" @click="save">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
