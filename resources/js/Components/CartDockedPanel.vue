<script setup>
import CartContents from '@/Components/CartContents.vue';
</script>

<template>
    <!-- Hidden below lg (CartHeaderButton's slide-over covers mobile there) -
         always visible and docked in the page flow on desktop. -->
    <aside
        class="hidden lg:sticky lg:top-16 lg:flex lg:h-[calc(100vh-4rem)] lg:w-80 lg:shrink-0 lg:flex-col lg:self-start lg:border-l lg:border-gray-200 lg:bg-white"
    >
        <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Your Cart</h3>
        </div>

        <CartContents />
    </aside>
</template>
