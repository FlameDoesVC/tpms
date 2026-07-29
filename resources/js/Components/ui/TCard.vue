<script setup>
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    interactive: { type: Boolean, default: false },
    padding: { type: Boolean, default: true },
    // Optional leading icon for the header, so sections are scannable.
    icon: { type: String, default: null },
    title: { type: String, default: null },
    // 'flat' keeps the old border-only look where cards sit inside a card.
    elevation: { type: String, default: 'raised' },
});
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border bg-surface"
        :class="[
            elevation === 'raised' ? 'elevated' : '',
            interactive ? 'lift cursor-pointer' : '',
        ]"
    >
        <div v-if="$slots.header || title" class="flex items-center gap-2.5 border-b px-5 py-3.5">
            <span v-if="icon" class="grid h-7 w-7 place-items-center rounded bg-primary-soft text-primary">
                <TIcon :name="icon" :size="16" />
            </span>
            <slot name="header">
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
            </slot>
            <div v-if="$slots.headerAction" class="ml-auto">
                <slot name="headerAction" />
            </div>
        </div>
        <div :class="padding ? 'p-5' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t bg-surface-hover px-5 py-3">
            <slot name="footer" />
        </div>
    </div>
</template>
