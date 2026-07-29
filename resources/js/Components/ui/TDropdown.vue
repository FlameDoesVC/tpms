<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

defineProps({
    align: { type: String, default: 'right' },
    width: { type: String, default: '48' },
});

const open = ref(false);
const wrapperRef = ref(null);

const widthClass = {
    '32': 'w-32',
    '48': 'w-48',
    '56': 'w-56',
};

const onClickOutside = (e) => {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="wrapperRef" class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="elevated-lg absolute z-50 mt-2 origin-top-right rounded-xl border bg-surface py-1"
                :class="[
                    widthClass[width] ?? 'w-48',
                    align === 'left' ? 'left-0' : 'right-0',
                ]"
                @click="open = false"
            >
                <slot name="content" />
            </div>
        </Transition>
    </div>
</template>
