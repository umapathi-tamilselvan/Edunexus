<script setup>
defineProps({
    tone: { type: String, default: 'error' },
});
</script>

<template>
    <div
        role="alert"
        class="rounded-md border px-3 py-2 text-sm"
        :class="tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'"
    >
        <slot />
    </div>
</template>
