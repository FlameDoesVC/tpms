<template>
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div v-if="$slots.title" class="border-b border-gray-200 px-4 py-3 font-medium text-gray-900">
            <slot name="title" />
        </div>
        <div class="p-4">
            <slot />
        </div>
    </div>
</template>
