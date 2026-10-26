<script setup>
import AppIcon from './AppIcon.vue';
import AppLogo from './AppLogo.vue';
import UserMenu from './UserMenu.vue';

defineProps({
    institution: { type: String, required: true },
    user: { type: Object, required: true },
    sidebarOpen: { type: Boolean, default: false },
});

defineEmits(['toggle-sidebar', 'sign-out']);
</script>

<template>
    <header class="fixed inset-x-0 top-0 z-40 flex h-17.5 items-center border-b border-gray-200 bg-white">
        <div class="flex h-full min-w-0 flex-1 items-center gap-1 pl-2 lg:w-65 lg:flex-none lg:border-r lg:border-gray-200 lg:px-6">
            <button
                type="button"
                class="btn-icon lg:hidden"
                aria-controls="app-sidebar"
                :aria-expanded="sidebarOpen"
                :aria-label="sidebarOpen ? 'Close menu' : 'Open menu'"
                @click="$emit('toggle-sidebar')"
            >
                <AppIcon :name="sidebarOpen ? 'x' : 'menu'" class="size-5" />
            </button>
            <RouterLink to="/dashboard" class="min-w-0 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary">
                <AppLogo :caption="institution" />
            </RouterLink>
        </div>

        <div class="flex shrink-0 items-center gap-2 px-3 sm:gap-3 sm:px-4 lg:flex-1 lg:justify-end lg:px-7.5">
            <slot />
            <UserMenu :user="user" @sign-out="$emit('sign-out')" />
        </div>
    </header>
</template>
