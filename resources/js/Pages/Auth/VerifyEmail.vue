<script setup>
import { computed, ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const auth = useAuthStore();
const status = ref('');

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification', {
        onSuccess: (response) => {
            status.value = response.data.status || 'verification-link-sent';
        },
    });
};

const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

const logout = async () => {
    await axios.post('/logout');
    auth.clearUser();
    router.push({ name: 'login' });
};
</script>

<template>
    <GuestLayout>
        <p class="mb-4 text-sm text-foreground-secondary">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
        </p>

        <div v-if="verificationLinkSent" class="mb-4 rounded-lg bg-success-soft p-3 text-sm font-medium text-success">
            A new verification link has been sent to the email address you provided during registration.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <TButton :loading="form.processing">Resend Verification Email</TButton>

                <button
                    @click="logout"
                    type="button"
                    class="text-sm text-foreground-secondary underline hover:text-foreground"
                >
                    Log Out
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
