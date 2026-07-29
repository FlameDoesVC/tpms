<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TInput from '@/Components/ui/TInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useForm } from '@/composables/useForm';
import { useRouter } from 'vue-router';

const router = useRouter();

const form = useForm({ password: '' });

const submit = () => {
    form.post('/confirm-password', {
        onSuccess: () => router.push({ name: 'dashboard' }),
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <p class="mb-4 text-sm text-foreground-secondary">
            This is a secure area of the application. Please confirm your password before continuing.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <TInput id="password" type="password" label="Password" v-model="form.password" :error="form.errors.password" required autocomplete="current-password" autofocus />

            <div class="flex justify-end">
                <TButton :loading="form.processing">Confirm</TButton>
            </div>
        </form>
    </GuestLayout>
</template>
