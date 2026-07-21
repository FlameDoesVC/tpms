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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.processing = true;
    form.errors = {};
    auth.claimAccount(form)
        .then(() => emit('claimed'))
        .catch((error) => {
            if (error.response?.status === 422) {
                form.errors = error.response.data.errors ?? {};
                for (const key in form.errors) {
                    if (Array.isArray(form.errors[key])) form.errors[key] = form.errors[key][0];
                }
            }
        })
        .finally(() => (form.processing = false));
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
            You checked out as a guest. Add your details so you can log back in later to manage this booking.
        </p>

        <form @submit.prevent="submit" class="mt-4 space-y-4">
            <div>
                <InputLabel for="claim_name" value="Name" />
                <TextInput id="claim_name" v-model="form.name" class="mt-1 block w-full" required />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_email" value="Email" />
                <TextInput id="claim_email" type="email" v-model="form.email" class="mt-1 block w-full" required />
                <InputError :message="form.errors.email" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_password" value="Password" />
                <TextInput id="claim_password" type="password" v-model="form.password" class="mt-1 block w-full" required />
                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="claim_password_confirmation" value="Confirm Password" />
                <TextInput id="claim_password_confirmation" type="password" v-model="form.password_confirmation" class="mt-1 block w-full" required />
            </div>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="form.processing">Save Account</PrimaryButton>
                <SecondaryButton type="button" @click="skip">Skip for now</SecondaryButton>
            </div>
        </form>
    </div>
</template>
