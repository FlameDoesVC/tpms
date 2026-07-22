<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    label: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
});

const model = defineModel();
</script>

<template>
    <div>
        <InputLabel v-if="label" :value="label" />
        <select
            v-model="model"
            v-bind="$attrs"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
            <slot />
        </select>
        <InputError :message="error" class="mt-2" />
    </div>
</template>
