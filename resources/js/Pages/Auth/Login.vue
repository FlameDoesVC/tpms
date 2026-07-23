<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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

// A guest checkout session logging in here merges its bookings into the
// target account instead of just starting a normal separate session.
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
        <p v-if="auth.isGuest" class="mb-4 rounded-md bg-indigo-50 p-3 text-sm text-indigo-800">
            You're browsing as a guest - logging in will move your current booking to this account.
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <router-link
                    :to="{ name: 'register', query: route.query.redirect ? { redirect: route.query.redirect } : {} }"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Need an account?
                </router-link>

                <div class="flex items-center">
                    <router-link
                        :to="{ name: 'password.request' }"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Forgot your password?
                    </router-link>

                    <PrimaryButton
                        class="ms-4"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Log in
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
