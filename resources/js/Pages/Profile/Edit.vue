<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Profile" />
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border bg-surface p-4 sm:p-8">
                    <UpdateProfileInformationForm class="max-w-xl" />
                </div>

                <div class="rounded-xl border bg-surface p-4 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="rounded-xl border bg-surface p-4 sm:p-8">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
