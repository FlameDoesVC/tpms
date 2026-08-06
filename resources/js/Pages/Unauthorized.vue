<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TButton from '@/Components/ui/TButton.vue';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Unauthorized" />
        </template>

        <div class="shell pb-12 pt-5">
            <TEmptyState
                title="Access denied"
                description="You don't have permission to perform that action."
            >
                <template #action>
                    <router-link :to="{ name: 'dashboard' }">
                        <TButton variant="secondary">Back to dashboard</TButton>
                    </router-link>
                </template>
            </TEmptyState>
        </div>
    </AuthenticatedLayout>
</template>
