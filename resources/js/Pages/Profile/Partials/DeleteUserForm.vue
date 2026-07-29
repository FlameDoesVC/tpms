<script setup>
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TModal from '@/Components/ui/TModal.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { nextTick, ref } from 'vue';
import { useRouter } from 'vue-router';

const auth = useAuthStore();
const router = useRouter();

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete('/profile', {
        onSuccess: () => {
            closeModal();
            auth.clearUser();
            router.push({ name: 'welcome' });
        },
        onError: () => passwordInput.value?.focus(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-foreground">
                Delete Account
            </h2>

            <p class="mt-1 text-sm text-foreground-secondary">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <TButton variant="danger" @click="confirmUserDeletion">Delete Account</TButton>

        <TModal v-model:show="confirmingUserDeletion" max-width="md" @close="closeModal">
            <template #title>Are you sure you want to delete your account?</template>

            <p class="text-sm text-foreground-secondary">
                Once your account is deleted, all of its resources and data
                will be permanently deleted. Please enter your password to
                confirm you would like to permanently delete your account.
            </p>

            <div class="mt-4">
                <TInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    :error="form.errors.password"
                    type="password"
                    placeholder="Password"
                    @keyup.enter="deleteUser"
                />
            </div>

            <template #footer>
                <TButton variant="secondary" @click="closeModal">
                    Cancel
                </TButton>

                <TButton
                    variant="danger"
                    :loading="form.processing"
                    @click="deleteUser"
                >
                    Delete Account
                </TButton>
            </template>
        </TModal>
    </section>
</template>
