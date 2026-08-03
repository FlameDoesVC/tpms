<script setup>
import { computed, onMounted, ref } from 'vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { usePromotionsStore } from '@/stores/promotions';
import { formatDate } from '@/utils/format';

const props = defineProps({
    // Matches a promotion's own category; 'general' offers always show.
    category: { type: String, required: true },
});

const promotionsStore = usePromotionsStore();
const dismissed = ref(false);

// Per-session rather than forever: an offer shouldn't nag someone mid-booking,
// but it also shouldn't be gone permanently after one stray click.
const storageKey = `tpms.promoStrip.dismissed.${props.category}`;

onMounted(() => {
    try {
        dismissed.value = sessionStorage.getItem(storageKey) === '1';
    } catch {
        dismissed.value = false;
    }

    // Fire-and-forget: this is optional content, so a failure just means the
    // strip doesn't render. No error state, no skeleton - reserving space for
    // something that may not exist would shift the page for nothing.
    if (promotionsStore.active.length === 0) {
        promotionsStore.fetchActive().catch(() => {});
    }
});

const dismiss = () => {
    dismissed.value = true;
    try {
        sessionStorage.setItem(storageKey, '1');
    } catch {
        // Session-only anyway; losing the preference is harmless.
    }
};

const relevant = computed(() =>
    promotionsStore.active.filter((p) => p.category === props.category || p.category === 'general')
);

const endsLabel = (promo) => (promo.ends_at ? `Ends ${formatDate(promo.ends_at, { weekday: false })}` : '');
</script>

<template>
    <div v-if="!dismissed && relevant.length" class="relative">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">Offers on now</p>
            <button
                type="button"
                class="rounded p-1 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                aria-label="Hide offers"
                @click="dismiss"
            >
                <TIcon name="x" :size="14" />
            </button>
        </div>

        <ul class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1">
            <li
                v-for="promo in relevant"
                :key="promo.id"
                class="elevated w-72 shrink-0 snap-start overflow-hidden rounded-xl border bg-surface"
            >
                <div class="flex gap-3 p-3">
                    <div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-lg bg-surface-hover text-foreground-muted">
                        <img
                            v-if="promo.image_url"
                            :src="promo.image_url"
                            :alt="promo.title"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                        <TIcon v-else name="sparkle" :size="20" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-foreground">{{ promo.title }}</p>
                        <p v-if="promo.description" class="line-clamp-2 text-xs text-foreground-muted">
                            {{ promo.description }}
                        </p>
                        <TBadge v-if="endsLabel(promo)" variant="warning" size="sm" class="mt-1">
                            {{ endsLabel(promo) }}
                        </TBadge>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
