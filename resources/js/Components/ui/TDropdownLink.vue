<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
    to: { type: [String, Object], default: null },
});

const router = useRouter();

const navigate = () => {
    if (props.to) router.push(props.to);
};
</script>

<template>
    <component
        :is="to ? 'router-link' : 'button'"
        :to="to"
        class="block w-full px-4 py-2 text-left text-sm text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
        @click="!to && navigate()"
    >
        <slot />
    </component>
</template>
