<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'inbox' },
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
        <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-500">
            <AppIcon :name="icon" class="size-6" />
        </span>
        <div class="flex flex-col gap-1">
            <p class="text-sm font-semibold text-gray-900">{{ title }}</p>
            <p v-if="description" class="max-w-md text-2sm text-gray-600">{{ description }}</p>
        </div>
        <slot />
    </div>
</template>
