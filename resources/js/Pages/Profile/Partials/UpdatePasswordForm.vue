<script setup>
import TButton from '@/Components/ui/TButton.vue';
import TPasswordInput from '@/Components/ui/TPasswordInput.vue';
import { useForm } from '@/composables/useForm';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put('/password', {
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-foreground">
                Update Password
            </h2>

            <p class="mt-1 text-sm text-foreground-secondary">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-4">
            <TPasswordInput
                id="current_password"
                ref="currentPasswordInput"
                v-model="form.current_password"
                label="Current Password"
                :error="form.errors.current_password"
                autocomplete="current-password"
                :show-strength="false"
            />

            <TPasswordInput
                id="password"
                ref="passwordInput"
                v-model="form.password"
                label="New Password"
                :error="form.errors.password"
            />

            <TPasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirm Password"
                :error="form.errors.password_confirmation"
                :confirms="form.password"
            />

            <div class="flex items-center gap-4">
                <TButton :loading="form.processing">Save</TButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-foreground-secondary"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
