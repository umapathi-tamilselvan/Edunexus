<script setup>
defineProps({
    label: { type: String, required: true },
    options: { type: Array, required: true },
});

const model = defineModel({ type: [String, Number], default: '' });
</script>

<template>
    <div role="tablist" :aria-label="label" class="inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-100 p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="tab"
            :aria-selected="model === option.value"
            class="h-8 cursor-pointer rounded-md px-3 text-2sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-primary"
            :class="model === option.value ? 'bg-white text-gray-900 shadow-xs ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'"
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
