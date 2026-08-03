<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useCartStore } from '@/stores/cart';
import CartContents from '@/Components/CartContents.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { formatMoney } from '@/utils/format';

const cart = useCartStore();
const open = ref(false);
const panelRef = ref(null);
const triggerRef = ref(null);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') open.value = false;
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});

// A slide-over that covers the page has to behave like a dialog: lock the
// page behind it, move focus in, and hand focus back to the trigger on close.
watch(open, async (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
    if (isOpen) {
        await nextTick();
        panelRef.value?.focus();
    } else {
        triggerRef.value?.focus();
    }
});

// Keeps Tab inside the panel while it's open - without this, tabbing walks
// onto the inert page underneath.
const onPanelKeydown = (e) => {
    if (e.key !== 'Tab' || !panelRef.value) return;

    const focusable = panelRef.value.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;

    if (e.shiftKey && (active === first || active === panelRef.value)) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
    }
};
</script>

<template>
    <div class="relative lg:hidden">
        <button
            ref="triggerRef"
            type="button"
            class="relative inline-flex items-center rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :aria-label="cart.count ? `Itinerary, ${cart.count} item${cart.count === 1 ? '' : 's'}` : 'Itinerary, empty'"
            :aria-expanded="open"
            aria-haspopup="dialog"
            @click="open = !open"
        >
            <TIcon name="cart" :size="20" />
            <span
                v-if="cart.count"
                aria-hidden="true"
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-accent text-[10px] font-bold text-accent-fg"
            >
                {{ cart.count }}
            </span>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="scrim-soft fixed inset-0 z-40 backdrop-blur-sm" @click="open = false" />
        </Transition>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div
                v-if="open"
                ref="panelRef"
                role="dialog"
                aria-modal="true"
                aria-label="Your itinerary"
                tabindex="-1"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col border-l bg-surface shadow-xl focus:outline-none"
                @keydown="onPanelKeydown"
            >
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h3 class="font-semibold text-foreground">Your itinerary</h3>
                        <p v-if="cart.count" class="text-xs text-foreground-muted">
                            {{ cart.count }} item{{ cart.count === 1 ? '' : 's' }} · {{ formatMoney(cart.onlineTotal) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                        aria-label="Close itinerary"
                        @click="open = false"
                    >
                        <TIcon name="x" :size="20" />
                    </button>
                </div>

                <CartContents @navigated="open = false" />
            </div>
        </Transition>
    </div>
</template>
