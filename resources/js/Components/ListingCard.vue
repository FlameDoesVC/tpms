<script setup>
/**
 * One result on a browse page - a hotel or a park attraction.
 *
 * The whole card is the link. These pages used to expand a card in place to
 * reveal the booking controls, which is why nothing had room for a photograph
 * or a description; now the card's only job is to be worth opening.
 */
import { computed } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    to: { type: [Object, String], required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    imageUrl: { type: String, default: null },
    icon: { type: String, default: 'image' },
    index: { type: Number, default: 0 },
    cta: { type: String, default: 'View details' },
});

// Spelled out, never interpolated - Tailwind drops custom classes it cannot
// find literally in a template, which would leave the plate without its colour
// stops. Same reasoning as RailCard.
const PLATES = ['plate-1', 'plate-2', 'plate-3'];
const plate = computed(() => PLATES[props.index % PLATES.length]);
</script>

<template>
    <router-link
        :to="to"
        class="lift elevated group flex flex-col overflow-hidden rounded-xl border bg-surface sm:flex-row"
    >
        <div class="relative h-40 shrink-0 overflow-hidden sm:h-auto sm:w-56">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="title"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.05]"
            />
            <div v-else class="wave-plate grid h-full w-full place-items-center" :class="plate">
                <TIcon :name="icon" :size="32" class="text-white/35" />
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-3 p-4 sm:flex-row sm:items-start sm:gap-5">
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold leading-tight text-foreground">{{ title }}</h3>

                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-foreground-muted">
                    <slot name="meta" />
                </div>

                <!-- Clamped by hand: this Tailwind predates line-clamp-* and the
                     plugin is not installed, so the utility compiles to nothing
                     and a long description runs the card to any height. -->
                <p
                    v-if="description"
                    class="mt-2 overflow-hidden text-sm leading-snug text-foreground-secondary [-webkit-box-orient:vertical] [-webkit-line-clamp:2] [display:-webkit-box]"
                >
                    {{ description }}
                </p>

                <div v-if="$slots.chips" class="mt-3">
                    <slot name="chips" />
                </div>
            </div>

            <div class="flex shrink-0 items-end justify-between gap-3 sm:flex-col sm:items-end sm:justify-start sm:text-right">
                <slot name="price" />
                <span class="inline-flex items-center gap-1 text-sm font-medium text-primary sm:mt-auto">
                    {{ cta }}
                    <TIcon
                        name="chevronRight"
                        :size="14"
                        class="transition-transform group-hover:translate-x-0.5"
                    />
                </span>
            </div>
        </div>
    </router-link>
</template>
