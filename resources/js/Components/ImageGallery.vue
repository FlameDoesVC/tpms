<script setup>
/**
 * The photographs for a hotel, room type or attraction, with a full-screen
 * viewer.
 *
 * `hero` is the detail-page header: one large frame with the rest stacked
 * beside it. `compact` is the strip on a room-type card. Both open the same
 * lightbox, which is the only reason a guest can see a room at full size.
 *
 * With nothing uploaded the component still has to look deliberate, so it falls
 * back to the same seascape plate RailCard uses.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    images: { type: Array, default: () => [] },
    alt: { type: String, default: '' },
    variant: { type: String, default: 'hero' }, // hero | compact
    icon: { type: String, default: 'image' },
    // Picks the fallback seascape, so two cards in a column are not identical.
    index: { type: Number, default: 0 },
});

// Written out rather than built as `plate-${n}`: Tailwind only keeps the
// custom classes it can find literally in a template, so an interpolated name
// compiles away and the gradient loses its colour stops.
const PLATES = ['plate-1', 'plate-2', 'plate-3'];
const plate = computed(() => PLATES[props.index % PLATES.length]);

const urls = computed(() => props.images.map((image) => image.url).filter(Boolean));
const hasImages = computed(() => urls.value.length > 0);

// Up to four thumbnails beside the hero frame; anything beyond that is reachable
// through the "+N photos" button.
const thumbnails = computed(() => urls.value.slice(1, 5));
const hiddenCount = computed(() => Math.max(0, urls.value.length - 5));

const lightboxIndex = ref(null);
const isOpen = computed(() => lightboxIndex.value !== null);

const open = (index) => {
    if (!hasImages.value) return;
    lightboxIndex.value = index;
};
const close = () => {
    lightboxIndex.value = null;
};
const step = (delta) => {
    if (!isOpen.value) return;
    const total = urls.value.length;
    lightboxIndex.value = (lightboxIndex.value + delta + total) % total;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') close();
    else if (event.key === 'ArrowRight') step(1);
    else if (event.key === 'ArrowLeft') step(-1);
};

// The viewer covers the page, so the page behind it must not scroll with it.
watch(isOpen, (openNow) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = openNow ? 'hidden' : '';
    if (openNow) window.addEventListener('keydown', onKeydown);
    else window.removeEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div>
        <!-- Hero: the photograph leads, at the full width of the page, with the
             rest of the set as a strip beneath it. -->
        <div v-if="variant === 'hero'" class="space-y-2">
            <button
                type="button"
                class="elevated group relative block w-full overflow-hidden rounded-xl border"
                :disabled="!hasImages"
                :aria-label="hasImages ? `View photos of ${alt}` : undefined"
                @click="open(0)"
            >
                <!-- Full width with an automatic height, so an image keeps its
                     own proportions instead of being forced into a fixed crop.
                     max-height is the only thing that ever crops it, and only
                     for something unusually tall; min-height holds the space
                     before the image has loaded, so the page doesn't jump. -->
                <img
                    v-if="hasImages"
                    :src="urls[0]"
                    :alt="alt"
                    class="h-auto max-h-[min(55vh,34rem)] min-h-[14rem] w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]"
                />
                <!-- The placeholder has no intrinsic ratio to lay itself out
                     from, so it gets an explicit height in the same range. -->
                <div
                    v-else
                    class="wave-plate grid h-[min(38vh,20rem)] min-h-[14rem] w-full place-items-center"
                    :class="plate"
                >
                    <TIcon :name="icon" :size="40" class="text-white/35" />
                </div>

                <!-- Always shown, not hover-revealed: on a touch screen there is
                     no hover, and this badge is the only thing advertising that
                     the photograph opens. -->
                <span
                    v-if="hasImages"
                    class="pointer-events-none absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-lg bg-black/60 px-2.5 py-1.5 text-xs font-medium text-white transition-colors group-hover:bg-black/75"
                >
                    <TIcon name="search" :size="13" />
                    {{ urls.length > 1 ? `View all ${urls.length} photos` : 'View photo' }}
                </span>
            </button>

            <div v-if="thumbnails.length" class="grid grid-cols-4 gap-2">
                <button
                    v-for="(url, i) in thumbnails"
                    :key="url"
                    type="button"
                    class="elevated group relative block aspect-[4/3] w-full overflow-hidden rounded-xl border"
                    :aria-label="`View photo ${i + 2} of ${alt}`"
                    @click="open(i + 1)"
                >
                    <img
                        :src="url"
                        :alt="`${alt} photo ${i + 2}`"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.06]"
                    />
                    <span
                        v-if="i === thumbnails.length - 1 && hiddenCount"
                        class="absolute inset-0 grid place-items-center bg-black/55 text-sm font-semibold text-white"
                    >
                        +{{ hiddenCount }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Compact: a single frame, sized by whatever lays it out. -->
        <button
            v-else
            type="button"
            class="group relative block h-full w-full overflow-hidden rounded-xl border"
            :disabled="!hasImages"
            :aria-label="hasImages ? `View photos of ${alt}` : undefined"
            @click="open(0)"
        >
            <img
                v-if="hasImages"
                :src="urls[0]"
                :alt="alt"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]"
            />
            <div v-else class="wave-plate grid h-full w-full place-items-center" :class="plate">
                <TIcon :name="icon" :size="26" class="text-white/35" />
            </div>
            <span
                v-if="urls.length > 1"
                class="absolute bottom-1.5 right-1.5 rounded-lg bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white"
            >
                {{ urls.length }} photos
            </span>
        </button>

        <!-- Teleported so the sticky booking bars, which sit at z-20, cannot
             paint over the viewer. -->
        <Teleport to="body">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="`Photos of ${alt}`"
                @click.self="close"
            >
                <img
                    :src="urls[lightboxIndex]"
                    :alt="`${alt} photo ${lightboxIndex + 1}`"
                    class="max-h-[85vh] max-w-full rounded-xl object-contain"
                />

                <button
                    type="button"
                    class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="Close photo viewer"
                    @click="close"
                >
                    <TIcon name="x" :size="20" />
                </button>

                <template v-if="urls.length > 1">
                    <button
                        type="button"
                        class="absolute left-4 grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"
                        aria-label="Previous photo"
                        @click.stop="step(-1)"
                    >
                        <TIcon name="chevronLeft" :size="20" />
                    </button>
                    <button
                        type="button"
                        class="absolute right-4 grid h-10 w-10 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"
                        aria-label="Next photo"
                        @click.stop="step(1)"
                    >
                        <TIcon name="chevronRight" :size="20" />
                    </button>
                    <p class="absolute bottom-5 text-sm text-white/70">
                        {{ lightboxIndex + 1 }} / {{ urls.length }}
                    </p>
                </template>
            </div>
        </Teleport>
    </div>
</template>
