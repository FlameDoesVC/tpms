<script setup>
/**
 * A horizontal rail of media cards: drag it with a mouse, swipe it on touch,
 * page it with the arrows, or drive it from the keyboard.
 *
 * Three details are what make it feel like a rail rather than an overflowing
 * div: it bleeds past the right edge of the shell so the row visibly
 * continues, the scrollbar is replaced by a progress bar the component draws
 * itself, and the ends are masked in CSS rather than covered by a gradient
 * overlay - a mask needs no knowledge of the section's background colour, so
 * the same rail sits correctly on the page and on a raised surface.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    to: { type: [Object, String], default: null },
    linkLabel: { type: String, default: 'See all' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    skeletons: { type: Number, default: 4 },
    emptyText: { type: String, default: '' },
});

const track = ref(null);
const atStart = ref(true);
const atEnd = ref(true);
const scrollable = ref(false);
const dragging = ref(false);
const thumb = ref({ width: 100, left: 0 });

const prefersReducedMotion = () =>
    typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const measure = () => {
    const el = track.value;
    if (!el) return;

    const max = el.scrollWidth - el.clientWidth;
    scrollable.value = max > 8;
    atStart.value = el.scrollLeft <= 4;
    atEnd.value = el.scrollLeft >= max - 4;
    thumb.value = {
        width: el.scrollWidth ? Math.min(100, (el.clientWidth / el.scrollWidth) * 100) : 100,
        left: el.scrollWidth ? (el.scrollLeft / el.scrollWidth) * 100 : 0,
    };
};

// Each end fades only while there is actually something hidden that way, so
// the fade doubles as the "there's more" signal.
const fadeVars = computed(() => ({
    '--fade-l': scrollable.value && !atStart.value ? '2.75rem' : '0px',
    '--fade-r': scrollable.value && !atEnd.value ? '2.75rem' : '0px',
}));

const page = (direction) => {
    const el = track.value;
    if (!el) return;
    el.scrollBy({
        left: direction * Math.max(240, el.clientWidth * 0.82),
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
};

/* ----------------------------- mouse drag ----------------------------- */

let startX = 0;
let startScroll = 0;
let travelled = 0;
let releasedAt = 0;
let active = false;

const onPointerMove = (event) => {
    if (!active) return;
    const el = track.value;
    if (!el) return;

    const dx = event.clientX - startX;
    travelled = Math.max(travelled, Math.abs(dx));
    el.scrollLeft = startScroll - dx;
    if (travelled > 3) event.preventDefault();
};

const endDrag = () => {
    if (!active) return;
    active = false;
    dragging.value = false;
    releasedAt = performance.now();

    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', endDrag);
    window.removeEventListener('pointercancel', endDrag);
};

const onPointerDown = (event) => {
    // Touch and pen already have momentum scrolling that beats anything done
    // by hand here; only a mouse has no way to drag a scroll container.
    if (event.pointerType !== 'mouse' || event.button !== 0) return;
    if (!scrollable.value || !track.value) return;

    active = true;
    travelled = 0;
    startX = event.clientX;
    startScroll = track.value.scrollLeft;
    dragging.value = true;

    // Listeners on the window, not the track: a drag that leaves the rail
    // (or the viewport) still has to keep scrolling and still has to end.
    window.addEventListener('pointermove', onPointerMove, { passive: false });
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);
};

// A drag that happens to finish on top of a card must not also open it.
const onClickCapture = (event) => {
    if (travelled > 6 && performance.now() - releasedAt < 320) {
        event.preventDefault();
        event.stopPropagation();
        travelled = 0;
    }
};

/* ------------------------------ keyboard ------------------------------ */

// Only when the rail itself holds focus - otherwise arrowing between cards
// would scroll the rail out from under the focused card.
const onKeydown = (event) => {
    if (event.target !== track.value) return;
    const el = track.value;

    if (event.key === 'ArrowRight') page(1);
    else if (event.key === 'ArrowLeft') page(-1);
    else if (event.key === 'Home') el.scrollTo({ left: 0, behavior: 'smooth' });
    else if (event.key === 'End') el.scrollTo({ left: el.scrollWidth, behavior: 'smooth' });
    else return;

    event.preventDefault();
};

let resizeObserver = null;

onMounted(() => {
    measure();
    if (typeof ResizeObserver !== 'undefined') {
        // Catches both viewport changes and cards whose images finish loading.
        resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(track.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    endDrag();
});

watch(() => props.items, () => requestAnimationFrame(measure), { deep: false });
watch(() => props.loading, () => requestAnimationFrame(measure));
</script>

<template>
    <section>
        <header class="mb-5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-foreground">{{ title }}</h2>
                <p v-if="hint" class="mt-1 text-sm text-foreground-muted">{{ hint }}</p>
            </div>
            <router-link
                v-if="to"
                :to="to"
                class="group/link inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
            >
                {{ linkLabel }}
                <TIcon
                    name="arrowRight"
                    :size="15"
                    class="transition-transform group-hover/link:translate-x-0.5"
                />
            </router-link>
        </header>

        <p v-if="!loading && items.length === 0 && emptyText" class="text-sm text-foreground-muted">
            {{ emptyText }}
        </p>

        <!-- Negative margins cancel the shell's padding so the rail runs to
             the edge, and the matching padding inside the scroller puts the
             first card back on the page's own left edge. -->
        <div v-else class="group/rail relative -mx-4 sm:-mx-6 lg:-mx-10">
            <ul
                ref="track"
                class="rail-track rail-grab select-none gap-5 px-4 py-2 sm:px-6 lg:px-10"
                :class="{ 'is-dragging': dragging }"
                :style="fadeVars"
                tabindex="0"
                :aria-label="`${title}, scrollable list`"
                @scroll.passive="measure"
                @pointerdown="onPointerDown"
                @click.capture="onClickCapture"
                @keydown="onKeydown"
                @dragstart.prevent
            >
                <template v-if="loading">
                    <li v-for="n in skeletons" :key="`s${n}`" class="rail-card shrink-0">
                        <div
                            class="aspect-[16/10] w-[min(86vw,23rem)] animate-pulse rounded-xl border bg-surface-hover"
                        />
                    </li>
                </template>
                <!-- v-else on a <template>, not on the <li> itself: v-if wins
                     over v-for when both sit on one element, which is the
                     trap that silently renders nothing. -->
                <template v-else>
                    <li v-for="(item, index) in items" :key="item.id ?? index" class="rail-card shrink-0">
                        <slot name="card" :item="item" :index="index" />
                    </li>
                </template>
            </ul>

            <!-- Paging arrows. Kept mounted rather than v-if'd so they can
                 still take focus for a keyboard visitor, and only painted
                 when there is somewhere to page to. -->
            <button
                v-for="side in ['left', 'right']"
                :key="side"
                type="button"
                class="elevated absolute top-1/2 z-10 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full border bg-surface/90 text-foreground backdrop-blur transition-opacity duration-200 hover:bg-surface focus-visible:opacity-100 sm:grid"
                :class="[
                    side === 'left' ? 'left-2 lg:left-5' : 'right-2 lg:right-5',
                    (side === 'left' ? atStart : atEnd) || !scrollable
                        ? 'pointer-events-none opacity-0'
                        : 'opacity-0 group-hover/rail:opacity-100 group-focus-within/rail:opacity-100',
                ]"
                :aria-label="side === 'left' ? `Scroll ${title} back` : `Scroll ${title} forward`"
                :tabindex="(side === 'left' ? atStart : atEnd) || !scrollable ? -1 : 0"
                @click="page(side === 'left' ? -1 : 1)"
            >
                <TIcon :name="side === 'left' ? 'chevronLeft' : 'chevronRight'" :size="20" />
            </button>
        </div>

        <!-- Stands in for the hidden scrollbar: position and proportion of
             what you're looking at, without the scrollbar's weight. -->
        <!-- `bg-border` doesn't exist: the border colour is registered under
             borderColor only, so it has to be read from the token directly. -->
        <div
            v-if="scrollable"
            class="mt-4 h-0.5 w-full overflow-hidden rounded-full bg-[rgb(var(--color-border))]"
        >
            <div
                class="h-full rounded-full bg-primary/50"
                :style="{ width: `${thumb.width}%`, marginLeft: `${thumb.left}%` }"
            />
        </div>
    </section>
</template>
