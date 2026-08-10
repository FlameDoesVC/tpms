<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useAnchoredPanel } from '@/composables/useAnchoredPanel';

const props = defineProps({
    align: { type: String, default: 'right' },
    width: { type: String, default: '48' },
    // Menus of links should close the moment one is picked. A panel the user
    // works inside first - ticking checkboxes, say - must not.
    closeOnContentClick: { type: Boolean, default: true },
});

const open = ref(false);
const anchorRef = ref(null);
const panelRef = ref(null);

// Pixel widths rather than `w-*` classes, because a teleported panel is sized
// through its inline position style.
const WIDTHS = { '32': 128, '48': 192, '56': 224, '80': 320 };
const panelWidth = computed(() => WIDTHS[props.width] ?? WIDTHS['48']);

const onContentClick = () => {
    if (props.closeOnContentClick) open.value = false;
};

const close = () => { open.value = false; };

// The menu is teleported to <body> so it can't be clipped by an ancestor's
// `overflow-hidden` - a TModal dialog, a TCard, a horizontally scrolling table -
// nor stack beneath a modal's own layer.
const { panelStyle } = useAnchoredPanel({
    open,
    anchorRef,
    panelRef,
    align: props.align === 'left' ? 'left' : 'right',
    width: panelWidth,
    gap: 8, // mt-2
    estimatedHeight: 200,
    onClose: close,
});

// Escape closes the menu without reaching a host modal's own Escape handler.
const onEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        e.stopPropagation();
        open.value = false;
    }
};

defineExpose({ close });

onMounted(() => document.addEventListener('keydown', onEscape, true));
onUnmounted(() => document.removeEventListener('keydown', onEscape, true));
</script>

<template>
    <div ref="anchorRef" class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="open"
                    ref="panelRef"
                    class="elevated-lg fixed z-[60] rounded-xl border bg-surface py-1"
                    :class="align === 'left' ? 'origin-top-left' : 'origin-top-right'"
                    :style="panelStyle"
                    @click="onContentClick"
                >
                    <slot name="content" />
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
