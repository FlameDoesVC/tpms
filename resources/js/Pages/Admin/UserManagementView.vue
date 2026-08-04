<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPasswordInput from '@/Components/ui/TPasswordInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TAvatar from '@/Components/ui/TAvatar.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useAdminStore } from '@/stores/admin';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDate } from '@/utils/format';

const adminStore = useAdminStore();
const confirm = useConfirm();
const showModal = ref(false);
const editingUser = ref(null);
const errors = ref({});
const saving = ref(false);

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

const ROLE_LABELS = Object.fromEntries(ROLE_OPTIONS.map((o) => [o.value, o.label]));

const search = ref('');
const roleFilter = ref('');

// Only the roles actually present, so the filter never offers a dead end.
const roleFilterOptions = computed(() => {
    const present = new Set(adminStore.users.map((u) => u.role).filter(Boolean));
    return [
        { value: '', label: 'All roles' },
        ...ROLE_OPTIONS.filter((o) => present.has(o.value)),
    ];
});

const visibleUsers = computed(() => {
    let list = adminStore.users;

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((u) => `${u.name ?? ''} ${u.email ?? ''}`.toLowerCase().includes(query));
    }
    if (roleFilter.value) {
        list = list.filter((u) => u.role === roleFilter.value);
    }
    return list;
});

const summary = computed(() => {
    const total = adminStore.users.length;
    if (!total) return null;
    const shown = visibleUsers.value.length;
    return shown === total ? `${total} account${total === 1 ? '' : 's'}` : `${shown} of ${total} accounts`;
});

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
    saving.value = true;
    try {
        const payload = { ...form.value };
        if (editingUser.value) {
            if (!payload.password) delete payload.password;
            await adminStore.updateUser(editingUser.value.id, payload);
        } else {
            await adminStore.createUser(payload);
        }
        showToast(editingUser.value ? 'Account updated.' : 'Account created.', 'success');
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        // A failure with no field errors (a 500, a policy refusal) would
        // otherwise close nothing and say nothing.
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save the account.');
        }
    } finally {
        saving.value = false;
    }
};

// Was window.confirm/window.alert, which ignored the theme entirely and
// blocked the tab. The app has its own dialog and toast hosts mounted in
// App.vue - deleting an account is exactly what they are for.
const remove = async (user) => {
    const ok = await confirm({
        title: `Delete ${user.name}?`,
        message: 'The account and its sign-in are removed for good. Bookings already made against it are kept.',
        confirmLabel: 'Delete account',
        danger: true,
    });
    if (!ok) return;

    try {
        await adminStore.deleteUser(user.id);
        showToast(`${user.name} deleted.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this account.');
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="User Management" icon="users" compact />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Accounts" :summary="summary">
                <template #actions>
                    <TButton @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add user
                    </TButton>
                </template>

                <TInput
                    v-model="search"
                    type="search"
                    label="Search"
                    placeholder="Name or email"
                    class="min-w-[16rem] flex-1"
                >
                    <template #prefix><TIcon name="search" :size="16" /></template>
                </TInput>
                <div class="w-52">
                    <TSelect v-model="roleFilter" label="Role" :options="roleFilterOptions" />
                </div>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="adminStore.loading" class="p-8 text-center text-sm text-foreground-muted">
                    Loading accounts…
                </div>
                <div v-else-if="adminStore.error" class="p-8 text-center text-sm text-danger">
                    {{ adminStore.error }}
                </div>
                <div v-else-if="adminStore.users.length === 0" class="p-4">
                    <TEmptyState title="No users yet" description="Create the first user account." icon="users">
                        <template #action>
                            <TButton @click="openAddModal">Add user</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else-if="visibleUsers.length === 0" class="p-4">
                    <TEmptyState
                        title="No accounts match"
                        description="Try a different name, or clear the role filter."
                        icon="search"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in visibleUsers" :key="user.id">
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <TAvatar :name="user.name ?? ''" size="sm" />
                                        <span class="font-medium text-foreground">{{ user.name }}</span>
                                        <TBadge v-if="user.is_guest" variant="neutral" size="sm">guest</TBadge>
                                    </div>
                                </td>
                                <td>{{ user.email }}</td>
                                <td>
                                    <TBadge :variant="ROLE_VARIANT[user.role] ?? 'neutral'">
                                        {{ ROLE_LABELS[user.role] ?? user.role ?? '—' }}
                                    </TBadge>
                                </td>
                                <td class="whitespace-nowrap">{{ formatDate(user.created_at) || user.created_at }}</td>
                                <td>
                                    <!-- Icon buttons with real labels rather than two
                                         bare text links sat next to each other, where
                                         "Delete" was one mis-click from "Edit". -->
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit ${user.name}`"
                                            title="Edit"
                                            @click="openEditModal(user)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete ${user.name}`"
                                            title="Delete"
                                            @click="remove(user)"
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

        <TModal v-model:show="showModal" @close="closeModal">
            <template #title>{{ editingUser ? 'Edit account' : 'Add account' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput id="name" v-model="form.name" label="Name" :error="errors.name?.[0]" />
                <TInput id="email" v-model="form.email" label="Email" type="email" :error="errors.email?.[0]" />
                <TPasswordInput
                    id="password"
                    v-model="form.password"
                    label="Password"
                    :placeholder="editingUser ? 'Leave blank to keep the current one' : ''"
                    :helper="editingUser ? 'Only set this if you are resetting their password.' : null"
                    :error="errors.password?.[0]"
                />
                <TSelect v-model="form.role" label="Role" :options="ROLE_OPTIONS" :error="errors.role?.[0]" />
                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" :loading="saving" @click="save">
                    {{ editingUser ? 'Save changes' : 'Create account' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
