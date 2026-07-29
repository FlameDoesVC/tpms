<script setup>
import CartContents from '@/Components/CartContents.vue';
</script>

<template>
    <aside
        class="hidden lg:sticky lg:top-16 lg:flex lg:h-[calc(100vh-4rem)] lg:w-80 lg:shrink-0 lg:flex-col lg:self-start lg:border-l lg:bg-surface"
    >
        <div class="border-b p-4">
            <h3 class="font-semibold text-foreground">Your Cart</h3>
        </div>

        <CartContents />
    </aside>
</template>
