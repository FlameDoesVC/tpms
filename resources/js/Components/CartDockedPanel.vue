<script setup>
import CartContents from '@/Components/CartContents.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useCartStore } from '@/stores/cart';
import { formatMoney } from '@/utils/format';

const cart = useCartStore();
</script>

<template>
    <!-- Sunken rather than raised: the rail is chrome that follows you between
         pages, so it sits behind the content it's collecting, not on top of it. -->
    <aside
        aria-label="Your itinerary"
        class="hidden lg:sticky lg:top-16 lg:flex lg:h-[calc(100vh-4rem)] lg:w-[19rem] lg:shrink-0 lg:flex-col lg:self-start lg:border-l lg:bg-surface-sunken xl:w-80"
    >
        <div class="border-b bg-surface px-4 py-3">
            <div class="flex items-center gap-2">
                <TIcon name="ticket" :size="17" class="text-primary" />
                <h3 class="text-sm font-semibold tracking-tight text-foreground">Your itinerary</h3>
                <span
                    v-if="cart.count"
                    class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-white"
                >
                    {{ cart.count }}
                </span>
            </div>
            <p v-if="cart.count" class="mt-1 text-xs text-foreground-muted">
                {{ cart.count }} item{{ cart.count === 1 ? '' : 's' }} · {{ formatMoney(cart.onlineTotal) }}
            </p>
            <p v-else class="mt-1 text-xs text-foreground-muted">Nothing added yet</p>
        </div>

        <!-- Perforation under the header, so the rail reads as a ticket stub
             being filled in rather than a generic side panel. -->
        <div class="perforation shrink-0" aria-hidden="true" />

        <CartContents />
    </aside>
</template>
