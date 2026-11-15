<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    caption: { type: String, default: '' },
    inverse: { type: Boolean, default: false },
});
</script>

<template>
    <span class="flex min-w-0 items-center gap-2.5">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-primary to-primary-active text-white shadow-xs">
            <AppIcon name="academic" class="size-5" />
        </span>
        <span class="flex min-w-0 flex-col">
            <span class="truncate text-base/5 font-bold tracking-tight" :class="inverse ? 'text-white' : 'text-gray-900'">EduNexus</span>
            <span v-if="caption" class="truncate text-xs" :class="inverse ? 'text-white/70' : 'text-gray-600'">{{ caption }}</span>
        </span>
    </span>
</template>
