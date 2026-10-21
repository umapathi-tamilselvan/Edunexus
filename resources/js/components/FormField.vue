<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <label class="flex flex-col gap-1.5" :class="{ 'field-invalid': error }">
        <span class="text-2sm font-medium text-gray-800">{{ label }}</span>
        <slot />
        <span v-if="error" class="text-xs text-danger-strong">{{ error }}</span>
        <span v-else-if="hint" class="text-xs text-gray-600">{{ hint }}</span>
    </label>
</template>
