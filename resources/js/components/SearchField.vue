<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    label: { type: String, required: true },
    placeholder: { type: String, default: 'Search' },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <div class="relative w-full sm:w-72">
        <AppIcon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-500" />
        <input v-model="model" type="search" class="input pl-9" :placeholder="placeholder" :aria-label="label" />
    </div>
</template>
