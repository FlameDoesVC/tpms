<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPasswordInput from '@/Components/ui/TPasswordInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    const options = {
        onSuccess: (response) => {
            auth.setUser(response.data.user);
            router.push(route.query.redirect || { name: 'dashboard' });
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    };

    if (auth.isGuest) {
        form.patch('/api/guest/claim', options);
    } else {
        form.post('/register', options);
    }
};
</script>

<template>
    <GuestLayout>
        <p v-if="auth.isGuest" class="mb-4 rounded-lg bg-primary-soft p-3 text-sm text-primary">
            You're browsing as a guest — creating an account will save your current booking to it.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <TInput id="name" type="text" label="Name" v-model="form.name" :error="form.errors.name" required autofocus autocomplete="name" />
            <TInput id="email" type="email" label="Email" v-model="form.email" :error="form.errors.email" required autocomplete="username" />
            <TPasswordInput id="password" label="Password" v-model="form.password" :error="form.errors.password" required />
            <TPasswordInput
                id="password_confirmation"
                label="Confirm Password"
                v-model="form.password_confirmation"
                :error="form.errors.password_confirmation"
                :confirms="form.password"
                required
            />

            <div class="flex items-center justify-end gap-3">
                <router-link
                    :to="{ name: 'login', query: route.query.redirect ? { redirect: route.query.redirect } : {} }"
                    class="text-sm text-foreground-secondary underline hover:text-foreground"
                >
                    Already registered?
                </router-link>
                <TButton :loading="form.processing">Register</TButton>
            </div>
        </form>
    </GuestLayout>
</template>
