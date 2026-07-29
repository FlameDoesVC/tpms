<script setup>
import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();

const icons = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z',
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

const typeClasses = {
    success: 'border-success/20 bg-success-soft text-success',
    error: 'border-danger/20 bg-danger-soft text-danger',
    warning: 'border-warning/20 bg-warning-soft text-warning',
    info: 'border-foreground/10 bg-surface text-foreground',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2">
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="flex min-w-[300px] items-start gap-3 rounded-xl border p-4 shadow-lg backdrop-blur-sm"
                    :class="typeClasses[toast.type] ?? typeClasses.info"
                >
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[toast.type] ?? icons.info" />
                    </svg>
                    <span class="flex-1 text-sm font-medium">{{ toast.message }}</span>
                    <button class="shrink-0 rounded-md p-0.5 opacity-60 transition-opacity hover:opacity-100" @click="removeToast(toast.id)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
