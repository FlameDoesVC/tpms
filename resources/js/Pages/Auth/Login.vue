<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import TCheckbox from '@/Components/ui/TCheckbox.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    const url = auth.isGuest ? '/api/guest/login' : '/login';
    form.post(url, {
        onSuccess: (response) => {
            auth.setUser(response.data.user);
            router.push(route.query.redirect || { name: 'dashboard' });
        },
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <p v-if="auth.isGuest" class="mb-4 rounded-lg bg-primary-soft p-3 text-sm text-primary">
            You're browsing as a guest — logging in will move your current booking to this account.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <TInput
                id="email"
                type="email"
                label="Email"
                v-model="form.email"
                :error="form.errors.email"
                required
                autofocus
                autocomplete="username"
            />

            <TInput
                id="password"
                type="password"
                label="Password"
                v-model="form.password"
                :error="form.errors.password"
                required
                autocomplete="current-password"
            />

            <TCheckbox v-model="form.remember" label="Remember me" />

            <div class="flex items-center justify-between">
                <router-link
                    :to="{ name: 'register', query: route.query.redirect ? { redirect: route.query.redirect } : {} }"
                    class="text-sm text-foreground-secondary underline hover:text-foreground"
                >
                    Need an account?
                </router-link>

                <div class="flex items-center gap-3">
                    <router-link
                        :to="{ name: 'password.request' }"
                        class="text-sm text-foreground-secondary underline hover:text-foreground"
                    >
                        Forgot password?
                    </router-link>

                    <TButton :loading="form.processing">Log in</TButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
