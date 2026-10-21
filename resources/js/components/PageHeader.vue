<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    back: { type: Object, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 flex-col gap-1">
            <RouterLink v-if="back" :to="back.to" class="mb-1 inline-flex w-fit items-center gap-1.5 text-2sm font-medium text-gray-600 transition-colors hover:text-primary">
                <AppIcon name="arrow-left" class="size-4" />
                {{ back.label }}
            </RouterLink>
            <h1 class="text-xl font-semibold text-gray-900">{{ title }}</h1>
            <p v-if="description || $slots.description" class="text-2sm text-gray-600">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2.5">
            <slot />
        </div>
    </div>
</template>
