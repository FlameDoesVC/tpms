<script setup>
/**
 * A card in a MediaRail. The picture IS the card - title and meta sit on the
 * image under a scrim rather than in a panel below it, which is what lets the
 * artwork be the size the user asked for.
 *
 * Nothing in this system has a photo uploaded yet, so the no-image case is
 * the designed case, not a fallback: a small seascape whose water colour is
 * chosen by the card's position, so a row of them reads as a set of places
 * rather than three identical grey rectangles.
 */
import { computed } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    to: { type: [Object, String], required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    subtitleIcon: { type: String, default: 'pin' },
    imageUrl: { type: String, default: null },
    icon: { type: String, default: 'sparkle' },
    index: { type: Number, default: 0 },
});

// Written out, never built as `plate-${n}`. Tailwind tree-shakes the custom
// classes in @layer components against the class names it can actually find in
// the templates, so an interpolated name is compiled away and the plate is
// left with no --plate-a/--plate-b - which makes the whole gradient invalid
// and the card renders flat.
const PLATES = ['plate-1', 'plate-2', 'plate-3'];
const plate = computed(() => PLATES[props.index % PLATES.length]);
</script>

<template>
    <router-link
        :to="to"
        class="lift elevated group/card relative block w-[min(86vw,23rem)] overflow-hidden rounded-xl border bg-surface"
        draggable="false"
    >
        <div class="relative aspect-[16/10] w-full overflow-hidden">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="title"
                loading="lazy"
                draggable="false"
                class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover/card:scale-[1.06]"
            />
            <div
                v-else
                class="wave-plate grid h-full w-full place-items-center transition-transform duration-500 ease-out group-hover/card:scale-[1.06]"
                :class="plate"
            >
                <TIcon :name="icon" :size="34" class="text-white/35" />
            </div>

            <!-- Scrim. Tall and soft: a hard bar across the bottom crops the
                 artwork, a gradient lets it keep going behind the words. -->
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 h-3/4 bg-gradient-to-t from-black/90 via-black/45 to-transparent"
                aria-hidden="true"
            />

            <div class="absolute inset-x-0 bottom-0 p-4">
                <slot name="meta" />
                <h3 class="mt-1.5 text-lg font-semibold leading-tight tracking-tight text-white">
                    {{ title }}
                </h3>
                <p v-if="subtitle" class="mt-1 flex items-center gap-1 text-xs text-white/70">
                    <TIcon :name="subtitleIcon" :size="12" class="shrink-0" />
                    <span class="truncate">{{ subtitle }}</span>
                </p>

                <!-- Held back until the card is hovered or focused, so the
                     row stays calm and the detail is there when you go
                     looking for it. Clamped to two lines so the expanded
                     height is known and nothing clips mid-word. -->
                <div
                    v-if="$slots.more"
                    class="max-h-0 overflow-hidden opacity-0 transition-all duration-300 ease-out group-hover/card:max-h-16 group-hover/card:opacity-100 group-focus-visible/card:max-h-16 group-focus-visible/card:opacity-100"
                >
                    <!-- Clamped by hand: this Tailwind predates line-clamp-*
                         and the plugin isn't installed, so the utility would
                         compile to nothing and let a long blurb clip
                         mid-line. -->
                    <p
                        class="overflow-hidden pt-2 text-sm leading-snug text-white/75 [-webkit-box-orient:vertical] [-webkit-line-clamp:2] [display:-webkit-box]"
                    >
                        <slot name="more" />
                    </p>
                </div>
            </div>
        </div>
    </router-link>
</template>
