<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();

const typeClass = {
    error: 'bg-red-600',
    success: 'bg-green-600',
    info: 'bg-gray-800',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex items-center justify-between gap-4 rounded-md px-4 py-3 text-sm text-white shadow-lg"
                :class="typeClass[toast.type] ?? typeClass.info"
            >
                <span>{{ toast.message }}</span>
                <button class="text-white/80 hover:text-white" @click="removeToast(toast.id)">&times;</button>
            </div>
        </div>
    </Teleport>
</template>
