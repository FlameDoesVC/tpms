<script setup>
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@/composables/useForm';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const emit = defineEmits(['claimed', 'skipped']);
const dismissed = ref(false);
const mode = ref('create'); // 'create' | 'login'

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const loginForm = useForm({
    email: '',
    password: '',
});

const submitCreate = () => {
    createForm.processing = true;
    createForm.errors = {};
    auth.claimAccount(createForm)
        .then(() => emit('claimed'))
        .catch((error) => {
            if (error.response?.status === 422) {
                createForm.errors = error.response.data.errors ?? {};
                for (const key in createForm.errors) {
                    if (Array.isArray(createForm.errors[key])) createForm.errors[key] = createForm.errors[key][0];
                }
            }
        })
        .finally(() => (createForm.processing = false));
};

const submitLogin = () => {
    loginForm.processing = true;
    loginForm.errors = {};
    auth.loginAndMergeGuest(loginForm)
        .then(() => emit('claimed'))
        .catch((error) => {
            if (error.response?.status === 422) {
                loginForm.errors = error.response.data.errors ?? {};
                for (const key in loginForm.errors) {
                    if (Array.isArray(loginForm.errors[key])) loginForm.errors[key] = loginForm.errors[key][0];
                }
            }
        })
        .finally(() => (loginForm.processing = false));
};

const skip = () => {
    dismissed.value = true;
    emit('skipped');
};
</script>

<template>
    <div v-if="!dismissed" class="rounded-lg border border-indigo-100 bg-indigo-50 p-6">
        <h3 class="font-semibold text-indigo-900">Save your booking to an account</h3>
        <p class="mt-1 text-sm text-indigo-800">
            You checked out as a guest.
            {{ mode === 'create'
                ? 'Create an account so you can log back in later to manage this booking.'
                : 'Log in to an existing account and this booking will move over to it.' }}
        </p>

        <div class="mt-4 flex gap-4 border-b border-indigo-200 text-sm font-medium">
            <button
                type="button"
                @click="mode = 'create'"
                class="pb-2"
                :class="mode === 'create' ? 'border-b-2 border-indigo-600 text-indigo-900' : 'text-indigo-400'"
            >
                Create account
            </button>
            <button
                type="button"
                @click="mode = 'login'"
                class="pb-2"
                :class="mode === 'login' ? 'border-b-2 border-indigo-600 text-indigo-900' : 'text-indigo-400'"
            >
                Log in instead
            </button>
        </div>

        <form v-if="mode === 'create'" @submit.prevent="submitCreate" class="mt-4 space-y-4">
            <div>
                <InputLabel for="claim_name" value="Name" />
                <TextInput id="claim_name" v-model="createForm.name" class="mt-1 block w-full" required />
                <InputError :message="createForm.errors.name" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_email" value="Email" />
                <TextInput id="claim_email" type="email" v-model="createForm.email" class="mt-1 block w-full" required />
                <InputError :message="createForm.errors.email" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_password" value="Password" />
                <TextInput id="claim_password" type="password" v-model="createForm.password" class="mt-1 block w-full" required />
                <InputError :message="createForm.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_password_confirmation" value="Confirm Password" />
                <TextInput id="claim_password_confirmation" type="password" v-model="createForm.password_confirmation" class="mt-1 block w-full" required />
            </div>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="createForm.processing">Save Account</PrimaryButton>
                <SecondaryButton type="button" @click="skip">Skip for now</SecondaryButton>
            </div>
        </form>

        <form v-else @submit.prevent="submitLogin" class="mt-4 space-y-4">
            <div>
                <InputLabel for="login_email" value="Email" />
                <TextInput id="login_email" type="email" v-model="loginForm.email" class="mt-1 block w-full" required />
                <InputError :message="loginForm.errors.email" class="mt-2" />
            </div>

            <div>
                <InputLabel for="login_password" value="Password" />
                <TextInput id="login_password" type="password" v-model="loginForm.password" class="mt-1 block w-full" required />
                <InputError :message="loginForm.errors.password" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="loginForm.processing">Log In</PrimaryButton>
                <SecondaryButton type="button" @click="skip">Skip for now</SecondaryButton>
            </div>
        </form>
    </div>
</template>
