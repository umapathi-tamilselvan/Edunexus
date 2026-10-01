<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</span>
        <slot />
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-xs text-slate-500">{{ hint }}</span>
    </label>
</template>
