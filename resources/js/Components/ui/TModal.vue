<script setup>
import { watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    maxWidth: { type: String, default: 'md' },
    closeable: { type: Boolean, default: true },
});

const show = defineModel('show', { type: Boolean, default: false });
const emit = defineEmits(['close']);

const maxWidthClass = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
    '5xl': 'sm:max-w-5xl',
};

const close = () => {
    if (props.closeable) {
        show.value = false;
        emit('close');
    }
};

watch(show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

const onEscape = (e) => {
    if (e.key === 'Escape' && show.value) {
        e.preventDefault();
        close();
    }
};

onMounted(() => document.addEventListener('keydown', onEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', onEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
                appear
            >
                <div v-show="show" class="scrim fixed inset-0 backdrop-blur-sm" @click="close" />
            </Transition>

            <!-- Centred rather than parked near the top: a short dialog pinned
                 high leaves all its slack in one lump underneath, which reads as
                 the page having run out rather than as deliberate space.
                 `min-h-full` on the flex row (with the scroll on the parent) is
                 what keeps a dialog taller than the viewport from having its top
                 clipped, which plain `items-center` would do. -->
            <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">
                <Transition
                    enter-active-class="ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    appear
                >
                    <div
                        v-show="show"
                        class="elevated-lg relative w-full transform overflow-hidden rounded-xl border bg-surface transition-all"
                        :class="maxWidthClass[maxWidth]"
                    >
                        <div class="flex items-center justify-between border-b px-6 py-4">
                            <h3 v-if="$slots.title" class="text-lg font-semibold text-foreground">
                                <slot name="title" />
                            </h3>
                            <button
                                v-if="closeable"
                                type="button"
                                class="rounded-lg p-1 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                                @click="close"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="px-6 py-4">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="flex justify-end gap-2 border-t bg-surface-hover px-6 py-3">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </Teleport>
</template>
