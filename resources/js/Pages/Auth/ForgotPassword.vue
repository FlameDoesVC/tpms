<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useForm } from '@/composables/useForm';
import { ref } from 'vue';

const status = ref('');

const form = useForm({ email: '' });

const submit = () => {
    form.post('/forgot-password', {
        onSuccess: (response) => {
            status.value = response.data.status || 'We have emailed your password reset link.';
        },
    });
};
</script>

<template>
    <GuestLayout>
        <p class="mb-4 text-sm text-foreground-secondary">
            Forgot your password? No problem. Enter your email address and we'll send you a reset link.
        </p>

        <div v-if="status" class="mb-4 rounded-lg bg-success-soft p-3 text-sm font-medium text-success">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <TInput id="email" type="email" label="Email" v-model="form.email" :error="form.errors.email" required autofocus autocomplete="username" />

            <div class="flex justify-end">
                <TButton :loading="form.processing">Email Password Reset Link</TButton>
            </div>
        </form>
    </GuestLayout>
</template>
