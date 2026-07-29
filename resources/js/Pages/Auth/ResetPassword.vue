<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
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
            <TInput id="password" type="password" label="Password" v-model="form.password" :error="form.errors.password" required autocomplete="new-password" />
            <TInput id="password_confirmation" type="password" label="Confirm Password" v-model="form.password_confirmation" :error="form.errors.password_confirmation" required autocomplete="new-password" />

            <div class="flex justify-end">
                <TButton :loading="form.processing">Reset Password</TButton>
            </div>
        </form>
    </GuestLayout>
</template>
