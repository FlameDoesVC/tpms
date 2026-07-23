<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useCartStore } from '@/stores/cart';
import CartContents from '@/Components/CartContents.vue';

const cart = useCartStore();
const open = ref(false);

// Only relevant below the lg breakpoint - the docked CartDockedPanel takes
// over on desktop, always open, so this toggle button/slide-over is hidden there.
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
            class="relative inline-flex items-center rounded-md border border-transparent bg-white px-2 py-2 text-gray-500 hover:text-gray-700 focus:outline-none"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l1 12h13l1.5-8H6M9 20a1 1 0 100-2 1 1 0 000 2zm9 0a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
            <span
                v-if="cart.count"
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-medium text-white"
            >
                {{ cart.count }}
            </span>
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

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
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-white shadow-xl"
            >
                <div class="flex items-center justify-between border-b border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900">Your Cart</h3>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
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
