<script setup>
defineProps({
    maxWidth: {
        type: String,
        default: 'md',
    },
});

const show = defineModel('show', { type: Boolean, default: false });

const maxWidthClass = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-gray-500 opacity-75" @click="show = false" />
            <div
                class="relative mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full"
                :class="maxWidthClass[maxWidth]"
            >
                <div v-if="$slots.title" class="border-b border-gray-200 px-6 py-4 text-lg font-medium text-gray-900">
                    <slot name="title" />
                </div>
                <div class="px-6 py-4">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="flex justify-end gap-2 bg-gray-50 px-6 py-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
