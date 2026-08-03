<script setup>
import { ref } from 'vue';
import TInput from '@/Components/ui/TInput.vue';
import TButton from '@/Components/ui/TButton.vue';
import { formatMoney } from '@/utils/format';

const props = defineProps({
    total: { type: Number, required: true },
    processing: { type: Boolean, default: false },
    // Server-side failure, owned by the parent that made the call.
    error: { type: String, default: '' },
    // False when nothing is payable online (an all-cash ferry cart) - the
    // fields go away but the confirm action stays.
    requireCard: { type: Boolean, default: true },
    submitLabel: { type: String, default: null },
});

const emit = defineEmits(['submit']);

const cardNumber = ref('');
const expiry = ref('');
const cvv = ref('');
const localError = ref('');

// Card values are deliberately never emitted. Payment is simulated end to end
// (the server only flips a status), so keeping them inside this component
// means there is exactly one place to replace when a real gateway lands.
const submit = () => {
    localError.value = '';

    if (props.requireCard && (!cardNumber.value || !expiry.value || !cvv.value)) {
        localError.value = 'Fill in all payment fields.';
        return;
    }

    emit('submit');
};
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <template v-if="requireCard">
            <TInput
                v-model="cardNumber"
                label="Card number"
                placeholder="4242 4242 4242 4242"
                inputmode="numeric"
                autocomplete="cc-number"
            />
            <div class="flex gap-4">
                <div class="flex-1">
                    <TInput
                        v-model="expiry"
                        label="Expiry"
                        placeholder="MM/YY"
                        inputmode="numeric"
                        autocomplete="cc-exp"
                    />
                </div>
                <div class="w-24">
                    <TInput
                        v-model="cvv"
                        label="CVV"
                        placeholder="123"
                        inputmode="numeric"
                        autocomplete="cc-csc"
                    />
                </div>
            </div>
        </template>
        <slot v-else name="no-payment">
            <p class="text-sm text-foreground-muted">No online payment needed for this order.</p>
        </slot>

        <p v-if="localError || error" role="alert" class="text-sm text-danger">
            {{ localError || error }}
        </p>

        <TButton type="submit" :loading="processing" class="w-full sm:w-auto">
            {{ submitLabel ?? (total > 0 ? `Pay ${formatMoney(total)}` : 'Confirm booking') }}
        </TButton>
    </form>
</template>
