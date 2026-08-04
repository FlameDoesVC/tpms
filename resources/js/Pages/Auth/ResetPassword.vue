<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPasswordInput from '@/Components/ui/TPasswordInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useForm } from '@/composables/useForm';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();

const form = useForm({
    token: route.params.token,
    email: route.query.email || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onSuccess: () => router.push({ name: 'login' }),
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <form @submit.prevent="submit" class="space-y-4">
            <TInput id="email" type="email" label="Email" v-model="form.email" :error="form.errors.email" required autofocus autocomplete="username" />
            <TPasswordInput id="password" label="New Password" v-model="form.password" :error="form.errors.password" required />
            <TPasswordInput
                id="password_confirmation"
                label="Confirm Password"
                v-model="form.password_confirmation"
                :error="form.errors.password_confirmation"
                :confirms="form.password"
                required
            />

            <div class="flex justify-end">
                <TButton :loading="form.processing">Reset Password</TButton>
            </div>
        </form>
    </GuestLayout>
</template>
