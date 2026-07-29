<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useCartStore } from '@/stores/cart';
import CartContents from '@/Components/CartContents.vue';

const cart = useCartStore();
const open = ref(false);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') open.value = false;
};
onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div class="relative lg:hidden">
        <button
            type="button"
            @click="open = !open"
            class="relative inline-flex items-center rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground focus:outline-none"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l1 12h13l1.5-8H6M9 20a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            <span
                v-if="cart.count"
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-accent text-[10px] font-bold text-accent-fg"
            >
                {{ cart.count }}
            </span>
        </button>

        <div v-if="open" class="fixed inset-0 z-40 bg-foreground/20 backdrop-blur-sm" @click="open = false"></div>

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
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col border-l bg-surface shadow-xl"
            >
                <div class="flex items-center justify-between border-b p-4">
                    <h3 class="font-semibold text-foreground">Your Cart</h3>
                    <button type="button" @click="open = false" class="rounded-lg p-1 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                        </svg>
                    </button>
                </div>

                <CartContents @navigated="open = false" />
            </div>
        </Transition>
    </div>
</template>
