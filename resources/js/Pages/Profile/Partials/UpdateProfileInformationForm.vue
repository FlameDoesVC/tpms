<script setup>
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const form = useForm({
    name: auth.user?.name ?? '',
    email: auth.user?.email ?? '',
});

const submit = () => {
    form.patch('/profile', {
        onSuccess: (response) => {
            auth.setUser(response.data.user);
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-foreground">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-foreground-secondary">
                Update your account's profile information and email address.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
            <!-- Side by side from sm: each field lands near 540px in the wide
                 profile column, which is what a text field wants - stacked,
                 each one stretched to the full 1100px and read as wireframe. -->
            <div class="grid gap-4 sm:grid-cols-2">
            <TInput
                id="name"
                v-model="form.name"
                label="Name"
                :error="form.errors.name"
                type="text"
                required
                autofocus
                autocomplete="name"
            />

            <TInput
                id="email"
                v-model="form.email"
                label="Email"
                :error="form.errors.email"
                type="email"
                required
                autocomplete="username"
            />
            </div>

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
