<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    align: { type: String, default: 'right' },
    width: { type: String, default: '48' },
    // Menus of links should close the moment one is picked. A panel the user
    // works inside first - ticking checkboxes, say - must not.
    closeOnContentClick: { type: Boolean, default: true },
});

const open = ref(false);
const wrapperRef = ref(null);

const widthClass = {
    '32': 'w-32',
    '48': 'w-48',
    '56': 'w-56',
    '80': 'w-80',
};

const onContentClick = () => {
    if (props.closeOnContentClick) open.value = false;
};

const close = () => { open.value = false; };

const onEscape = (e) => {
    if (open.value && e.key === 'Escape') open.value = false;
};

defineExpose({ close });

const onClickOutside = (e) => {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside);
    document.addEventListener('keydown', onEscape);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});
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
                @click="onContentClick"
            >
                <slot name="content" />
            </div>
        </Transition>
    </div>
</template>
